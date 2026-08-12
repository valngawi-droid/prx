<?php

declare(strict_types=1);

/**
 * ============================================================
 * ChiperX — Front Controller (satu pintu masuk semua request)
 * ============================================================
 */
define('BASE_PATH', dirname(__DIR__));

// PHP built-in server: layani file statis langsung
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

// Autoloader: composer jika ada, fallback PSR-4 manual
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
    // helpers.php wajib dimuat manual bila composer belum diinstall
    require BASE_PATH . '/src/Core/helpers.php';
}

use ChiperX\Core\Config;
use ChiperX\Core\Env;
use ChiperX\Core\Request;
use ChiperX\Core\Router;
use ChiperX\Core\Session;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\DiscordWebhook;

Env::load(BASE_PATH . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Jakarta'));

// ── Strategi error: peringatan TIDAK PERNAH dicetak langsung ke browser. ──
// Notice/warning/deprecated (mis. curl_close() di PHP 8.5) yang tercetak akan
// merusak header redirect ("headers already sent") → bug halaman putih.
// Semuanya dicatat ke storage/logs/php.log; detail tetap tampil via handler.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Buffer SELURUH output sejak awal: pelindung terakhir bagi header redirect.
ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // Hormati operator @ (error suppression)
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $dir = BASE_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $label = match (true) {
        (bool) ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) => 'DEPRECATED',
        (bool) ($severity & (E_WARNING | E_USER_WARNING))       => 'WARNING',
        (bool) ($severity & (E_NOTICE | E_USER_NOTICE))         => 'NOTICE',
        default                                                  => 'ERROR',
    };
    @file_put_contents(
        $dir . '/php.log',
        '[' . date('Y-m-d H:i:s') . "] {$label}: {$message} @ {$file}:{$line}\n",
        FILE_APPEND | LOCK_EX
    );
    return true; // sudah tertangani — jangan biarkan PHP mencetaknya
});

// Fatal error (E_ERROR/E_PARSE/dst.) tidak melalui set_error_handler —
// tangkap saat shutdown agar detailnya tetap tampil dalam mode debug.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        return;
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (Config::isDebug()) {
        echo '<pre style="background:#0f172a;color:#f87171;padding:24px;font-size:13px;white-space:pre-wrap;">FATAL: '
            . htmlspecialchars($err['message'] . "\n@ " . $err['file'] . ':' . $err['line'], ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<!DOCTYPE html><html lang="id"><body style="background:#0f172a;color:#e2e8f0;font-family:sans-serif;display:grid;place-items:center;height:100vh;">'
            . '<div style="text-align:center"><h1 style="color:#a78bfa;">500 — Terjadi Kesalahan</h1><p>Tim ChiperX telah diberi tahu. Coba beberapa saat lagi.</p>'
            . '<a href="/" style="color:#22d3ee;">← Kembali ke Beranda</a></div></body></html>';
    }
});

Session::start();

// Global error handler: produksi → halaman ramah + log
set_exception_handler(static function (\Throwable $e): void {
    try {
        AuditLogger::record('system.exception', ['error' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()], 'critical');
        if (str_contains($e->getMessage(), 'PHPMailer') === false) {
            DiscordWebhook::logError('Exception tidak tertangkap', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    } catch (\Throwable) {
    }
    http_response_code(500);
    // Bersihkan buffer partial-render agar halaman error tampil utuh (tidak menempel di konten setengah jadi)
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    if (Config::isDebug()) {
        echo '<pre style="background:#0f172a;color:#f87171;padding:24px;">' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        echo '<!DOCTYPE html><html lang="id"><body style="background:#0f172a;color:#e2e8f0;font-family:sans-serif;display:grid;place-items:center;height:100vh;">'
            . '<div style="text-align:center"><h1 style="color:#a78bfa;">500 — Terjadi Kesalahan</h1><p>Tim ChiperX telah diberi tahu. Coba beberapa saat lagi.</p>'
            . '<a href="/" style="color:#22d3ee;">← Kembali ke Beranda</a></div></body></html>';
    }
});

$request = Request::capture();

// ── 🛡️ Security headers (anti-sniff, anti-clickjacking, dsb.) ──
$__host = (string) ($_SERVER['HTTP_HOST'] ?? '');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 0'); // legacy auditor bisa salah-blokir halaman sah
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (!str_ends_with($__host, '.e2b.app')) { // jangan rusak preview iframe sandbox
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self'");
}
unset($__host);

// ── 🔄 AUTO-MIGRASI DB: file database/migrations/*.sql yang belum terpasang
// diterapkan otomatis (dicatat di tabel `migrations`, aman diulang).
// Jadi update kode TIDAK PERNAH LAGI lupa migrasi → bye "table doesn't exist"!
try {
    $___migrasiBaru = \ChiperX\Core\Migrator::sync();
    if ($___migrasiBaru !== []) {
        try {
            AuditLogger::record('system.migrate', ['applied' => $___migrasiBaru], 'info');
            DiscordWebhook::send('🔄 Auto-migrasi database', 'Migrasi baru diterapkan: `' . implode('`, `', $___migrasiBaru) . '`', DiscordWebhook::COLOR_INFO);
        } catch (\Throwable) {
        }
    }
    unset($___migrasiBaru);
} catch (\Throwable $___me) {
    // DB belum siap? Jangan bunuh request — catat saja; request tetap dilayani.
    @file_put_contents(
        BASE_PATH . '/storage/logs/php.log',
        '[' . date('Y-m-d H:i:s') . '] MIGRATE-GAGAL: ' . $___me->getMessage() . "\n",
        FILE_APPEND | LOCK_EX
    );
    unset($___me);
}

// ── 🧯 FIREWALL: blokir IP terlarang & tangkap deface/hack ──
\ChiperX\Services\Firewall::guard();

$router  = new Router();
(require BASE_PATH . '/src/routes/web.php')($router);
$router->dispatch($request);
