<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Stories 24 jam — ala WhatsApp Status / Instagram Story.
 * Foto wajib, caption opsional; otomatis hilang setelah 24 jam.
 */
final class Story
{
    /** Bar story di atas feed: story TERBARU tiap user (<24 jam). */
    public static function activeBar(int $limit = 12): array
    {
        return Database::all(
            'SELECT s.id, s.image, s.created_at,
                    u.id AS user_id, u.name, u.username, u.role, u.badges
             FROM stories s
             JOIN users u ON u.id = s.user_id
             WHERE s.created_at > NOW() - INTERVAL 1 DAY
               AND s.id = (
                    SELECT s2.id FROM stories s2
                    WHERE s2.user_id = s.user_id AND s2.created_at > NOW() - INTERVAL 1 DAY
                    ORDER BY s2.id DESC LIMIT 1
               )
             ORDER BY s.id DESC
             LIMIT ' . max(1, $limit)
        );
    }

    /** Semua story aktif seorang user (urut lama → baru, untuk viewer). */
    public static function byUser(int $userId): array
    {
        return Database::all(
            'SELECT id, image, caption, created_at FROM stories
             WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY
             ORDER BY id ASC',
            [$userId]
        );
    }

    /** Jumlah story aktif (ring penanda "ada story baru"). */
    public static function activeCount(int $userId): int
    {
        return (int) (Database::value(
            'SELECT COUNT(*) FROM stories WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 DAY',
            [$userId]
        ) ?? 0);
    }

    public static function create(int $userId, string $image, ?string $caption): void
    {
        Database::run(
            'INSERT INTO stories (user_id, image, caption) VALUES (?, ?, ?)',
            [$userId, $image, $caption !== null && trim($caption) !== '' ? mb_substr(trim($caption), 0, 120) : null]
        );
    }

    /** Throttle: story terakhir user kapan. */
    public static function lastPostedAt(int $userId): ?string
    {
        $v = Database::value('SELECT created_at FROM stories WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        return $v !== null ? (string) $v : null;
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT id, user_id, image FROM stories WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM stories WHERE id = ?', [$id]);
    }

    // ---------------- 👀 STORY VIEWS (gaya Instagram) ----------------

    /** Catat view (sekali per user per story). */
    public static function recordView(int $storyId, int $userId): void
    {
        try {
            Database::run('INSERT IGNORE INTO story_views (story_id, user_id) VALUES (?, ?)', [$storyId, $userId]);
        } catch (\Throwable) {
        }
    }

    /** Daftar penonton sebuah story (pemilik saja yang melihat ini di UI). */
    public static function viewers(int $storyId, int $limit = 50): array
    {
        try {
            return Database::all(
                'SELECT v.user_id, v.viewed_at, u.name, u.username, u.avatar
                 FROM story_views v JOIN users u ON u.id = v.user_id
                 WHERE v.story_id = ? ORDER BY v.viewed_at DESC LIMIT ' . max(1, $limit),
                [$storyId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function viewCount(int $storyId): int
    {
        try {
            return (int) (Database::value('SELECT COUNT(*) FROM story_views WHERE story_id = ?', [$storyId]) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Bersihkan story kedaluwarsa (>2 hari) — dipanggil sesekali. */
    public static function prune(): void
    {
        Database::run('DELETE FROM stories WHERE created_at < NOW() - INTERVAL 2 DAY');
    }
}
