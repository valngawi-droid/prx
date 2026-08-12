<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Announcement
{
    /** @return array<int, array<string, mixed>> */
    public static function active(int $limit = 3): array
    {
        return Database::all("SELECT * FROM announcements WHERE is_active = 1 AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY id DESC LIMIT {$limit}");
    }

    /** Buat pengumuman (bisa dijadwalkan: $scheduledAt 'Y-m-d H:i:s' atau null). */
    public static function createScheduled(string $title, string $body, string $level, int $authorId, ?string $scheduledAt): void
    {
        $level = in_array($level, ['info', 'success', 'warning'], true) ? $level : 'info';
        Database::run(
            'INSERT INTO announcements (title, body, level, created_by, scheduled_at) VALUES (?, ?, ?, ?, ?)',
            [$title, $body, $level, $authorId, $scheduledAt]
        );
    }

    // ---------------- 😀 REAKSI pengumuman (gaya Telegram channel) ----------------

    public static function toggleReaction(int $id, int $userId, string $emoji): bool
    {
        $allowed = ['👍', '🔥', '🎉', '❤️'];
        if (!in_array($emoji, $allowed, true)) {
            return false;
        }
        $has = (int) (Database::value(
            'SELECT COUNT(*) FROM announcement_reactions WHERE announcement_id = ? AND user_id = ? AND emoji = ?',
            [$id, $userId, $emoji]
        ) ?? 0) > 0;
        if ($has) {
            Database::run('DELETE FROM announcement_reactions WHERE announcement_id = ? AND user_id = ? AND emoji = ?', [$id, $userId, $emoji]);
            return false;
        }
        Database::run('INSERT IGNORE INTO announcement_reactions (announcement_id, user_id, emoji) VALUES (?, ?, ?)', [$id, $userId, $emoji]);
        return true;
    }

    /** Reaksi per pengumuman (counter + apakah viewer menekan). */
    public static function reactions(int $id, int $viewerId): array
    {
        try {
            return Database::all(
                "SELECT emoji, COUNT(*) AS c, MAX(user_id = ?) AS mine
                 FROM announcement_reactions WHERE announcement_id = ? GROUP BY emoji ORDER BY c DESC",
                [$viewerId, $id]
            );
        } catch (\Throwable) {
            return [];
        }
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
