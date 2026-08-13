<?php

declare(strict_types=1);

/**
 * ============================================================
 * ChiperX — CRON versi WEB (untuk shared hosting TANPA SSH/crontab,
 *           mis. SmarterASP/site4now)
 * ------------------------------------------------------------
 * Dipanggil lewat URL oleh layanan cron gratis (cron-job.org / UptimeRobot):
 *
 *   https://DOMAINMU/cron-web.php?key=APP_KEY
 *
 * Aman dipanggil sesering apa pun (mis. tiap 30 menit):
 *  - purge-otp      → jalan maks. 1x per hari
 *  - weekly-rewards → jalan maks. 1x per minggu (Senin)
 * Jadwal terakhir dicatat di storage/cache/cron-web.json.
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

header('Content-Type: text/plain; charset=utf-8');

// ── Kunci rahasia wajib = APP_KEY di .env ──
$expected = (string) Env::get('APP_KEY', '');
$given    = (string) ($_GET['key'] ?? '');
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit("403 Forbidden — key salah. URL benar: /cron-web.php?key=APP_KEY\n");
}

$cacheFile = BASE_PATH . '/storage/cache/cron-web.json';
$last      = is_file($cacheFile) ? (json_decode((string) file_get_contents($cacheFile), true) ?: []) : [];

$today    = date('Y-m-d');
$weekId   = date('o-\WW');   // contoh: 2026-W33
$isMonday = ((int) date('N')) === 1;

echo "⚙️  ChiperX Cron-Web — " . date('Y-m-d H:i:s T') . "\n";

// ── Tugas harian: bersihkan OTP kedaluwarsa ──
if (($last['purge-otp'] ?? '') !== $today) {
    OtpCode::purgeExpired();
    $last['purge-otp'] = $today;
    echo "✅ purge-otp: OTP kedaluwarsa dibersihkan.\n";
} else {
    echo "⏭️  purge-otp: sudah jalan hari ini.\n";
}

// ── Tugas mingguan: hadiah leaderboard (hanya Senin) ──
if ($isMonday && ($last['weekly-rewards'] ?? '') !== $weekId) {
    $result = LeaderboardService::distributeLastWeek();
    echo ($result['ok'] ? '✅ weekly-rewards: ' : '⚠️  weekly-rewards: ') . $result['message'] . "\n";
    $last['weekly-rewards'] = $weekId;
} else {
    echo "⏭️  weekly-rewards: " . ($isMonday ? 'sudah jalan minggu ini.' : 'bukan Senin, dilewati.') . "\n";
}

@file_put_contents($cacheFile, json_encode($last, JSON_PRETTY_PRINT));
echo "🏁 Selesai.\n";
