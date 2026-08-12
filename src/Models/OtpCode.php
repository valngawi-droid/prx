<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * OTP disimpan sebagai HASH (Argon2id, fallback BCrypt) — kode mentah
 * tidak pernah disimpan; password_verify kompatibel silang algoritma.
 */
final class OtpCode
{
    /**
     * Pilih algoritma hash terkuat yang BENAR-BENAR tersedia di build PHP
     * ini. Sebagian paket PHP (mis. Termux/Android) dikompilasi TANPA
     * dukungan Argon2 — password_hash(ARGON2ID) di sana melempar ValueError.
     */
    private static function hashAlgo(): string
    {
        if (defined('PASSWORD_ARGON2ID') && in_array(constant('PASSWORD_ARGON2ID'), password_algos(), true)) {
            return constant('PASSWORD_ARGON2ID');
        }
        return PASSWORD_BCRYPT; // selalu tersedia; tetap slow-hash + salt acak
    }

    public static function create(string $email, string $plainOtp, int $ttlSeconds, string $ip): int
    {
        // Matikan OTP lama yang belum dipakai
        Database::run('UPDATE otp_codes SET consumed_at = NOW() WHERE email = ? AND consumed_at IS NULL', [$email]);

        $hash = password_hash($plainOtp, self::hashAlgo());
        Database::run(
            'INSERT INTO otp_codes (email, otp_hash, expires_at, ip_address) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)',
            [$email, $hash, $ttlSeconds, $ip]
        );
        return Database::lastInsertId();
    }

    /** @return array<string, mixed>|null OTP aktif terakhir untuk email. */
    public static function latestActive(string $email): ?array
    {
        return Database::one(
            'SELECT * FROM otp_codes WHERE email = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$email]
        );
    }

    public static function incrementAttempts(int $id): void
    {
        Database::run('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$id]);
    }

    public static function consume(int $id): void
    {
        Database::run('UPDATE otp_codes SET consumed_at = NOW() WHERE id = ?', [$id]);
    }

    /** Detik tersisa sebelum boleh kirim ulang (cooldown 60 detik). */
    public static function resendCooldown(string $email, int $cooldownSeconds = 60): int
    {
        $last = Database::value(
            'SELECT UNIX_TIMESTAMP(created_at) FROM otp_codes WHERE email = ? ORDER BY id DESC LIMIT 1',
            [$email]
        );
        if (!$last) {
            return 0;
        }
        $elapsed = time() - (int) $last;
        return max(0, $cooldownSeconds - $elapsed);
    }

    /** Batasi pengiriman: maks N OTP per email per jam. */
    public static function sendsThisHour(string $email): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM otp_codes WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            [$email]
        );
    }

    /** Bersihkan OTP kedaluwarsa (dipanggil berkala). */
    public static function purgeExpired(): void
    {
        Database::run('DELETE FROM otp_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}
