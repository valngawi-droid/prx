<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Shoutbox global — chat ringan semua member di dashboard.
 */
final class Shout
{
    public static function add(int $userId, string $message): void
    {
        Database::run('INSERT INTO shouts (user_id, message) VALUES (?, ?)', [$userId, mb_substr($message, 0, 190)]);
    }

    /** @return array<int, array<string, mixed>> — N pesan TERBARU, urut lama→baru. */
    public static function latest(int $limit = 25): array
    {
        return Database::all(
            'SELECT * FROM (
                SELECT s.id, s.user_id, s.message, s.created_at, u.name, u.role, u.is_verified
                FROM shouts s JOIN users u ON u.id = s.user_id
                ORDER BY s.id DESC LIMIT ' . max(1, min(50, $limit)) . '
            ) t ORDER BY t.id ASC'
        );
    }

    public static function lastPostedAt(int $userId): ?string
    {
        $v = Database::value('SELECT created_at FROM shouts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        return $v !== null ? (string) $v : null;
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM shouts WHERE id = ?', [$id]);
    }

    /** Sisakan 200 pesan terbaru agar tabel tetap ramping. */
    public static function prune(): void
    {
        Database::run('DELETE FROM shouts WHERE id NOT IN (SELECT id FROM (SELECT id FROM shouts ORDER BY id DESC LIMIT 200) t)');
    }
}
