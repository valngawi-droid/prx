<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Ticket
{
    public static function create(int $userId, string $subject, string $category, string $message): int
    {
        $category = in_array($category, ['umum', 'pembayaran', 'bug', 'lainnya'], true) ? $category : 'umum';
        $id = Database::transaction(function () use ($userId, $subject, $category, $message) {
            Database::run('INSERT INTO tickets (user_id, subject, category) VALUES (?, ?, ?)', [$userId, $subject, $category]);
            $tid = Database::lastInsertId();
            Database::run('INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)', [$tid, $userId, $message]);
            return $tid;
        });
        return (int) $id;
    }

    /** @return array<int, array<string, mixed>> */
    public static function forUser(int $userId): array
    {
        return Database::all('SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC', [$userId]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function allAdmin(): array
    {
        return Database::all(
            'SELECT t.*, u.email FROM tickets t JOIN users u ON u.id = t.user_id ORDER BY t.updated_at DESC'
        );
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT t.*, u.email FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?',
            [$id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function replies(int $ticketId): array
    {
        return Database::all(
            'SELECT r.*, u.email, u.role FROM ticket_replies r JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ? ORDER BY r.id ASC',
            [$ticketId]
        );
    }

    public static function reply(int $ticketId, int $userId, string $message, bool $byStaff): void
    {
        Database::transaction(function () use ($ticketId, $userId, $message, $byStaff) {
            Database::run('INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)', [$ticketId, $userId, $message]);
            Database::run(
                'UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?',
                [$byStaff ? 'answered' : 'open', $ticketId]
            );
        });
    }

    public static function setStatus(int $ticketId, string $status): void
    {
        if (in_array($status, ['open', 'answered', 'closed'], true)) {
            Database::run('UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $ticketId]);
        }
    }

    public static function openCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
    }
}
