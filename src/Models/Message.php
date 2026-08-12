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
            'SELECT u.id, u.name, u.username, u.role, u.badges, u.last_activity, u.avatar,
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

    /** Riwayat chat antara dua user (terlama → terbaru dibatasi N terakhir). Menyaring pesan self-destruct yang sudah musnah. */
    public static function thread(int $a, int $b, int $limit = 60): array
    {
        $rows = Database::all(
            'SELECT m.id, m.sender_id, m.body, m.image, m.reply_to, m.deleted_at, m.pinned_at,
                    m.expire_at, m.read_at, m.created_at,
                    r.body AS reply_body, r.image AS reply_image, r.deleted_at AS reply_deleted,
                    ru.name AS reply_name
             FROM messages m
             LEFT JOIN messages r ON r.id = m.reply_to
             LEFT JOIN users ru ON ru.id = r.sender_id
             WHERE ((m.sender_id = ? AND m.recipient_id = ?)
                OR (m.sender_id = ? AND m.recipient_id = ?))
               AND (m.expire_at IS NULL OR m.expire_at > NOW())
             ORDER BY m.id DESC LIMIT ' . max(1, $limit),
            [$a, $b, $b, $a]
        );
        return array_reverse($rows); // kronologis untuk ditampilkan
    }

    /** Kirim pesan — dukung foto (bucket dm), reply kutipan, dan timer musnah (detik; null = permanen). */
    public static function send(int $from, int $to, string $body, ?string $image = null, ?int $replyTo = null, ?int $ttlSeconds = null): int
    {
        $expire = null;
        if ($ttlSeconds !== null && $ttlSeconds > 0) {
            $ttlSeconds = min(86400 * 7, max(60, $ttlSeconds));
            $expire = date('Y-m-d H:i:s', time() + $ttlSeconds);
        }
        Database::run(
            'INSERT INTO messages (sender_id, recipient_id, body, image, reply_to, expire_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$from, $to, mb_substr(trim($body), 0, 500), $image, $replyTo, $expire]
        );
        return (int) Database::value('SELECT LAST_INSERT_ID()');
    }

    /** 🗑️ Hapus untuk semua orang (soft delete — gaya WhatsApp). */
    public static function deleteForAll(int $id, int $senderId): bool
    {
        Database::run('UPDATE messages SET deleted_at = NOW() WHERE id = ? AND sender_id = ? AND deleted_at IS NULL', [$id, $senderId]);
        return Database::value('SELECT deleted_at FROM messages WHERE id = ?', [$id]) !== null;
    }

    /** 📌 Toggle pin pesan — dipasang/dilepas. */
    public static function togglePin(int $id, int $a, int $b): bool
    {
        $has = Database::value('SELECT pinned_at FROM messages WHERE id = ?', [$id]);
        if ($has !== null) {
            Database::run('UPDATE messages SET pinned_at = NULL WHERE id = ?', [$id]);
            return false;
        }
        Database::run(
            'UPDATE messages SET pinned_at = NOW() WHERE id = ? AND deleted_at IS NULL AND ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))',
            [$id, $a, $b, $b, $a]
        );
        return Database::value('SELECT pinned_at FROM messages WHERE id = ?', [$id]) !== null;
    }

    /** Semua pesan yang di-pin dalam percakapan (banner atas chat). */
    public static function pinned(int $a, int $b): array
    {
        return Database::all(
            'SELECT m.id, m.body, m.sender_id, u.name FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.pinned_at IS NOT NULL AND m.deleted_at IS NULL
               AND ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
             ORDER BY m.pinned_at DESC LIMIT 3',
            [$a, $b, $b, $a]
        );
    }

    /** 📇 Blokir: $blocker memblokir $blocked → $blocked tak bisa DM. */
    public static function isBlocked(int $senderId, int $recipientId): bool
    {
        try {
            return (int) (Database::value('SELECT COUNT(*) FROM blocks WHERE blocker_id = ? AND blocked_id = ?', [$recipientId, $senderId]) ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function setBlocked(int $blocker, int $blocked, bool $on): void
    {
        if ($on) {
            Database::run('INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)', [$blocker, $blocked]);
        } else {
            Database::run('DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?', [$blocker, $blocked]);
        }
    }

    public static function blockedByMe(int $me): array
    {
        try {
            return Database::all('SELECT b.blocked_id, u.name, u.username FROM blocks b JOIN users u ON u.id = b.blocked_id WHERE b.blocker_id = ?', [$me]);
        } catch (\Throwable) {
            return [];
        }
    }

    /** ✍️ Typing indicator — set flag (dipanggil saat mengetik), cek lawan, pruned otomatis (>8 dtk). */
    public static function setTyping(int $userId, int $peerId): void
    {
        try {
            Database::run(
                'INSERT INTO dm_typing (user_id, peer_id, ts) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE ts = NOW()',
                [$userId, $peerId]
            );
        } catch (\Throwable) {
        }
    }

    public static function isTyping(int $fromUser, int $toPeer): bool
    {
        try {
            return (int) (Database::value(
                'SELECT COUNT(*) FROM dm_typing WHERE user_id = ? AND peer_id = ? AND ts > NOW() - INTERVAL 8 SECOND',
                [$fromUser, $toPeer]
            ) ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** ✅ Tandai semua percakapan dibaca (Telegram-style mark all). */
    public static function markAllRead(int $me): void
    {
        Database::run('UPDATE messages SET read_at = NOW() WHERE recipient_id = ? AND read_at IS NULL', [$me]);
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
