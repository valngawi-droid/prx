<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Pesan Pribadi (DM) 1-lawan-1 — ala WhatsApp/Telegram.
 */
final class Message
{
    /**
     * Daftar percakapan $userId: lawan bicara + pesan terakhir + jumlah belum dibaca.
     * Terurut dari pesan terakhir paling baru.
     */
    public static function conversations(int $userId): array
    {
        return Database::all(
            'SELECT u.id, u.name, u.username, u.role, u.badges, u.last_activity,
                    (SELECT m.body FROM messages m
                      WHERE (m.sender_id = u.id AND m.recipient_id = :me1)
                         OR (m.sender_id = :me2 AND m.recipient_id = u.id)
                      ORDER BY m.id DESC LIMIT 1) AS last_body,
                    (SELECT m.created_at FROM messages m
                      WHERE (m.sender_id = u.id AND m.recipient_id = :me3)
                         OR (m.sender_id = :me4 AND m.recipient_id = u.id)
                      ORDER BY m.id DESC LIMIT 1) AS last_at,
                    (SELECT COUNT(*) FROM messages m
                      WHERE m.sender_id = u.id AND m.recipient_id = :me5 AND m.read_at IS NULL) AS unread
             FROM users u
             WHERE u.id IN (
                    SELECT sender_id FROM messages WHERE recipient_id = :me6
                    UNION
                    SELECT recipient_id FROM messages WHERE sender_id = :me7
             )
             ORDER BY last_at DESC',
            [
                'me1' => $userId, 'me2' => $userId, 'me3' => $userId, 'me4' => $userId,
                'me5' => $userId, 'me6' => $userId, 'me7' => $userId,
            ]
        );
    }

    /** Riwayat chat antara dua user (terlama → terbaru dibatasi N terakhir). */
    public static function thread(int $a, int $b, int $limit = 60): array
    {
        $rows = Database::all(
            'SELECT m.id, m.sender_id, m.body, m.read_at, m.created_at
             FROM messages m
             WHERE (m.sender_id = ? AND m.recipient_id = ?)
                OR (m.sender_id = ? AND m.recipient_id = ?)
             ORDER BY m.id DESC LIMIT ' . max(1, $limit),
            [$a, $b, $b, $a]
        );
        return array_reverse($rows); // kronologis untuk ditampilkan
    }

    public static function send(int $from, int $to, string $body): void
    {
        Database::run(
            'INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)',
            [$from, $to, mb_substr(trim($body), 0, 500)]
        );
    }

    /** Tandai semua pesan dari $from kepada $me sudah dibaca. */
    public static function markRead(int $me, int $from): void
    {
        Database::run(
            'UPDATE messages SET read_at = NOW() WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL',
            [$me, $from]
        );
    }

    /** Total pesan belum dibaca $userId (badge navigasi). */
    public static function unreadCount(int $userId): int
    {
        try {
            return (int) (Database::value('SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND read_at IS NULL', [$userId]) ?? 0);
        } catch (\Throwable) {
            return 0; // tabel belum dimigrasi
        }
    }

    /** Waktu pesan terakhir $from → $to (throttle + logika notifikasi). */
    public static function lastSentAt(int $from, int $to): ?string
    {
        $v = Database::value(
            'SELECT created_at FROM messages WHERE sender_id = ? AND recipient_id = ? ORDER BY id DESC LIMIT 1',
            [$from, $to]
        );
        return $v !== null ? (string) $v : null;
    }
}
