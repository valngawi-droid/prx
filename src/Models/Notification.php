<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Notifikasi per-pengguna (lonceng 🔔).
 * SEMUA metode auto-degrade (try/catch) — bila tabel notifications belum
 * di-import, fitur diam-diam nonaktif tanpa memutus alur apa pun.
 */
final class Notification
{
    public static function add(int $userId, string $title, ?string $body = null, ?string $url = null, string $type = 'info'): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            Database::run(
                'INSERT INTO notifications (user_id, type, title, body, url) VALUES (?, ?, ?, ?, ?)',
                [$userId, $type, mb_substr($title, 0, 120), $body !== null ? mb_substr($body, 0, 500) : null, $url !== null ? mb_substr($url, 0, 190) : null]
            );
        } catch (\Throwable) {
            // tabel belum ada / DB sibuk — abaikan, jangan ganggu alur utama
        }
    }

    public static function unreadCount(int $userId): int
    {
        try {
            return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function latest(int $userId, int $limit = 20): array
    {
        try {
            return Database::all(
                'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)),
                [$userId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function markAllRead(int $userId): void
    {
        try {
            Database::run('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
        } catch (\Throwable) {
        }
    }

    /** Hapus notifikasi lebih tua dari N hari (dipanggil sesekali). */
    public static function prune(int $days = 30): void
    {
        try {
            Database::run('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
        } catch (\Throwable) {
        }
    }
}
