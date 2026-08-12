<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * ============================================================
 * Migrator — AUTO-MIGRASI database 🤖
 * ============================================================
 * Memindai database/migrations/*.sql (urut nama file), mencatat
 * file yang sudah diterapkan di tabel `migrations`, lalu menerapkan
 * sisanya — OTOMATIS, tanpa perlu perintah manual sama sekali.
 *
 * Dipanggil dari public/index.php setiap request (super murah:
 * steady-state hanya membaca 1 file flag kecil) dan dari
 * bin/migrate.php untuk eksekusi manual via CLI.
 *
 * Ketangguhan:
 *  - Idempoten  : error "sudah ada" (tabel/kolom/index/entri ganda)
 *                 dianggap Wajar & dilewati — aman untuk DB yang
 *                 sebagian migrasinya pernah diimpor manual.
 *  - File lock  : dua request bersamaan tidak migrasi dobel.
 *  - Hash flag  : setelah semua bersih, request berikutnya = 0 query.
 * ============================================================
 */
class Migrator
{
    /**
     * Terapkan semua migrasi yang tertinggal.
     *
     * @param bool $verbose  cetak progres ke stdout (mode CLI)
     * @param bool $force    abaikan flag hash (selalu periksa DB)
     * @return string[] daftar nama file migrasi yang BARU diterapkan
     */
    public static function sync(bool $verbose = false, bool $force = false): array
    {
        $dir   = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)), '/') . '/database/migrations';
        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        if ($files === []) {
            return [];
        }

        // ── Jalur cepat: hash daftar file cocok = tidak ada migrasi baru ──
        $hash     = md5(implode('|', array_map('basename', $files)));
        $flagFile = self::storageDir() . '/schema.ok';
        if (!$force && is_file($flagFile) && trim((string) @file_get_contents($flagFile)) === $hash) {
            return [];
        }

        // ── Serialkan antar-proses: pemenang lock yang migrasi ──
        $lock = @fopen(self::storageDir() . '/migrate.lock', 'c');
        if ($lock !== false) {
            flock($lock, LOCK_EX);
        }

        try {
            self::ensureTable();

            $applied = [];
            foreach (Database::all('SELECT name FROM migrations') as $row) {
                $applied[(string) $row['name']] = true;
            }

            $baru      = [];
            $adaGagal  = false;
            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) {
                    continue;
                }

                $gagal   = [];
                $sql     = (string) file_get_contents($file);
                $logLine = static function (string $msg): void {
                    @file_put_contents(
                        self::storageDir() . '/migrate.log',
                        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n",
                        FILE_APPEND | LOCK_EX
                    );
                };

                foreach (self::statements($sql) as $stmt) {
                    try {
                        Database::run($stmt);
                    } catch (\Throwable $e) {
                        if (self::isWajar($e)) {
                            continue; // sudah pernah diterapkan manual — lewati
                        }
                        $gagal[] = $e->getMessage();
                        $logLine("GAGAL [{$name}]: " . $e->getMessage());
                    }
                }

                if ($gagal === []) {
                    Database::run('INSERT INTO migrations (name) VALUES (?)', [$name]);
                    $baru[] = $name;
                    if ($verbose) {
                        echo "  \033[32m✔\033[0m diterapkan: {$name}\n";
                    }
                } else {
                    $adaGagal = true; // JANGAN tandai — dicoba ulang lain waktu
                    if ($verbose) {
                        echo "  \033[33m⚠\033[0m {$name}: " . implode(' | ', $gagal) . "\n";
                    }
                }
            }

            // Semua file bersih → tulis flag hash agar request berikutnya 0-query.
            if (!$adaGagal) {
                @file_put_contents($flagFile, $hash, LOCK_EX);
            }

            return $baru;
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** Tabel pelacak migrasi (dibuat bila belum ada). */
    private static function ensureTable(): void
    {
        Database::run(
            'CREATE TABLE IF NOT EXISTS migrations (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(190) NOT NULL UNIQUE,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Error yang WAJAR saat migrasi diterapkan di DB yang sebagian
     * skemanya sudah ada (mis. hasil import manual): tabel/kolom/index
     * duplikat & entri seed ganda. Selain itu = error sungguhan.
     */
    private static function isWajar(\Throwable $e): bool
    {
        $info = ($e instanceof \PDOException) ? ($e->errorInfo ?? []) : [];
        $kode = isset($info[1]) ? (int) $info[1] : 0;
        if (in_array($kode, [1050, 1060, 1061, 1062], true)) {
            return true; // table exists / duplicate column / duplicate key / duplicate entry
        }
        $msg = $e->getMessage();
        return str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate');
    }

    /**
     * Pecah isi file .sql menjadi statement terpisah.
     *  - membuang komentar blok `/* ... *\/` & baris `-- ...`
     *  - mengabaikan `USE ...;` (DB sudah dipilih lewat DSN; nama DB
     *    di file belum tentu sama dengan konfigurasi lingkungan)
     *  - memotong pada titik koma (migrasi kita murni DDL/DML polos)
     *
     * @return string[]
     */
    private static function statements(string $sql): array
    {
        $sql   = (string) (preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql);
        $lines = [];
        foreach (preg_split('/\r?\n/', $sql) ?: [] as $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '--')) {
                continue;
            }
            $lines[] = $line;
        }
        $sql = implode("\n", $lines);

        $out = [];
        foreach (explode(';', $sql) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || preg_match('/^USE\s+/i', $chunk)) {
                continue;
            }
            $out[] = $chunk;
        }
        return $out;
    }

    /** Folder penyimpanan flag/lock/log migrasi. */
    private static function storageDir(): string
    {
        $dir = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)), '/') . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }
}
