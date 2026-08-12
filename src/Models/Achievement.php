<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Achievement
{
    /** Semua achievement aktif beserta status unlock untuk user. @return array<int, array<string, mixed>> */
    public static function withUnlockStatus(int $userId): array
    {
        return Database::all(
            'SELECT a.*, ua.unlocked_at
             FROM achievements a
             LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
             WHERE a.is_active = 1
             ORDER BY a.id ASC',
            [$userId]
        );
    }

    public static function unlockedCount(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM user_achievements WHERE user_id = ?', [$userId]);
    }

    public static function totalActive(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM achievements WHERE is_active = 1');
    }
}
