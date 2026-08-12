<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Config;

/**
 * Pengirim log ke Discord Webhook dengan format Rich Embed.
 * Reusable: panggil DiscordWebhook::send(...) dari mana saja.
 * Fire-and-forget: kegagalan kirim TIDAK menghentikan alur aplikasi.
 */
final class DiscordWebhook
{
    // Palet warna embed (decimal)
    public const COLOR_SUCCESS = 0x22C55E; // hijau
    public const COLOR_INFO    = 0x3B82F6; // biru
    public const COLOR_PURPLE  = 0x8B5CF6; // ungu neon
    public const COLOR_WARNING = 0xF59E0B; // kuning
    public const COLOR_DANGER  = 0xEF4444; // merah
    public const COLOR_CYAN    = 0x22D3EE; // cyan

    /**
     * Kirim Rich Embed ke Discord.
     *
     * @param array<int, array{name:string, value:string, inline?:bool}> $fields
     */
    public static function send(string $title, string $description = '', int $color = self::COLOR_INFO, array $fields = []): bool
    {
        try {
            return self::deliver($title, $description, $color, $fields);
        } catch (\Throwable) {
            // Webhook murni pelengkap — TIDAK PERNAH boleh mematahkan alur aplikasi
            // (mis. ekstensi curl absen di build PHP minimal seperti Termux).
            return false;
        }
    }

    /**
     * @param array<int, array{name:string, value:string, inline?:bool}> $fields
     */
    private static function deliver(string $title, string $description, int $color, array $fields): bool
    {
        if (!Config::discordEnabled() || !function_exists('curl_init')) {
            return false;
        }
        $payload = [
            'username'   => Config::appName() . ' Logger',
            'avatar_url' => Config::appUrl() . '/assets/img/logo.png',
            'embeds'     => [[
                'title'       => mb_substr($title, 0, 256),
                'description' => mb_substr($description, 0, 4000),
                'color'       => $color,
                'fields'      => array_map(static function (array $f): array {
                    return [
                        'name'   => mb_substr((string) $f['name'], 0, 256),
                        'value'  => mb_substr((string) $f['value'], 0, 1024),
                        'inline' => (bool) ($f['inline'] ?? false),
                    ];
                }, $fields),
                'footer'      => ['text' => 'ChiperX Audit Log • ' . Config::appUrl()],
                'timestamp'   => gmdate('c'),
            ]],
        ];

        $ch = curl_init(Config::discordWebhook());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return $code >= 200 && $code < 300 && $body !== false;
    }

    // ------------------------------------------------------------------
    // Event siap pakai
    // ------------------------------------------------------------------

    public static function logLogin(string $email, string $ip, bool $success): void
    {
        self::send(
            $success ? '🔐 Login Berhasil' : '🚨 Login Gagal / OTP Salah',
            $success ? 'Pengguna berhasil masuk ke sistem.' : 'Percobaan login gagal terdeteksi.',
            $success ? self::COLOR_SUCCESS : self::COLOR_DANGER,
            [
                ['name' => 'Email', 'value' => $email, 'inline' => true],
                ['name' => 'IP', 'value' => $ip, 'inline' => true],
            ]
        );
    }

    public static function logRegister(string $email, string $ip): void
    {
        self::send('✨ Registrasi Akun Baru', 'Akun baru dibuat otomatis melalui login OTP perdana.', self::COLOR_PURPLE, [
            ['name' => 'Email', 'value' => $email, 'inline' => true],
            ['name' => 'IP', 'value' => $ip, 'inline' => true],
        ]);
    }

    public static function logPurchase(string $email, string $product, int $amount, string $method, string $ref): void
    {
        self::send('💰 Pembelian Berhasil', 'Transaksi dikonfirmasi PAID oleh payment gateway.', self::COLOR_SUCCESS, [
            ['name' => 'Pembeli', 'value' => $email, 'inline' => true],
            ['name' => 'Produk', 'value' => $product, 'inline' => true],
            ['name' => 'Nominal', 'value' => 'Rp ' . number_format($amount, 0, ',', '.'), 'inline' => true],
            ['name' => 'Metode', 'value' => strtoupper($method), 'inline' => true],
            ['name' => 'Referensi', 'value' => $ref, 'inline' => true],
        ]);
    }

    public static function logFileChange(string $email, string $action, string $path): void
    {
        self::send('🗂️ Perubahan File oleh Owner', "Aksi: **{$action}**", self::COLOR_WARNING, [
            ['name' => 'Owner', 'value' => $email, 'inline' => true],
            ['name' => 'Path', 'value' => '`' . $path . '`', 'inline' => false],
        ]);
    }

    public static function logError(string $context, string $message): void
    {
        self::send('⚠️ Error Sistem', $context, self::COLOR_DANGER, [
            ['name' => 'Pesan', 'value' => mb_substr($message, 0, 900)],
        ]);
    }
}
