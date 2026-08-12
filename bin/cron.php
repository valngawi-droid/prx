#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * ============================================================
 * ChiperX — Tugas Terjadwal (cron)
 * ------------------------------------------------------------
 * Pasang di crontab server (timezone WIB):
 *
 *   # Bagikan hadiah leaderboard tiap Senin 00:05 WIB
 *   5 0 * * 1  php /var/www/chiperx/bin/cron.php weekly-rewards
 *
 *   # Bersihkan OTP kedaluwarsa tiap hari 03:00 WIB
 *   0 3 * * *  php /var/www/chiperx/bin/cron.php purge-otp
 * ============================================================
 */
define('BASE_PATH', dirname(__DIR__));

$composer = BASE_PATH . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'ChiperX\\')) {
            $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require BASE_PATH . '/src/Core/helpers.php';
}

use ChiperX\Core\Env;
use ChiperX\Models\OtpCode;
use ChiperX\Services\LeaderboardService;

Env::load(BASE_PATH . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Jakarta'));

$cmd = $argv[1] ?? 'help';

echo "⚙️  ChiperX Cron — " . date('Y-m-d H:i:s T') . "\n";

switch ($cmd) {
    case 'weekly-rewards':
        $result = LeaderboardService::distributeLastWeek();
        echo ($result['ok'] ? '✅ ' : '⚠️  ') . $result['message'] . "\n";
        foreach (($result['winners'] ?? []) as $w) {
            echo "   #{$w['rank']} {$w['name']} — menang {$w['earned']} koin, bonus +{$w['bonus']}\n";
        }
        exit($result['ok'] ? 0 : 1);

    case 'purge-otp':
        OtpCode::purgeExpired();
        echo "✅ OTP kedaluwarsa dibersihkan.\n";
        exit(0);

    default:
        echo "Perintah tersedia:\n";
        echo "  weekly-rewards   Bagikan hadiah leaderboard pekan lalu (top 3)\n";
        echo "  purge-otp        Hapus OTP kedaluwarsa dari database\n";
        exit(0);
}
