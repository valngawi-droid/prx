<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Feed Komunitas — postingan teks/foto + suka + komentar (ala Instagram/Facebook).
 */
final class Post
{
    /** Feed terbaru (bergabung info penulis + flag apakah $viewerId menyukai). Tersembunyi: arsip milik ORANG LAIN. */
    public static function feed(int $limit = 20, int $viewerId = 0): array
    {
        return Database::all(
            'SELECT p.id, p.user_id, p.body, p.image, p.likes_count, p.comments_count, p.created_at, p.is_archived,
                    u.name, u.username, u.role, u.badges, u.avatar,
                    EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) AS liked,
                    EXISTS(SELECT 1 FROM bookmarks bm WHERE bm.post_id = p.id AND bm.user_id = ?) AS saved
             FROM posts p JOIN users u ON u.id = p.user_id
             WHERE p.is_archived = 0 OR p.user_id = ?
             ORDER BY p.id DESC LIMIT ' . max(1, $limit),
            [$viewerId, $viewerId, $viewerId]
        );
    }

    /** Postingan milik satu user (untuk profil publik) — tanpa yang diarsipkan. */
    public static function forUser(int $userId, int $limit = 9, bool $includeArchived = false): array
    {
        $sql = 'SELECT p.id, p.body, p.image, p.likes_count, p.comments_count, p.created_at, p.is_archived
             FROM posts p WHERE p.user_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND p.is_archived = 0';
        }
        $sql .= ' ORDER BY p.id DESC LIMIT ' . max(1, $limit);
        return Database::all($sql, [$userId]);
    }

    /** 🧭 Jelajah (Explore): grid semua postingan bergambar terbaru. */
    public static function explore(int $limit = 60): array
    {
        return Database::all(
            'SELECT p.id, p.body, p.image, p.likes_count, p.comments_count, p.created_at,
                    u.name, u.username, u.avatar
             FROM posts p JOIN users u ON u.id = p.user_id
             WHERE p.image IS NOT NULL AND p.image != "" AND p.is_archived = 0
             ORDER BY p.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** 📥 Toggle arsip (milik sendiri) → true bila SEKARANG terarsip. */
    public static function toggleArchive(int $id, int $userId): bool
    {
        $cur = Database::value('SELECT is_archived FROM posts WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($cur === null) {
            return false;
        }
        Database::run('UPDATE posts SET is_archived = 1 - is_archived WHERE id = ? AND user_id = ?', [$id, $userId]);
        return (int) Database::value('SELECT is_archived FROM posts WHERE id = ?', [$id]) === 1;
    }

    /** ❤️ Daftar penyuka postingan (nama & username, maks 50). */
    public static function likers(int $postId, int $limit = 50): array
    {
        return Database::all(
            'SELECT u.name, u.username, u.avatar, u.role, u.badges
             FROM post_likes pl JOIN users u ON u.id = pl.user_id
             WHERE pl.post_id = ? ORDER BY pl.created_at DESC LIMIT ' . max(1, $limit),
            [$postId]
        );
    }

    public static function create(int $userId, string $body, ?string $image = null): void
    {
        Database::run(
            'INSERT INTO posts (user_id, body, image) VALUES (?, ?, ?)',
            [$userId, mb_substr(trim($body), 0, 500), $image]
        );
    }

    /** Waktu posting terakhir user (throttle anti-spam). */
    public static function lastPostedAt(int $userId): ?string
    {
        $v = Database::value('SELECT created_at FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        return $v !== null ? (string) $v : null;
    }

    /**
     * Toggle suka atomik. @return array{liked:bool,count:int}
     */
    public static function toggleLike(int $postId, int $userId): array
    {
        return Database::transaction(static function () use ($postId, $userId): array {
            Database::run('SELECT id FROM posts WHERE id = ? FOR UPDATE', [$postId]);
            $has = Database::value('SELECT COUNT(*) FROM post_likes WHERE post_id = ? AND user_id = ?', [$postId, $userId]) > 0;
            if ($has) {
                Database::run('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?', [$postId, $userId]);
                Database::run('UPDATE posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?', [$postId]);
            } else {
                Database::run('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?, ?)', [$postId, $userId]);
                Database::run('UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?', [$postId]);
            }
            $count = (int) (Database::value('SELECT likes_count FROM posts WHERE id = ?', [$postId]) ?? 0);
            return ['liked' => !$has, 'count' => $count];
        });
    }

    /** Tambah komentar atomik (naikkan counter). $parentId = balasan komentar (IG style). */
    public static function addComment(int $postId, int $userId, string $body, ?int $parentId = null): void
    {
        Database::transaction(static function () use ($postId, $userId, $body, $parentId): void {
            Database::run(
                'INSERT INTO post_comments (post_id, user_id, body, parent_id) VALUES (?, ?, ?, ?)',
                [$postId, $userId, mb_substr(trim($body), 0, 300), $parentId]
            );
            Database::run('UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?', [$postId]);
        });
    }

    /** Komentar per postingan (terlama → terbaru) + info komentar yang dibalas. */
    public static function comments(int $postId, int $limit = 30): array
    {
        return Database::all(
            'SELECT c.id, c.user_id, c.body, c.created_at, c.parent_id,
                    pu.username AS parent_username, pu.name AS parent_name,
                    u.name, u.username, u.role, u.badges, u.avatar
             FROM post_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN post_comments pc ON pc.id = c.parent_id
             LEFT JOIN users pu ON pu.id = pc.user_id
             WHERE c.post_id = ? ORDER BY c.id ASC LIMIT ' . max(1, $limit),
            [$postId]
        );
    }

    // ----------------- 🔖 BOOKMARK (simpan postingan) -----------------

    /** Toggle simpan → return true bila SEKARANG tersimpan. */
    public static function toggleBookmark(int $postId, int $userId): bool
    {
        $has = (int) (Database::value('SELECT COUNT(*) FROM bookmarks WHERE post_id = ? AND user_id = ?', [$postId, $userId]) ?? 0) > 0;
        if ($has) {
            Database::run('DELETE FROM bookmarks WHERE post_id = ? AND user_id = ?', [$postId, $userId]);
            return false;
        }
        Database::run('INSERT IGNORE INTO bookmarks (user_id, post_id) VALUES (?, ?)', [$userId, $postId]);
        return true;
    }

    /** Feed postingan yang disimpan user (untuk halaman Tersimpan). */
    public static function bookmarkFeed(int $userId, int $limit = 30): array
    {
        return Database::all(
            'SELECT p.id, p.user_id, p.body, p.image, p.likes_count, p.comments_count, p.created_at,
                    u.name, u.username, u.role, u.badges, u.avatar,
                    1 AS saved
             FROM bookmarks b
             JOIN posts p ON p.id = b.post_id
             JOIN users u ON u.id = p.user_id
             WHERE b.user_id = ?
             ORDER BY b.id DESC LIMIT ' . max(1, $limit),
            [$userId]
        );
    }

    public static function bookmarkCount(int $userId): int
    {
        return (int) (Database::value('SELECT COUNT(*) FROM bookmarks WHERE user_id = ?', [$userId]) ?? 0);
    }

    /** Ambil satu post + pemiliknya (otorisasi like/komentar/hapus). */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT id, user_id FROM posts WHERE id = ?', [$id]);
    }

    /** Hapus postingan + seluruh suka & komentarnya (atomik). */
    public static function delete(int $id): void
    {
        Database::transaction(static function () use ($id): void {
            Database::run('DELETE FROM post_likes WHERE post_id = ?', [$id]);
            Database::run('DELETE FROM post_comments WHERE post_id = ?', [$id]);
            Database::run('DELETE FROM posts WHERE id = ?', [$id]);
        });
    }

    /** Hapus satu komentar + turunkan counter (atomik). */
    public static function deleteComment(int $commentId): void
    {
        Database::transaction(static function () use ($commentId): void {
            $row = Database::one('SELECT id, post_id FROM post_comments WHERE id = ?', [$commentId]);
            if (!$row) {
                return;
            }
            Database::run('DELETE FROM post_comments WHERE id = ?', [$commentId]);
            Database::run('UPDATE posts SET comments_count = GREATEST(0, comments_count - 1) WHERE id = ?', [(int) $row['post_id']]);
        });
    }

    public static function findComment(int $id): ?array
    {
        return Database::one(
            'SELECT c.id, c.user_id, c.post_id, p.user_id AS post_owner FROM post_comments c JOIN posts p ON p.id = c.post_id WHERE c.id = ?',
            [$id]
        );
    }

    public static function countByUser(int $userId): int
    {
        return (int) (Database::value('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId]) ?? 0);
    }
}
