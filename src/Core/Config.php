<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Konfigurasi aplikasi terpusat.
 * secret(): DB (halaman Owner > Integrasi, prefix `cfg_`) MENIMPA .env —
 * sehingga semua API key bisa diedit dari web tanpa SSH.
 */
final class Config
{
    /** Nilai konfigurasi rahasia: settings DB (cfg_KEY) > .env. */
    public static function secret(string $key, ?string $default = null): ?string
    {
        try {
            $override = \ChiperX\Models\Setting::get('cfg_' . $key);
        } catch (\Throwable) {
            $override = null; // DB belum siap (boot/awal) → pakai .env
        }
        return ($override !== null && $override !== '') ? $override : Env::get($key, $default);
    }

    public static function secretBool(string $key, bool $default = false): bool
    {
        $v = self::secret($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
    public static function appUrl(): string
    {
        $url = rtrim((string) self::secret('APP_URL', ''), '/');

        // Bila APP_URL belum di set / masih localhost, tetapi request datang lewat
        // domain publik (mis. Cloudflare Tunnel app.chiperx.cyou) → pakai host
        // request. Ini memastikan magic link email & link referral SELALU publik,
        // tanpa perlu menyunting .env. Host divalidasi ketat (anti Host-header abuse).
        $host    = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $isLocal = $url === '' || (bool) preg_match('~^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])(:\d+)?$~i', $url);
        if ($isLocal && $host !== ''
            && !preg_match('~^(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])(:\d+)?$~i', $host)
            && preg_match('~^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}(:\d+)?$~iD', $host)) {
            return 'https://' . strtolower($host);
        }

        return $url !== '' ? $url : 'http://localhost:8000';
    }

    public static function appName(): string
    {
        return (string) Env::get('APP_NAME', 'ChiperX');
    }

    public static function isDebug(): bool
    {
        return Env::bool('APP_DEBUG', false);
    }

    /** @return array<string, mixed> */
    public static function database(): array
    {
        return [
            'host'    => Env::get('DB_HOST', '127.0.0.1'),
            'port'    => (int) Env::get('DB_PORT', '3306'),
            'name'    => Env::get('DB_NAME', 'chiperx'),
            'user'    => Env::get('DB_USER', 'root'),
            'pass'    => Env::get('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ];
    }

    /** @return array<string, mixed> */
    public static function smtp(): array
    {
        $port = (int) trim((string) self::secret('SMTP_PORT', '587'));
        $enc  = strtolower(trim((string) self::secret('SMTP_ENCRYPTION', 'auto')));
        // 'auto' (atau nilai tak dikenal) → tentukan dari port: 465=SSL, lainnya STARTTLS.
        if (!in_array($enc, ['tls', 'ssl', 'none'], true)) {
            $enc = $port === 465 ? 'ssl' : 'tls';
        }
        return [
            'host'       => trim((string) self::secret('SMTP_HOST', 'smtp-relay.brevo.com')),
            'port'       => $port,
            'encryption' => $enc,
            // Trim agresif: paste dari HP sering membawa spasi/enter tak terlihat,
            // dan App Password Gmail ditampilkan berformat "abcd efgh ijkl mnop".
            'username'   => trim((string) self::secret('SMTP_USERNAME', '')),
            'password'   => (string) preg_replace('/\s+/', '', (string) self::secret('SMTP_PASSWORD', '')),
            'from'       => trim((string) self::secret('MAIL_FROM_ADDRESS', 'no-reply@chiperx.local')),
            'from_name'  => trim((string) self::secret('MAIL_FROM_NAME', 'ChiperX Official')),
        ];
    }

    /** @return array<string, string> */
    public static function kirimEmail(): array
    {
        return [
            'api_key'    => trim((string) self::secret('KIRIMEMAIL_API_KEY', '')),
            'api_secret' => trim((string) self::secret('KIRIMEMAIL_API_SECRET', '')),
            'domain'     => trim((string) self::secret('KIRIMEMAIL_DOMAIN', '')),
            'api_url'    => rtrim(trim((string) self::secret('KIRIMEMAIL_API_URL', 'https://smtp-app.kirim.email/api/v4/transactional/message')), '/'),
        ];
    }

    public static function discordWebhook(): string
    {
        return (string) self::secret('DISCORD_WEBHOOK_URL', '');
    }

    public static function discordEnabled(): bool
    {
        return self::secretBool('DISCORD_ENABLED', true) && self::discordWebhook() !== '';
    }

    public static function fileManagerRoot(): string
    {
        $root = (string) Env::get('FILEMANAGER_ROOT', '');
        if ($root === '') {
            $root = BASE_PATH; // default: seluruh direktori proyek
        }
        return rtrim($root, '/');
    }

    public static function fileManagerMaxEditBytes(): int
    {
        return ((int) Env::get('FILEMANAGER_MAX_EDIT_KB', '2048')) * 1024;
    }
}
