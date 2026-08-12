<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Session aman: cookie httponly + SameSite, regenerasi ID saat login,
 * fingerprint User-Agent untuk mitigasi session hijacking.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        // Umur sesi: default 8 jam (28800 dtk). Default PHP hanya 24 menit —
        // itulah biang "419 Sesi kedaluwarsa" saat user jeda lama sebelum submit.
        $lifetime = max(300, Env::int('SESSION_LIFETIME', 28800));

        // Pastikan session BENAR-BENAR tersimpan: di sebagian lingkungan
        // (mis. build PHP minimal/Android) save_path default tak writable →
        // setiap request dapat session KOSONG baru → CSRF 419 terus-menerus.
        $savePath = (string) ini_get('session.save_path');
        if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
            $fallback = BASE_PATH . '/storage/sessions';
            if (!is_dir($fallback)) {
                @mkdir($fallback, 0700, true);
            }
            if (is_dir($fallback) && is_writable($fallback)) {
                session_save_path($fallback);
            }
        }

        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_lifetime', (string) $lifetime);
        session_name(Env::get('SESSION_NAME', 'CHIPERX_SESSION'));
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $agent = substr(hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 32);
        // Fingerprint UA: CATAT perubahan, JANGAN PERNAH menghancurkan sesi.
        // UA mobile bisa berubah antar-tab (toggle "Situs desktop", prefetch
        // Chrome, dsb.) — mengosongkan $_SESSION di sini = bug 419 berulang.
        // (Session-fixation tetap ditangkal session_regenerate_id saat login.)
        if (isset($_SESSION['_fp']) && $_SESSION['_fp'] !== $agent) {
            @file_put_contents(
                BASE_PATH . '/storage/logs/php.log',
                '[' . date('Y-m-d H:i:s') . '] SESSION-FP: User-Agent berubah untuk sesi '
                    . session_id() . ' — dicatat, sesi TIDAK dikosongkan.' . "\n",
                FILE_APPEND | LOCK_EX
            );
        }
        $_SESSION['_fp'] = $agent;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type:string, message:string}> */
    public static function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}
