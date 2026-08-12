<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Token magic-link login: 32 byte acak → yang disimpan hanya hash sha256.
 * Sekali pakai, TTL 10 menit (WIB), konsumsi atomik (anti replay/race).
 */
final class LoginToken
{
    public const TTL_MINUTES = 10;

    /** Buat token baru; mengembalikan token MENTAH untuk dimasukkan ke URL email. */
    public static function create(int $userId, string $ip): string
    {
        // Matikan token lama yang belum dipakai
        Database::run('UPDATE login_tokens SET consumed_at = NOW() WHERE user_id = ? AND consumed_at IS NULL', [$userId]);

        $plain = bin2hex(random_bytes(32)); // 64 hex char
        Database::run(
            'INSERT INTO login_tokens (user_id, token_hash, expires_at, ip_address)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)',
            [$userId, hash('sha256', $plain), self::TTL_MINUTES, $ip]
        );
        return $plain;
    }

    /**
     * Verifikasi + konsumsi atomik. Return user_id bila valid, null bila
     * tidak ada / kedaluwarsa / sudah dipakai.
     */
    public static function consume(string $plainToken): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            return null;
        }
        $hash = hash('sha256', $plainToken);
        return Database::transaction(function () use ($hash) {
            $row = Database::one(
                'SELECT id, user_id FROM login_tokens
                  WHERE token_hash = ? AND consumed_at IS NULL AND expires_at > NOW()
                  LIMIT 1 FOR UPDATE',
                [$hash]
            );
            if (!$row) {
                return null;
            }
            Database::run('UPDATE login_tokens SET consumed_at = NOW() WHERE id = ?', [(int) $row['id']]);
            return (int) $row['user_id'];
        });
    }
}
