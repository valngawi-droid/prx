<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Proteksi CSRF: token per-session, divalidasi dengan hash_equals (timing-safe).
 * Form HTML   → hidden input "_token"  (helper csrf_field())
 * Fetch/AJAX  → header "X-CSRF-TOKEN"  (meta tag csrf-token di layout)
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Validasi otomatis dari body POST atau header AJAX. */
    public static function validateRequest(): bool
    {
        return self::validate($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }

    public static function abortIfInvalid(): void
    {
        if (self::validateRequest()) {
            return;
        }
        // ── Forensik: catat MENGAPA token ditolak — jawaban pasti atas 419 berulang
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        $detail = [
            'uri'          => $_SERVER['REQUEST_URI'] ?? '?',
            'session_id'   => session_id() ?: '(kosong)',
            'cookie_sesi'  => isset($_COOKIE[session_name()]) ? 'ada' : 'TIDAK-ADA',
            'csrf_sesi'    => isset($_SESSION['_csrf']) ? 'ada' : 'TIDAK-ADA',
            'token_kirim'  => (isset($_POST['_token']) || isset($_SERVER['HTTP_X_CSRF_TOKEN'])) ? 'ada' : 'TIDAK-ADA',
            'kunci_sesi'   => implode('|', array_keys($_SESSION ?? [])),
            'save_path'    => (string) ini_get('session.save_path'),
            'gc_maxlife'   => (string) ini_get('session.gc_maxlifetime'),
        ];
        @file_put_contents(
            $logDir . '/php.log',
            '[' . date('Y-m-d H:i:s') . '] CSRF-419: ' . json_encode($detail, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
        http_response_code(419);
        // Bersihkan output parsial agar halaman error tampil utuh
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        // Render halaman 419 yang ramah (standalone, tahan segala kondisi);
        // $debug diteruskan ke view — ditampilkan HANYA saat APP_DEBUG=true
        $debug = $detail;
        $view = BASE_PATH . '/src/Views/errors/419.php';
        if (is_file($view)) {
            include $view;
        } else {
            echo '419 — Sesi kedaluwarsa / token CSRF tidak valid. Muat ulang halaman.';
        }
        exit;
    }
}
