<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Proteksi CSRF — token HMAC stateless.
 *
 * Token = HMAC-SHA256(secret, session_id). Keunggulan besar di lingkungan
 * rapuh (Termux/shared hosting): token TETAP VALID meski isi file sesi
 * hilang/direset di tengah jalan — yang dibutuhkan hanyalah cookie session-id
 * yang sama. Mode lama (token acak di $_SESSION) tetap diterima agar form
 * yang sudah terlanjur dirender tidak rusak.
 *
 * Form HTML   → hidden input "_token"  (helper csrf_field())
 * Fetch/AJAX  → header "X-CSRF-TOKEN"  (meta tag csrf-token di layout)
 */
final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }
        $sid = session_id() !== '' ? session_id() : 'tanpa-sesi';
        return substr(hash_hmac('sha256', 'csrf|' . $sid, self::secret()), 0, 48);
    }

    public static function validate(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        // Mode utama: HMAC(session_id) — kebal kehilangan isi sesi (419 berulang).
        if (hash_equals(self::token(), $token)) {
            return true;
        }
        // Mode lawas (kompatibel mundur): token acak yang disimpan di sesi.
        return !empty($_SESSION['_csrf'])
            && hash_equals((string) $_SESSION['_csrf'], $token);
    }

    /**
     * Rahasia penandatangan: APP_KEY di .env, atau file otomatis
     * storage/.csrf_secret (dibuat sekali, chmod 0600, di-ignore git).
     */
    private static function secret(): string
    {
        static $secret = null;
        if ($secret !== null) {
            return $secret;
        }
        $secret = trim((string) Env::get('APP_KEY', ''));
        if ($secret === '') {
            $file = BASE_PATH . '/storage/.csrf_secret';
            if (is_file($file)) {
                $secret = trim((string) @file_get_contents($file));
            }
            if ($secret === '') {
                $secret = bin2hex(random_bytes(32));
                @file_put_contents($file, $secret, LOCK_EX);
                @chmod($file, 0600);
            }
        }
        return $secret;
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
            'csrf_mode'    => 'hmac(session_id)+legacy',
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
