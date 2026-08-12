<?php

declare(strict_types=1);

/**
 * ============================================================
 * ChiperX Doctor — pemeriksa kesehatan lingkungan (CLI)
 * ============================================================
 * Jalankan:  php bin/doctor.php
 *
 * Memeriksa SEMUA prasyarat aplikasi satu per satu — versi PHP,
 * ekstensi, koneksi database, tabel, izin folder, konfigurasi
 * email/Discord/payment — dan melaporkan OK/GAGAL per butir.
 */

define('BASE_PATH', dirname(__DIR__));

// Autoloader PSR-4 manual (doctor harus jalan bahkan tanpa composer)
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'ChiperX\\')) {
        $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
if (is_file(BASE_PATH . '/src/Core/helpers.php')) {
    require_once BASE_PATH . '/src/Core/helpers.php';
}

use ChiperX\Core\Database;
use ChiperX\Core\Env;

$gagal = 0;
$total = 0;

/** Cetak satu butir pemeriksaan. */
function periksa(string $label, bool $ok, string $catatan = ''): void
{
    global $gagal, $total;
    $total++;
    if (!$ok) {
        $gagal++;
    }
    $ikon  = $ok ? "\e[32m[ OK ]\e[0m" : "\e[31m[GAGAL]\e[0m";
    $extra = $catatan !== '' ? " \e[90m— {$catatan}\e[0m" : '';
    echo " {$ikon} {$label}{$extra}\n";
}

function info(string $teks): void
{
    echo "\e[36m{$teks}\e[0m\n";
}

info("\n╔══════════════════════════════════════════╗");
info("║        🩺 ChiperX Doctor — mulai         ║");
info("╚══════════════════════════════════════════╝\n");

// ---------------------------------------------------------------
info("▶ PHP & Ekstensi");
// ---------------------------------------------------------------
periksa('PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION);

$wajib = ['pdo_mysql', 'mbstring', 'openssl', 'json', 'session', 'hash', 'pcre'];
foreach ($wajib as $ext) {
    periksa("Ekstensi {$ext}", extension_loaded($ext));
}
periksa(
    'Ekstensi curl',
    extension_loaded('curl'),
    extension_loaded('curl') ? '' : 'opsional untuk Discord/email SMTP; wajib untuk payment gateway'
);

$argon = defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true);
periksa('Hash Argon2id (OTP)', true, $argon ? 'argon2id tersedia' : 'TIDAK tersedia → otomatis fallback BCrypt (aman)');

// ---------------------------------------------------------------
info("\n▶ Berkas & Izin Folder");
// ---------------------------------------------------------------
periksa('File .env ada', is_file(BASE_PATH . '/.env'), 'salin dari .env.example bila belum ada');
foreach (['storage/logs', 'storage/uploads', 'storage/backups', 'storage/cache'] as $dir) {
    $path = BASE_PATH . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
    periksa("{$dir}/ writable", is_dir($path) && is_writable($path));
}
periksa(
    'PHPMailer (composer vendor)',
    is_file(BASE_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php'),
    is_file(BASE_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php') ? 'SMTP penuh aktif' : 'tanpa vendor → email otomatis fallback ke storage/logs/mail.log'
);

// ---------------------------------------------------------------
info("\n▶ Konfigurasi .env");
// ---------------------------------------------------------------
Env::load(BASE_PATH . '/.env');
periksa('APP_URL terisi', Env::get('APP_URL', '') !== '', Env::get('APP_URL', '(kosong)'));
periksa('APP_DEBUG diset', in_array(Env::get('APP_DEBUG', ''), ['true', 'false'], true), 'nilai: ' . Env::get('APP_DEBUG', '(kosong)'));
$smtpTerisi = Env::get('SMTP_USERNAME', '') !== '' && Env::get('SMTP_PASSWORD', '') !== '';
periksa('Kredensial SMTP', $smtpTerisi, Env::get('MAIL_DRIVER', 'smtp') === 'log' ? 'MAIL_DRIVER=log (mode dev, email → mail.log)' : ($smtpTerisi ? Env::get('SMTP_USERNAME') : 'kosong → email fallback ke mail.log'));
// Uji jangkauan TCP ke server SMTP — operator seluler kadang memblokir port!
$smtpHost = trim((string) Env::get('SMTP_HOST', ''));
$smtpPort = (int) Env::get('SMTP_PORT', '587');
if (Env::get('MAIL_DRIVER', 'smtp') !== 'log' && $smtpHost !== '') {
    $errno = 0;
    $errstr = '';
    $mulai = microtime(true);
    $conn = @fsockopen(($smtpPort === 465 ? 'ssl://' : '') . $smtpHost, $smtpPort, $errno, $errstr, 6);
    $ms = (int) ((microtime(true) - $mulai) * 1000);
    if (is_resource($conn)) {
        fclose($conn);
        periksa("Jaringan → {$smtpHost}:{$smtpPort}", true, "terhubung dalam {$ms}ms");
    } else {
        periksa("Jaringan → {$smtpHost}:{$smtpPort}", false, 'DITOLAK: ' . trim($errstr !== '' ? $errstr : "kode {$errno}") . ' — port diblokir operator? Coba 587 ↔ 465 ↔ 2525');
    }
}
// Mode Kirim.Email API — butuh key/secret/domain + HTTPS 443 reachable
if (Env::get('MAIL_DRIVER', 'smtp') === 'kirimemail') {
    $keKey = Env::get('KIRIMEMAIL_API_KEY', '');
    $keSec = Env::get('KIRIMEMAIL_API_SECRET', '');
    $keDom = Env::get('KIRIMEMAIL_DOMAIN', '');
    periksa(
        'Kirim.Email key/secret/domain',
        $keKey !== '' && $keSec !== '' && $keDom !== '',
        $keKey !== '' && $keSec !== '' ? 'domain: ' . ($keDom !== '' ? $keDom : '(KOSONG!)') : 'API key/secret kosong — isi di .env atau Owner → Integrasi'
    );
    periksa('Ekstensi curl (mode API)', function_exists('curl_init'), function_exists('curl_init') ? 'aktif' : 'TIDAK aktif — mode kirimemail butuh curl');
    $errno = 0;
    $errstr = '';
    $mulai = microtime(true);
    $conn = @fsockopen('ssl://smtp-app.kirim.email', 443, $errno, $errstr, 6);
    $ms = (int) ((microtime(true) - $mulai) * 1000);
    if (is_resource($conn)) {
        fclose($conn);
        periksa('Jaringan → smtp-app.kirim.email:443', true, "HTTPS terhubung dalam {$ms}ms");
    } else {
        periksa('Jaringan → smtp-app.kirim.email:443', false, 'DITOLAK: ' . trim($errstr !== '' ? $errstr : "kode {$errno}") . ' — cek koneksi internet HP');
    }
}
$driver = Env::get('PAYMENT_DRIVER', 'pakasir');
periksa('PAYMENT_DRIVER valid', in_array($driver, ['pakasir', 'duitku', 'tripay', 'midtrans', 'paydisini'], true), $driver);

// ---------------------------------------------------------------
info("\n▶ Database");
// ---------------------------------------------------------------
$dbOk = false;
try {
    $pdo = Database::pdo();
    $dbOk = true;
    periksa('Koneksi MySQL', true, Env::get('DB_USER', '?') . '@' . Env::get('DB_HOST', '?') . ':' . Env::get('DB_PORT', '3306'));
} catch (\Throwable $e) {
    periksa('Koneksi MySQL', false, $e->getMessage());
}

if ($dbOk) {
    $wajibTabel = [
        'users', 'otp_codes', 'links', 'products', 'transactions', 'logs',
        'game_history', 'settings', 'changelogs', 'announcements', 'tickets',
        'ticket_replies', 'feedback', 'weekly_rewards', 'achievements',
        'user_achievements', 'api_tokens', 'login_tokens', 'notifications',
    ];
    $ada = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
    $kurang = array_diff($wajibTabel, $ada);
    periksa(
        'Skema tabel lengkap (' . count($wajibTabel) . ' tabel)',
        $kurang === [],
        $kurang === [] ? count($ada) . ' tabel ditemukan' : 'KURANG: ' . implode(', ', $kurang) . ' → import database/schema.sql'
    );
    $owner = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
    periksa('Akun owner ada', (int) $owner >= 1, (int) $owner . ' owner → import database/seed.sql bila 0');
}

// ---------------------------------------------------------------
info("\n▶ Ringkasan");
// ---------------------------------------------------------------
if ($gagal === 0) {
    info("  ✨ {$total}/{$total} pemeriksaan LOLOS — ChiperX siap tempur!\n");
    exit(0);
}
info("  ⚠️  " . ($total - $gagal) . "/{$total} lolos, {$gagal} butuh perhatian (lihat tanda [GAGAL] di atas).\n");
exit(1);
