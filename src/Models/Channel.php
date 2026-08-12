<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * 📢 Channels gaya Discord — ruang ngobrol publik + kanal pengumuman.
 * Slowmode, pin pesan, reaksi emoji, polling — semua di sini.
 */
final class Channel
{
    public const EMOJIS = ['👍', '❤️', '🔥', '😂', '🎉'];

    /** Daftar channel aktif + jumlah pesan. */
    public static function all(): array
    {
        return Database::all(
            'SELECT c.id, c.slug, c.name, c.topic, c.kind, c.slowmode,
                    (SELECT COUNT(*) FROM channel_messages m WHERE m.channel_id = c.id) AS messages_count,
                    (SELECT MAX(m.created_at) FROM channel_messages m WHERE m.channel_id = c.id) AS last_at
             FROM channels c WHERE c.is_active = 1 ORDER BY c.kind = "announce" DESC, c.id ASC'
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::one('SELECT * FROM channels WHERE slug = ? AND is_active = 1', [mb_strtolower(trim($slug))]);
    }

    public static function create(string $name, string $topic, string $kind, int $createdBy): bool
    {
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            return false;
        }
        $base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name));
        $base = trim($base, '-') ?: 'kanal';
        $slug = $base;
        $i = 2;
        while (Database::value('SELECT COUNT(*) FROM channels WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . $i++;
        }
        Database::run(
            'INSERT INTO channels (slug, name, topic, kind, created_by) VALUES (?, ?, ?, ?, ?)',
            [mb_substr($slug, 0, 40), mb_substr($name, 0, 60), mb_substr(trim($topic), 0, 120) ?: null,
             $kind === 'announce' ? 'announce' : 'text', $createdBy]
        );
        return true;
    }

    public static function setSlowmode(int $id, int $seconds): void
    {
        Database::run('UPDATE channels SET slowmode = ? WHERE id = ?', [max(0, min(3600, $seconds)), $id]);
    }

    public static function setTopic(int $id, string $topic): void
    {
        Database::run('UPDATE channels SET topic = ? WHERE id = ?', [mb_substr(trim($topic), 0, 120) ?: null, $id]);
    }

    /** Pesan channel (terbaru di bawah) + info penulis + reaksi + data poll + kutipan. */
    public static function messages(int $channelId, int $limit = 60, int $viewerId = 0): array
    {
        $rows = Database::all(
            'SELECT m.id, m.user_id, m.kind, m.body, m.reply_to, m.poll_options, m.edited_at, m.created_at,
                    u.name, u.username, u.role, u.badges, u.avatar,
                    r.body AS reply_body, ru.name AS reply_name
             FROM channel_messages m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN channel_messages r ON r.id = m.reply_to
             LEFT JOIN users ru ON ru.id = r.user_id
             WHERE m.channel_id = ?
             ORDER BY m.id DESC LIMIT ' . max(1, $limit),
            [$channelId]
        );
        $rows = array_reverse($rows);
        if ($rows === []) {
            return [];
        }
        // Reaksi + poll untuk semua pesan sekaligus (anti N+1)
        $ids = implode(',', array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $react = Database::all(
            "SELECT message_id, emoji, COUNT(*) AS c,
                    MAX(user_id = {$viewerId}) AS mine
             FROM channel_reactions WHERE message_id IN ({$ids})
             GROUP BY message_id, emoji"
        );
        $voteRows = Database::all(
            "SELECT message_id, opt, COUNT(*) AS c FROM channel_poll_votes WHERE message_id IN ({$ids}) GROUP BY message_id, opt"
        );
        $myVotes = Database::all(
            "SELECT message_id, opt FROM channel_poll_votes WHERE user_id = ? AND message_id IN ({$ids})",
            [$viewerId]
        );
        $byMsg = [];
        foreach ($react as $r) {
            $byMsg[(int) $r['message_id']]['reactions'][] = ['emoji' => (string) $r['emoji'], 'count' => (int) $r['c'], 'mine' => (bool) $r['mine']];
        }
        foreach ($voteRows as $v) {
            $byMsg[(int) $v['message_id']]['votes'][(int) $v['opt']] = (int) $v['c'];
        }
        $mine = [];
        foreach ($myVotes as $v) {
            $mine[(int) $v['message_id']] = (int) $v['opt'];
        }
        foreach ($rows as &$row) {
            $row['reactions'] = $byMsg[(int) $row['id']]['reactions'] ?? [];
            $row['votes'] = $byMsg[(int) $row['id']]['votes'] ?? [];
            $row['my_vote'] = $mine[(int) $row['id']] ?? null;
            $row['total_votes'] = array_sum($row['votes']);
        }
        return $rows;
    }

    public static function post(int $channelId, int $userId, string $body, ?int $replyTo = null, string $kind = 'text', ?string $pollJson = null): int
    {
        Database::run(
            'INSERT INTO channel_messages (channel_id, user_id, kind, body, reply_to, poll_options) VALUES (?, ?, ?, ?, ?, ?)',
            [$channelId, $userId, $kind === 'poll' ? 'poll' : 'text', mb_substr($body, 0, 1000), $replyTo, $pollJson]
        );
        return (int) Database::value('SELECT LAST_INSERT_ID()');
    }

    public static function findMessage(int $id): ?array
    {
        return Database::one('SELECT * FROM channel_messages WHERE id = ?', [$id]);
    }

    public static function lastMessageAt(int $channelId, int $userId): ?string
    {
        $v = Database::value(
            'SELECT created_at FROM channel_messages WHERE channel_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
            [$channelId, $userId]
        );
        return $v !== null ? (string) $v : null;
    }

    /** ✏️ Edit pesan sendiri (maks 15 menit). */
    public static function editMessage(int $id, int $userId, string $body): bool
    {
        Database::run(
            'UPDATE channel_messages SET body = ?, edited_at = NOW() WHERE id = ? AND user_id = ? AND created_at > NOW() - INTERVAL 15 MINUTE',
            [mb_substr($body, 0, 1000), $id, $userId]
        );
        return Database::value('SELECT edited_at FROM channel_messages WHERE id = ?', [$id]) !== null;
    }

    public static function deleteMessage(int $id): void
    {
        Database::run('DELETE FROM channel_reactions WHERE message_id = ?', [$id]);
        Database::run('DELETE FROM channel_poll_votes WHERE message_id = ?', [$id]);
        Database::run('DELETE FROM channel_messages WHERE id = ?', [$id]);
        Database::run('UPDATE channels SET pinned_id = NULL WHERE pinned_id = ?', [$id]);
    }

    /** 📌 Set/hapus pin channel (null untuk lepas). */
    public static function pin(int $channelId, ?int $messageId): void
    {
        Database::run('UPDATE channels SET pinned_id = ? WHERE id = ?', [$messageId, $channelId]);
    }

    public static function pinnedMessage(int $channelId): ?array
    {
        return Database::one(
            'SELECT m.id, m.body, u.name FROM channel_messages m
             JOIN channels c ON c.pinned_id = m.id
             JOIN users u ON u.id = m.user_id
             WHERE c.id = ?',
            [$channelId]
        );
    }

    /** 😀 Toggle reaksi → true bila SEKARANG aktif. */
    public static function toggleReaction(int $messageId, int $userId, string $emoji): bool
    {
        if (!in_array($emoji, self::EMOJIS, true)) {
            return false;
        }
        $has = (int) (Database::value(
            'SELECT COUNT(*) FROM channel_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?',
            [$messageId, $userId, $emoji]
        ) ?? 0) > 0;
        if ($has) {
            Database::run('DELETE FROM channel_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?', [$messageId, $userId, $emoji]);
            return false;
        }
        Database::run('INSERT IGNORE INTO channel_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)', [$messageId, $userId, $emoji]);
        return true;
    }

    /** 📊 Voting poll (1 suara). */
    public static function vote(int $messageId, int $userId, int $opt): void
    {
        Database::run(
            'INSERT INTO channel_poll_votes (message_id, user_id, opt) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE opt = VALUES(opt)',
            [$messageId, $userId, max(0, $opt)]
        );
    }

    /** 🟢 Member online sekarang (aktif < 5 menit). */
    public static function onlineNow(int $limit = 12): array
    {
        return Database::all(
            'SELECT id, name, username, role, badges, avatar FROM users
             WHERE status = "active" AND last_activity > NOW() - INTERVAL 5 MINUTE
             ORDER BY last_activity DESC LIMIT ' . max(1, $limit)
        );
    }
}
