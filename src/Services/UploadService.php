<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Config;

/**
 * ============================================================
 * 📤 UploadService — unggahan gambar/video dari HP pengguna
 * ============================================================
 * Satu pintu untuk semua upload:
 *   bucket 'tmp'    → file perantara untuk Tools API (auto-prune 2 jam)
 *   bucket 'avatar' → foto profil member
 *   bucket 'sampul' → sampul profil
 *   bucket 'situs'  → logo/OG image dari Owner
 *   bucket 'produk' → gambar produk (masa depan)
 *
 * Semua file: nama acak 24-hex, validasi konten ASLI (bukan cuma
 * ekstensi), disajikan read-only lewat MediaController.
 * ============================================================
 */
final class UploadService
{
    /** Bucket yang sah → subfolder storage/uploads/. */
    public const BUCKETS = ['tmp', 'avatar', 'sampul', 'situs', 'produk', 'social'];

    private const IMG_EXT   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const VIDEO_EXT = ['mp4', 'webm'];

    /**
     * Simpan GAMBAR dari $_FILES → nama file. Lempar RuntimeException bila invalid.
     * @param array $f satu entri $_FILES[...]
     */
    public static function saveImage(array $f, string $bucket, int $maxMb = 6): string
    {
        self::validateCommon($f, $maxMb);
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::IMG_EXT, true)) {
            throw new \RuntimeException('Format harus JPG/PNG/WEBP/GIF.');
        }
        if (@getimagesize((string) $f['tmp_name']) === false) {
            throw new \RuntimeException('File bukan gambar valid.');
        }
        return self::store($f, $bucket, $ext);
    }

    /**
     * Simpan GAMBAR atau VIDEO (mp4/webm) — untuk bucket 'tmp' (Tools API).
     */
    public static function saveMedia(array $f, string $bucket = 'tmp', int $maxMb = 30): string
    {
        self::validateCommon($f, $maxMb);
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, self::IMG_EXT, true)) {
            if (@getimagesize((string) $f['tmp_name']) === false) {
                throw new \RuntimeException('File bukan gambar valid.');
            }
        } elseif (in_array($ext, self::VIDEO_EXT, true)) {
            $mime = (string) (mime_content_type((string) $f['tmp_name']) ?: '');
            if (!str_starts_with($mime, 'video/') && $mime !== 'application/octet-stream') {
                throw new \RuntimeException('File bukan video valid.');
            }
        } else {
            throw new \RuntimeException('Format harus JPG/PNG/WEBP/GIF/MP4/WEBM.');
        }
        return self::store($f, $bucket, $ext);
    }

    /** URL publik file terunggah (dipakai sebagai input URL ke API eksternal). */
    public static function publicUrl(string $bucket, string $name): string
    {
        return Config::appUrl() . '/media/' . rawurlencode($bucket) . '/' . rawurlencode($name);
    }

    /** Hapus file tmp yang lebih tua dari $maxAgeSec. Return jumlah terhapus. */
    public static function pruneTmp(int $maxAgeSec = 7200): int
    {
        $dir = self::dir('tmp');
        $n   = 0;
        foreach (glob($dir . '/*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < time() - $maxAgeSec) {
                if (@unlink($path)) {
                    $n++;
                }
            }
        }
        return $n;
    }

    // --------------------------------------------------------- internal

    private static function validateCommon(array $f, int $maxMb): void
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Unggahan gagal (kode ' . (int) ($f['error'] ?? -1) . '). Coba ulangi.');
        }
        if ((int) ($f['size'] ?? 0) > $maxMb * 1024 * 1024) {
            throw new \RuntimeException('File melebihi ' . $maxMb . 'MB.');
        }
        if (!is_uploaded_file((string) ($f['tmp_name'] ?? '')) && !is_file((string) ($f['tmp_name'] ?? ''))) {
            throw new \RuntimeException('Sumber unggahan tidak sah.');
        }
    }

    private static function store(array $f, string $bucket, string $ext): string
    {
        $dir  = self::dir($bucket);
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!@move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $name)
            && !@rename((string) $f['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Gagal menyimpan file.');
        }
        @chmod($dir . '/' . $name, 0640);
        if ($bucket === 'tmp') {
            self::pruneTmp(); // bersih-bersih sekalian setiap ada upload tmp baru
        }
        return $name;
    }

    private static function dir(string $bucket): string
    {
        if (!in_array($bucket, self::BUCKETS, true)) {
            throw new \RuntimeException('Bucket tidak dikenal.');
        }
        $dir = BASE_PATH . '/storage/uploads/' . $bucket;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        // Benteng eksekusi: tidak ada skrip yang boleh jalan dari folder upload
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "php_flag engine off\nOptions -ExecCGI\nAddHandler text/plain .php .phtml .php3 .php4 .php5 .phar\n");
        }
        return $dir;
    }
}
