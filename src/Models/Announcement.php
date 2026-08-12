<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Announcement
{
    /** @return array<int, array<string, mixed>> */
    public static function active(int $limit = 3): array
    {
        return Database::all("SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT {$limit}");
    }

    /** @return array<int, array<string, mixed>> */
    public static function allAdmin(): array
    {
        return Database::all('SELECT a.*, u.email AS author FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.id DESC');
    }

    public static function create(string $title, string $body, string $level, int $authorId): void
    {
        $level = in_array($level, ['info', 'success', 'warning'], true) ? $level : 'info';
        Database::run('INSERT INTO announcements (title, body, level, created_by) VALUES (?, ?, ?, ?)', [$title, $body, $level, $authorId]);
    }

    public static function toggle(int $id): void
    {
        Database::run('UPDATE announcements SET is_active = 1 - is_active WHERE id = ?', [$id]);
    }

    public static function deleteById(int $id): void
    {
        Database::run('DELETE FROM announcements WHERE id = ?', [$id]);
    }
}
