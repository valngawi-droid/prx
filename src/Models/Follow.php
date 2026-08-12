<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Ikuti / Pengikut — ala Instagram & Facebook.
 */
final class Follow
{
    /**
     * Toggle ikuti/batal — atomik.
     * @return array{following:bool}
     */
    public static function toggle(int $followerId, int $followedId): array
    {
        return Database::transaction(static function () use ($followerId, $followedId): array {
            $has = (int) (Database::value('SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = ?', [$followerId, $followedId]) ?? 0) > 0;
            if ($has) {
                Database::run('DELETE FROM follows WHERE follower_id = ? AND followed_id = ?', [$followerId, $followedId]);
            } else {
                Database::run('INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (?, ?)', [$followerId, $followedId]);
            }
            return ['following' => !$has];
        });
    }

    public static function isFollowing(int $followerId, int $followedId): bool
    {
        try {
            return (int) (Database::value('SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = ?', [$followerId, $followedId]) ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function followersCount(int $userId): int
    {
        try {
            return (int) (Database::value('SELECT COUNT(*) FROM follows WHERE followed_id = ?', [$userId]) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function followingCount(int $userId): int
    {
        try {
            return (int) (Database::value('SELECT COUNT(*) FROM follows WHERE follower_id = ?', [$userId]) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
