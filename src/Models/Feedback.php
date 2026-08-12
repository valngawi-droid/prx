<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Feedback
{
    public static function add(string $name, string $message, int $rating, string $ip): void
    {
        $rating = max(1, min(5, $rating));
        // anti-spam: 1 feedback per IP per 10 menit
        $recent = (int) Database::value(
            'SELECT COUNT(*) FROM feedback WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
            [$ip]
        );
        if ($recent > 0) {
            throw new \RuntimeException('Terlalu sering, coba lagi 10 menit lagi.');
        }
        Database::run('INSERT INTO feedback (name, message, rating, ip_address) VALUES (?, ?, ?, ?)', [$name, $message, $rating, $ip]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function approved(int $limit = 6): array
    {
        return Database::all("SELECT name, message, rating, created_at FROM feedback WHERE is_approved = 1 ORDER BY id DESC LIMIT {$limit}");
    }

    /** @return array<int, array<string, mixed>> */
    public static function pending(): array
    {
        return Database::all('SELECT * FROM feedback WHERE is_approved = 0 ORDER BY id DESC');
    }

    public static function approve(int $id): void
    {
        Database::run('UPDATE feedback SET is_approved = 1 WHERE id = ?', [$id]);
    }

    public static function deleteById(int $id): void
    {
        Database::run('DELETE FROM feedback WHERE id = ?', [$id]);
    }

    public static function pendingCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM feedback WHERE is_approved = 0');
    }
}
