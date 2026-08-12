<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class GameHistory
{
    public static function add(int $userId, string $game, string $resultKey, int $reward): void
    {
        Database::run(
            'INSERT INTO game_history (user_id, game, result_key, reward) VALUES (?, ?, ?, ?)',
            [$userId, $game, $resultKey, $reward]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function forUser(int $userId, int $limit = 15): array
    {
        return Database::all(
            "SELECT * FROM game_history WHERE user_id = ? ORDER BY id DESC LIMIT {$limit}",
            [$userId]
        );
    }

    public static function totalPlays(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM game_history');
    }
}
