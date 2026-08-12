<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Database;
use ChiperX\Models\User;

/**
 * Mesin Achievement: evaluasi kondisi → unlock → kredit reward.
 * Dipanggil setelah event bermakna: main game, redeem, referral.
 */
final class AchievementService
{
    /**
     * Cek semua achievement yang belum dimiliki user; unlock yang terpenuhi.
     * @return array<int, array<string, mixed>> Achievement yang BARU terbuka.
     */
    public static function checkAndUnlock(int $userId): array
    {
        $unlocked = [];
        try {
            $owned = array_map('intval', array_column(
                Database::all('SELECT achievement_id FROM user_achievements WHERE user_id = ?', [$userId]),
                'achievement_id'
            ));
            $all = Database::all('SELECT * FROM achievements WHERE is_active = 1 ORDER BY reward_coins ASC');

            foreach ($all as $a) {
                if (in_array((int) $a['id'], $owned, true)) {
                    continue;
                }
                if (!self::conditionMet($a, $userId)) {
                    continue;
                }
                Database::transaction(function () use ($a, $userId) {
                    Database::run(
                        'INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)',
                        [$userId, $a['id']]
                    );
                    if ((int) $a['reward_coins'] > 0) {
                        User::addCoins($userId, (int) $a['reward_coins']);
                    }
                });
                AuditLogger::record('achievement.unlock', ['code' => $a['code'], 'reward' => $a['reward_coins']], 'info', $userId);
                $unlocked[] = $a;
            }

            // Broadcast ke Discord bila achievement langka terbuka
            foreach ($unlocked as $a) {
                if ((int) $a['reward_coins'] >= 75) {
                    $user = User::find($userId);
                    DiscordWebhook::send('🏅 Achievement Langka Terbuka!', '', DiscordWebhook::COLOR_PURPLE, [
                        ['name' => 'Pemain', 'value' => $user['email'] ?? '-', 'inline' => true],
                        ['name' => 'Badge', 'value' => $a['icon'] . ' ' . $a['name'], 'inline' => true],
                    ]);
                }
            }
        } catch (\Throwable) {
            // Kegagalan sistem badge tidak boleh mengganggu alur utama
        }
        return $unlocked;
    }

    /** Evaluasi satu kondisi terhadap metrik user saat ini. */
    private static function conditionMet(array $a, int $userId): bool
    {
        $target = (int) $a['condition_value'];
        return match ((string) $a['condition_type']) {
            'total_plays' => (int) Database::value(
                'SELECT COUNT(*) FROM game_history WHERE user_id = ?', [$userId]) >= $target,
            'total_wins' => (int) Database::value(
                'SELECT COUNT(*) FROM game_history WHERE user_id = ? AND reward > 0', [$userId]) >= $target,
            'big_win' => (int) Database::value(
                'SELECT COUNT(*) FROM game_history WHERE user_id = ? AND reward >= ?', [$userId, $target]) >= 1,
            'redeem_count' => (int) Database::value(
                "SELECT COUNT(*) FROM transactions WHERE user_id = ? AND payment_method = 'coin' AND status = 'paid'", [$userId]) >= $target,
            'referral_count' => (int) Database::value(
                'SELECT COUNT(*) FROM users WHERE referred_by = ?', [$userId]) >= $target,
            'coin_balance' => (int) Database::value(
                'SELECT coin_balance FROM users WHERE id = ?', [$userId]) >= $target,
            default => false,
        };
    }

    /** Ringkas untuk respons JSON. @return array<int, array{name:string, icon:string, reward:int}> */
    public static function summarize(array $achievements): array
    {
        return array_values(array_map(static fn (array $a) => [
            'name'   => $a['name'],
            'icon'   => $a['icon'],
            'reward' => (int) $a['reward_coins'],
        ], $achievements));
    }
}
