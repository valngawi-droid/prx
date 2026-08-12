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
 * bin/migrate.php + bin/doctor.php untuk eksekusi manual via CLI.
 *
 * Ketangguhan:
 *  - Splitter SADAR-TANDA-KUTIP: titik koma di dalam string
 *    (mis. COMMENT 'Maksimal klaim total; NULL = tanpa batas')
 *    TIDAK mematahkan statement.
 *  - Idempoten  : error "sudah ada" (tabel/kolom/index/entri ganda)
 *                 dianggap wajar & dilewati — aman untuk DB yang
 *                 sebagian migrasinya pernah diimpor manual.
 *  - File lock  : dua request bersamaan tidak migrasi dobel.
 *  - Hash flag  : setelah semua bersih, request berikutnya = 0 query.
 *  - Cooldown   : bila ada yang gagal, percobaan ulang di-throttle
 *                 60 detik agar DB tak dihantam tiap request.
 * ============================================================
 */
class Migrator
{
    /**
     * Terapkan semua migrasi yang tertinggal.
     *
     * @param bool $verbose  cetak progres ke stdout (mode CLI)
     * @param bool $force    abaikan flag hash & cooldown (selalu periksa DB)
     * @return array{applied: string[], failed: array<string, string[]>}
     *               applied = file yang BARU diterapkan bersih;
     *               failed  = nama file => daftar pesan error statement-nya
     */
    public static function sync(bool $verbose = false, bool $force = false): array
    {
        $kosong = ['applied' => [], 'failed' => []];

        $dir   = rtrim((string) (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)), '/') . '/database/migrations';
        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        if ($files === []) {
            return $kosong;
        }

        // ── Jalur cepat #1: hash daftar file cocok = tidak ada migrasi baru ──
        $hash     = md5(implode('|', array_map('basename', $files)));
        $flagFile = self::storageDir() . '/schema.ok';
        if (!$force && is_file($flagFile) && trim((string) @file_get_contents($flagFile)) === $hash) {
            return $kosong;
        }

        // ── Jalur cepat #2: cooldown kegagalan (max 1x percobaan / 60 dtk) ──
        $failFlag = self::storageDir() . '/migrate.fail';
        if (!$force && is_file($failFlag) && (time() - (int) @filemtime($failFlag)) < 60) {
            return $kosong;
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

            $baru   = [];
            $failed = [];
            foreach ($files as $file) {
                $name = basename($file);
                if (isset($applied[$name])) {
                    continue;
                }

                $gagal = [];
                $sql   = (string) file_get_contents($file);
                foreach (self::statements($sql) as $stmt) {
                    try {
                        Database::run($stmt);
                    } catch (\Throwable $e) {
                        if (self::isWajar($e)) {
                            continue; // sudah pernah diterapkan manual — lewati
                        }
                        $gagal[] = $e->getMessage();
                        self::log("GAGAL [{$name}]: " . $e->getMessage());
                    }
                }

                if ($gagal === []) {
                    Database::run('INSERT INTO migrations (name) VALUES (?)', [$name]);
                    $baru[] = $name;
                    if ($verbose) {
                        echo "  \033[32m✔\033[0m diterapkan: {$name}\n";
                    }
                } else {
                    $failed[$name] = $gagal; // JANGAN tandai — dicoba ulang nanti
                    if ($verbose) {
                        echo "  \033[31m✘\033[0m {$name}: " . implode(' | ', $gagal) . "\n";
                    }
                }
            }

            if ($failed === []) {
                // Semua bersih → flag hash aktif; cooldown gagal dibersihkan.
                @file_put_contents($flagFile, $hash, LOCK_EX);
                if (is_file($failFlag)) {
                    @unlink($failFlag);
                }
            } else {
                // Tandai waktu kegagalan → request berikutnya menunggu cooldown.
                @touch($failFlag);
                self::log('Pending-gagal: ' . implode(', ', array_keys($failed)));
            }

            return ['applied' => $baru, 'failed' => $failed];
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
     * Pecah isi file .sql menjadi statement utuh — SADAR TANDA KUTIP.
     *
     * Titik koma pemisah HANYA berlaku di luar string:
     *   'string'  "string"  `identifier`  (lolosan \x dan '' dihormati)
     *
     * Selain itu:
     *  - komentar baris `-- ...` dan blok `/* ... *\/` dibuang
     *  - `USE ...;` diabaikan (DB dipilih lewat DSN; nama DB di file
     *    bisa berbeda dengan konfigurasi lingkungan)
     *
     * @return string[]
     */
    private static function statements(string $sql): array
    {
        $out   = [];
        $buf   = '';
        $n     = strlen($sql);
        $i     = 0;
        $state = 'code'; // code | sq | dq | bt | block

        $flush = static function () use (&$out, &$buf): void {
            $stmt = trim($buf);
            $buf  = '';
            if ($stmt !== '' && !preg_match('/^USE\s+/i', $stmt)) {
                $out[] = $stmt;
            }
        };

        while ($i < $n) {
            $c   = $sql[$i];
            $nxt = $i + 1 < $n ? $sql[$i + 1] : '';

            switch ($state) {
                case 'code':
                    if ($c === "'") {
                        $state = 'sq';
                        $buf .= $c;
                    } elseif ($c === '"') {
                        $state = 'dq';
                        $buf .= $c;
                    } elseif ($c === '`') {
                        $state = 'bt';
                        $buf .= $c;
                    } elseif ($c === '-' && $nxt === '-') {
                        // komentar baris → buang sampai EOL
                        while ($i < $n && $sql[$i] !== "\n") {
                            $i++;
                        }
                        continue;
                    } elseif ($c === '/' && $nxt === '*') {
                        $state = 'block';
                        $i += 2;
                        continue;
                    } elseif ($c === ';') {
                        $flush();
                    } else {
                        $buf .= $c;
                    }
                    break;

                case 'sq': // di dalam 'string'
                    if ($c === '\\') {
                        $buf .= $c . $nxt;
                        $i += 2;
                        continue;
                    }
                    if ($c === "'") {
                        if ($nxt === "'") { // '' = kutip ter-escape
                            $buf .= "''";
                            $i += 2;
                            continue;
                        }
                        $state = 'code';
                    }
                    $buf .= $c;
                    break;

                case 'dq': // di dalam "string"
                    if ($c === '\\') {
                        $buf .= $c . $nxt;
                        $i += 2;
                        continue;
                    }
                    if ($c === '"') {
                        $state = 'code';
                    }
                    $buf .= $c;
                    break;

                case 'bt': // di dalam `identifier`
                    if ($c === '`') {
                        $state = 'code';
                    }
                    $buf .= $c;
                    break;

                case 'block': // di dalam /* komentar */ → buang
                    if ($c === '*' && $nxt === '/') {
                        $state = 'code';
                        $i += 2;
                        continue;
                    }
                    break;
            }
            $i++;
        }
        $flush(); // statement terakhir tanpa titik koma

        return $out;
    }

    /** Catat satu baris ke storage/logs/migrate.log. */
    private static function log(string $pesan): void
    {
        @file_put_contents(
            self::storageDir() . '/migrate.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $pesan . "\n",
            FILE_APPEND | LOCK_EX
        );
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
