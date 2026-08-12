<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Database;
use ChiperX\Models\Setting;
use ChiperX\Models\User;

/**
 * Leaderboard mingguan: dihitung dari total koin yang DIMENANGKAN
 * di game_history selama pekan berjalan (Senin 00:00 — Senin 00:00 WIB).
 */
final class LeaderboardService
{
    /**
     * Batas pekan ISO (Senin..Minggu) untuk timestamp acuan.
     * @return array{0:string, 1:string, 2:string} [mulai, akhir, periodKey]
     */
    public static function weekBounds(int $refTimestamp): array
    {
        $day    = strtotime(date('Y-m-d', $refTimestamp));
        $dow    = (int) date('N', $day); // 1 = Senin
        $monday = strtotime('-' . ($dow - 1) . ' days', $day);
        return [
            date('Y-m-d 00:00:00', $monday),
            date('Y-m-d 00:00:00', strtotime('+7 days', $monday)),
            date('o-\\WW', $monday), // mis. 2026-W33
        ];
    }

    /**
     * Top peraih koin pada pekan (weekOffset 0 = pekan ini, -1 = pekan lalu).
     * @return array<int, array<string, mixed>>
     */
    public static function weeklyTop(int $limit = 5, int $weekOffset = 0): array
    {
        [$start, $end] = self::weekBounds(time() + $weekOffset * 604800);
        return Database::all(
            'SELECT u.id AS user_id, u.name, u.email, SUM(g.reward) AS earned
             FROM game_history g
             JOIN users u ON u.id = g.user_id
             WHERE g.created_at >= ? AND g.created_at < ? AND g.reward > 0 AND u.status = \'active\'
             GROUP BY g.user_id
             ORDER BY earned DESC
             LIMIT ' . max(1, min(50, $limit)),
            [$start, $end]
        );
    }

    /**
     * Bagikan hadiah untuk TOP 3 pekan LALU. Idempoten — aman dijalankan berulang
     * (unique key period+rank menolak distribusi ganda).
     * @return array{ok:bool, message:string, winners?:array<int, array<string, mixed>>}
     */
    public static function distributeLastWeek(): array
    {
        [, , $period] = self::weekBounds(time() - 604800);

        $sudah = (int) Database::value('SELECT COUNT(*) FROM weekly_rewards WHERE period = ?', [$period]);
        if ($sudah > 0) {
            return ['ok' => false, 'message' => "Hadiah periode {$period} sudah dibagikan sebelumnya."];
        }

        $top     = self::weeklyTop(3, -1);
        $winners = [];
        foreach ($top as $i => $row) {
            $rank  = $i + 1;
            $bonus = (int) (Setting::get('weekly_reward_' . $rank) ?? '0');
            if ($bonus <= 0) {
                continue;
            }
            try {
                Database::transaction(function () use ($period, $row, $rank, $bonus) {
                    Database::run(
                        'INSERT INTO weekly_rewards (period, user_id, `rank`, bonus) VALUES (?, ?, ?, ?)',
                        [$period, $row['user_id'], $rank, $bonus]
                    );
                    User::addCoins((int) $row['user_id'], $bonus);
                });
                AuditLogger::record('weekly_reward.grant', [
                    'period' => $period, 'rank' => $rank, 'bonus' => $bonus,
                ], 'info', (int) $row['user_id']);
                $winners[] = ['rank' => $rank, 'name' => $row['name'], 'earned' => (int) $row['earned'], 'bonus' => $bonus];
            } catch (\Throwable) {
                // Duplikat parsial → anggap sudah terproses
            }
        }

        if ($winners !== []) {
            $fields = array_map(static fn (array $w) => [
                'name'   => "#{$w['rank']} — {$w['name']}",
                'value'  => "Menang {$w['earned']} koin → bonus +{$w['bonus']} 🪙",
                'inline' => false,
            ], $winners);
            DiscordWebhook::send("🏆 Hadiah Leaderboard Mingguan ({$period})", 'Top 3 pemain pekan lalu menerima bonus koin.', DiscordWebhook::COLOR_CYAN, $fields);
        }

        return ['ok' => true, 'message' => "Distribusi {$period} selesai: " . count($winners) . ' pemenang.', 'winners' => $winners];
    }

    /** Riwayat hadiah mingguan milik user. @return array<int, array<string, mixed>> */
    public static function myRewards(int $userId, int $limit = 8): array
    {
        return Database::all(
            'SELECT * FROM weekly_rewards WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit,
            [$userId]
        );
    }
}
