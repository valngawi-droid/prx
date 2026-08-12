<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Config;

/**
 * File Manager untuk Owner (God Mode).
 *
 * Keamanan:
 *  - Semua path dinormalisasi & dipaksa berada di dalam FILEMANAGER_ROOT
 *    (anti directory traversal "../../etc/passwd").
 *  - File biner / terlalu besar ditolak untuk diedit.
 *  - Setiap perubahan otomatis membuat BACKUP ke storage/backups
 *    + tercatat di audit log & Discord.
 */
final class FileManagerService
{
    /** Folder yang tidak ditampilkan di tree (demi performa & keamanan). */
    private const HIDDEN_DIRS = ['.git', 'node_modules', 'vendor', 'storage/backups'];
    private const MAX_DEPTH   = 8;

    private static string $root;

    private static function root(): string
    {
        if (!isset(self::$root)) {
            $real = realpath(Config::fileManagerRoot());
            if ($real === false) {
                throw new \RuntimeException('FILEMANAGER_ROOT tidak valid.');
            }
            self::$root = rtrim($real, DIRECTORY_SEPARATOR);
        }
        return self::$root;
    }

    /**
     * Ubah path relatif → absolut yang dijamin berada di dalam root.
     * @throws \RuntimeException jika keluar dari root
     */
    public static function resolve(string $relative, bool $mustExist = true): string
    {
        $relative = str_replace("\0", '', $relative);
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        $candidate = self::root() . '/' . $relative;

        if ($mustExist) {
            $real = realpath($candidate);
            if ($real === false) {
                throw new \RuntimeException('Path tidak ditemukan: ' . $relative);
            }
        } else {
            // File belum ada: validasi direktori induknya saja
            $parent = realpath(dirname($candidate));
            if ($parent === false) {
                throw new \RuntimeException('Direktori induk tidak ditemukan.');
            }
            $real = $parent . DIRECTORY_SEPARATOR . basename($candidate);
        }

        $root = self::root();
        if ($real !== $root && !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Akses ditolak: path berada di luar root file manager.');
        }
        return $real;
    }

    public static function toRelative(string $absolute): string
    {
        $root = self::root();
        if ($absolute === $root) {
            return '';
        }
        return ltrim(substr($absolute, strlen($root)), DIRECTORY_SEPARATOR);
    }

    /**
     * Bangun directory tree rekursif (format siap-render JSON).
     * @return array<int, array<string, mixed>>
     */
    public static function tree(?string $subDir = null, int $depth = 0): array
    {
        $base = $subDir === null ? self::root() : self::resolve($subDir);
        if (!is_dir($base) || $depth > self::MAX_DEPTH) {
            return [];
        }
        $items = [];
        $entries = scandir($base) ?: [];
        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

        // Folder dulu, baru file
        $dirs = [];
        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.') && $entry !== '.env.example') {
                continue;
            }
            $full = $base . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                if (!in_array($entry, self::HIDDEN_DIRS, true)) {
                    $dirs[] = $entry;
                }
            } else {
                $files[] = $entry;
            }
        }

        foreach (array_merge($dirs, $files) as $entry) {
            $full = $base . DIRECTORY_SEPARATOR . $entry;
            $rel  = self::toRelative($full);
            $isDir = is_dir($full);
            $items[] = [
                'name'     => $entry,
                'path'     => $rel,
                'type'     => $isDir ? 'dir' : 'file',
                'size'     => $isDir ? null : filesize($full),
                'modified' => date('Y-m-d H:i:s', (int) filemtime($full)),
                'children' => $isDir ? self::tree($rel, $depth + 1) : null,
            ];
        }
        return $items;
    }

    /**
     * Baca isi file teks untuk Monaco Editor.
     * @return array{content:string, language:string, size:int, readonly:bool}
     */
    public static function read(string $relative): array
    {
        $abs = self::resolve($relative);
        if (!is_file($abs)) {
            throw new \RuntimeException('Bukan sebuah file.');
        }
        $size = (int) filesize($abs);
        if ($size > Config::fileManagerMaxEditBytes()) {
            throw new \RuntimeException('File terlalu besar untuk diedit (' . round($size / 1024) . ' KB).');
        }
        $content = file_get_contents($abs);
        if ($content === false) {
            throw new \RuntimeException('Gagal membaca file.');
        }
        if (self::looksBinary($content)) {
            throw new \RuntimeException('File biner tidak dapat diedit di sini.');
        }
        return [
            'content'  => $content,
            'language' => self::monacoLanguage(pathinfo($abs, PATHINFO_EXTENSION)),
            'size'     => $size,
            'readonly' => !is_writable($abs),
        ];
    }

    /** Simpan perubahan file (dengan backup otomatis). */
    public static function save(string $relative, string $content, string $editorEmail): void
    {
        $abs = self::resolve($relative);
        if (!is_file($abs)) {
            throw new \RuntimeException('File tidak ditemukan.');
        }
        if (strlen($content) > Config::fileManagerMaxEditBytes()) {
            throw new \RuntimeException('Konten melebihi batas ukuran edit.');
        }
        self::backup($abs, $relative);
        if (file_put_contents($abs, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal menulis file (izin ditolak?).');
        }
        clearstatcache(true, $abs);
        AuditLogger::record('file.edit', ['path' => $relative], 'warning');
        DiscordWebhook::logFileChange($editorEmail, 'Edit file', $relative);
    }

    /** Buat file baru. */
    public static function create(string $relative, string $editorEmail): void
    {
        if (!preg_match('#^[a-zA-Z0-9._\-/]+$#', $relative) || str_contains($relative, '..')) {
            throw new \RuntimeException('Nama file tidak valid.');
        }
        $abs = self::resolve($relative, mustExist: false);
        if (file_exists($abs)) {
            throw new \RuntimeException('File sudah ada.');
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $stub = match ($ext) {
            'php'   => "<?php\n\ndeclare(strict_types=1);\n\n",
            'html'  => "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title>ChiperX</title>\n</head>\n<body>\n\n</body>\n</html>\n",
            'css'   => "/* ChiperX stylesheet */\n\n",
            'js'    => "'use strict';\n\n",
            default => '',
        };
        if (file_put_contents($abs, $stub, LOCK_EX) === false) {
            throw new \RuntimeException('Gagal membuat file.');
        }
        AuditLogger::record('file.create', ['path' => $relative], 'warning');
        DiscordWebhook::logFileChange($editorEmail, 'Buat file baru', $relative);
    }

    /** Buat folder baru. */
    public static function mkdir(string $relative, string $editorEmail): void
    {
        if (!preg_match('#^[a-zA-Z0-9._\-/]+$#', $relative) || str_contains($relative, '..')) {
            throw new \RuntimeException('Nama folder tidak valid.');
        }
        $abs = self::resolve($relative, mustExist: false);
        if (file_exists($abs)) {
            throw new \RuntimeException('Folder sudah ada.');
        }
        if (!mkdir($abs, 0755) && !is_dir($abs)) {
            throw new \RuntimeException('Gagal membuat folder.');
        }
        AuditLogger::record('file.mkdir', ['path' => $relative], 'warning');
        DiscordWebhook::logFileChange($editorEmail, 'Buat folder', $relative);
    }

    /** Hapus file (folder hanya jika kosong). */
    public static function delete(string $relative, string $editorEmail): void
    {
        $abs = self::resolve($relative);
        if ($abs === self::root()) {
            throw new \RuntimeException('Tidak dapat menghapus root.');
        }
        if (is_dir($abs)) {
            if (!rmdir($abs)) {
                throw new \RuntimeException('Folder tidak kosong atau gagal dihapus.');
            }
        } else {
            self::backup($abs, $relative); // amankan dulu sebelum dihapus
            if (!unlink($abs)) {
                throw new \RuntimeException('Gagal menghapus file.');
            }
        }
        AuditLogger::record('file.delete', ['path' => $relative], 'critical');
        DiscordWebhook::logFileChange($editorEmail, 'Hapus file/folder', $relative);
    }

    /** Backup versi lama ke storage/backups (retensi 30 berkas per file). */
    private static function backup(string $abs, string $relative): void
    {
        $dir = BASE_PATH . '/storage/backups/' . dirname(str_replace('/', '_', $relative));
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $target = $dir . '/' . basename($relative) . '.' . date('Ymd_His') . '.bak';
        @copy($abs, $target);

        // Rotasi sederhana
        $backups = glob($dir . '/' . basename($relative) . '.*.bak') ?: [];
        rsort($backups);
        foreach (array_slice($backups, 30) as $old) {
            @unlink($old);
        }
    }

    private static function looksBinary(string $content): bool
    {
        return str_contains(substr($content, 0, 4096), "\0");
    }

    public static function monacoLanguage(string $ext): string
    {
        return match (strtolower($ext)) {
            'php'              => 'php',
            'js', 'mjs'        => 'javascript',
            'ts'               => 'typescript',
            'css'              => 'css',
            'html', 'htm'      => 'html',
            'json'             => 'json',
            'sql'              => 'sql',
            'md'               => 'markdown',
            'xml', 'svg'       => 'xml',
            'yml', 'yaml'      => 'yaml',
            'env', 'example', 'htaccess', 'conf', 'ini' => 'ini',
            'sh'               => 'shell',
            default            => 'plaintext',
        };
    }
}
