<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Feed Komunitas — postingan teks/foto + suka + komentar (ala Instagram/Facebook).
 */
final class Post
{
    /** Feed terbaru (bergabung info penulis + flag apakah $viewerId menyukai). */
    public static function feed(int $limit = 20, int $viewerId = 0): array
    {
        return Database::all(
            'SELECT p.id, p.user_id, p.body, p.image, p.likes_count, p.comments_count, p.created_at,
                    u.name, u.username, u.role, u.badges,
                    EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) AS liked
             FROM posts p JOIN users u ON u.id = p.user_id
             ORDER BY p.id DESC LIMIT ' . max(1, $limit),
            [$viewerId]
        );
    }

    /** Postingan milik satu user (untuk profil publik). */
    public static function forUser(int $userId, int $limit = 9): array
    {
        return Database::all(
            'SELECT p.id, p.body, p.image, p.likes_count, p.comments_count, p.created_at
             FROM posts p WHERE p.user_id = ? ORDER BY p.id DESC LIMIT ' . max(1, $limit),
            [$userId]
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

    /** Tambah komentar atomik (naikkan counter). */
    public static function addComment(int $postId, int $userId, string $body): void
    {
        Database::transaction(static function () use ($postId, $userId, $body): void {
            Database::run(
                'INSERT INTO post_comments (post_id, user_id, body) VALUES (?, ?, ?)',
                [$postId, $userId, mb_substr(trim($body), 0, 300)]
            );
            Database::run('UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?', [$postId]);
        });
    }

    /** Komentar per postingan (terlama → terbaru). */
    public static function comments(int $postId, int $limit = 30): array
    {
        return Database::all(
            'SELECT c.id, c.user_id, c.body, c.created_at, u.name, u.username, u.role, u.badges
             FROM post_comments c JOIN users u ON u.id = c.user_id
             WHERE c.post_id = ? ORDER BY c.id ASC LIMIT ' . max(1, $limit),
            [$postId]
        );
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
