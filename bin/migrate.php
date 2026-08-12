<?php

declare(strict_types=1);

/**
 * ============================================================
 * ChiperX Migrasi — penerap migrasi database manual (CLI)
 * ============================================================
 * Jalankan:  php bin/migrate.php
 *
 * CATATAN: sejak v2.6 aplikasi mengauto-migrasi sendiri setiap
 * request — skrip ini hanya alat bantu bila kamu ingin menerapkan
 * migrasi SEKARANG lewat terminal (atau memastikan skema bersih).
 */

define('BASE_PATH', dirname(__DIR__));

// Autoloader PSR-4 manual (harus jalan bahkan tanpa composer)
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'ChiperX\\')) {
        $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require BASE_PATH . '/src/Core/helpers.php';

use ChiperX\Core\Env;
use ChiperX\Core\Migrator;

Env::load(BASE_PATH . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Jakarta'));

echo "\n🔄 \033[1mChiperX Auto-Migrasi\033[0m\n";
echo "   Memindai database/migrations/*.sql ...\n\n";

try {
    $hasil = Migrator::sync(verbose: true, force: true);
    if ($hasil['failed'] !== []) {
        echo "\n❌ \033[31mAda migrasi yang GAGAL:\033[0m\n";
        foreach ($hasil['failed'] as $nama => $errList) {
            echo "   • {$nama}\n";
            foreach ($errList as $err) {
                echo "     → {$err}\n";
            }
        }
        echo "\n   Detail juga tercatat di storage/logs/migrate.log\n\n";
        exit(1);
    }
    if ($hasil['applied'] === []) {
        echo "  ✨ Skema sudah terbaru — tidak ada migrasi yang tertinggal.\n";
    }
    echo "\n✅ \033[32mSelesai!\033[0m Database siap tempur.\n\n";
    exit(0);
} catch (\Throwable $e) {
    echo "\n❌ \033[31mGAGAL:\033[0m " . $e->getMessage() . "\n";
    echo "   Periksa koneksi DB di .env (DB_HOST/DB_USER/DB_PASS) lalu coba lagi.\n\n";
    exit(1);
}
