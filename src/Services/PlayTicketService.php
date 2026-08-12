<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Database;
use ChiperX\Models\Setting;

/**
 * Manajemen "Tiket Main" harian: 3 tiket/hari, reset otomatis 00:00 WIB.
 * Aplikasi berjalan dengan timezone Asia/Jakarta sehingga CURDATE()/NOW()
 * MySQL (time_zone +07:00) selalu mengikuti WIB.
 */
final class PlayTicketService
{
    /**
     * Reset tiket user jika tanggal WIB sudah berganti. Mengembalikan jumlah tiket terkini.
     */
    public static function ensureFresh(array $user): int
    {
        $today = date('Y-m-d'); // WIB
        if (($user['tickets_reset_at'] ?? null) !== $today) {
            $default = (int) (Setting::get('daily_tickets') ?? '3');
            Database::run(
                'UPDATE users SET play_tickets = ?, tickets_reset_at = CURDATE() WHERE id = ?',
                [$default, $user['id']]
            );
            return $default;
        }
        return (int) $user['play_tickets'];
    }

    /** Konsumsi 1 tiket secara atomik (anti race-condition double-click). */
    public static function consume(int $userId): bool
    {
        $stmt = Database::run(
            'UPDATE users SET play_tickets = play_tickets - 1 WHERE id = ? AND play_tickets > 0',
            [$userId]
        );
        return $stmt->rowCount() === 1;
    }

    public static function currentTickets(int $userId): int
    {
        return (int) Database::value('SELECT play_tickets FROM users WHERE id = ?', [$userId]);
    }
}
