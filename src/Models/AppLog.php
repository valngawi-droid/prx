<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class AppLog
{
    public static function add(?int $userId, string $action, string $level = 'info', ?string $ip = null, ?string $agent = null, ?array $meta = null): void
    {
        Database::run(
            'INSERT INTO logs (user_id, action, level, ip_address, user_agent, metadata) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, mb_substr($action, 0, 120), $level, $ip, $agent, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 50, string $actionFilter = ''): array
    {
        if ($actionFilter !== '') {
            return Database::all(
                "SELECT l.*, u.email FROM logs l LEFT JOIN users u ON u.id = l.user_id
                 WHERE l.action LIKE ? ORDER BY l.id DESC LIMIT {$limit}",
                ['%' . $actionFilter . '%']
            );
        }
        return Database::all(
            "SELECT l.*, u.email FROM logs l LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT {$limit}"
        );
    }

    public static function countToday(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM logs WHERE DATE(created_at) = CURDATE()');
    }
}
