<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Token API publik. Sama seperti OTP: plaintext TIDAK pernah disimpan —
 * yang tersimpan hanya SHA-256 hash-nya (one-way).
 */
final class ApiToken
{
    public const SCOPES = ['stats', 'leaderboard', 'user'];

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::hash($plain));
    }

    public static function generate(): string
    {
        return 'cx_' . bin2hex(random_bytes(24)); // 51 karakter
    }

    /** @return array{0:int, 1:string} [id, plaintext TOKEN — tampilkan SEKALI ke user] */
    public static function create(string $name, string $scopesCsv): array
    {
        $plain = self::generate();
        Database::run(
            'INSERT INTO api_tokens (name, token_hash, scopes) VALUES (?, ?, ?)',
            [mb_substr($name, 0, 80), self::hash($plain), $scopesCsv]
        );
        return [Database::lastInsertId(), $plain];
    }

    /** @return array<string, mixed>|null Token aktif (belum dicabut) untuk plaintext ini. */
    public static function findActiveByPlain(string $plain): ?array
    {
        if ($plain === '') {
            return null;
        }
        return Database::one(
            'SELECT * FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL',
            [self::hash($plain)]
        );
    }

    public static function touch(int $id): void
    {
        Database::run('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?', [$id]);
    }

    public static function revoke(int $id): void
    {
        Database::run('UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL', [$id]);
    }

    /** Daftar token untuk panel Owner (hash tidak pernah dikeluarkan). @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::all(
            'SELECT id, name, scopes, last_used_at, revoked_at, created_at FROM api_tokens ORDER BY id DESC'
        );
    }
}
