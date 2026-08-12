<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Database;
use ChiperX\Models\GameHistory;
use ChiperX\Models\Setting;
use ChiperX\Models\User;

/**
 * Mesin Mini Games ChiperX.
 *
 * RNG: random_int() (CSPRNG) + weighted probability dari tabel settings,
 * sehingga Owner dapat mengatur RTP (win-rate) per hadiah via panel.
 * Seluruh mutasi saldo/tiket dilakukan di dalam TRANSAKSI DB yang atomik.
 */
final class GameService
{
    /** Definisi 3 game: key hadiah => label UI. Bobot diambil dari settings. */
    private const GAMES = [
        'gacha' => [
            'zonk'     => ['label' => 'Zonk', 'reward' => 0],
            'coin_10'  => ['label' => '10 Coin', 'reward' => 10],
            'coin_50'  => ['label' => '50 Coin', 'reward' => 50],
            'coin_100' => ['label' => '100 Coin', 'reward' => 100],
        ],
        'mystery_box' => [
            'zonk'      => ['label' => 'Zonk', 'reward' => 0],
            'coin_25'   => ['label' => '25 Coin', 'reward' => 25],
            'coin_75'   => ['label' => '75 Coin', 'reward' => 75],
            'coin_150'  => ['label' => '150 Coin', 'reward' => 150],
        ],
        'card_flip' => [
            'zonk'      => ['label' => 'Zonk', 'reward' => 0],
            'coin_20'   => ['label' => '20 Coin', 'reward' => 20],
            'coin_60'   => ['label' => '60 Coin', 'reward' => 60],
            'coin_120'  => ['label' => '120 Coin', 'reward' => 120],
        ],
    ];

    public const SETTING_PREFIX = [
        'gacha'       => 'gacha_',
        'mystery_box' => 'box_',
        'card_flip'   => 'card_',
    ];

    public static function gameExists(string $game): bool
    {
        return isset(self::GAMES[$game]);
    }

    /**
     * Bobot persen per hadiah (dipakai juga untuk render UI roda).
     * @return array<string, float> key => persen
     */
    public static function weights(string $game): array
    {
        $prefix = self::SETTING_PREFIX[$game];
        $weights = [];
        foreach (self::GAMES[$game] as $key => $_) {
            $weights[$key] = max(0.0, (float) (Setting::get($prefix . $key) ?? '0'));
        }
        // Fallback aman jika settings kosong/rusak: zonk dominan
        if (array_sum($weights) <= 0.0) {
            $weights = array_map(static fn ($k) => $k === 'zonk' ? 60.0 : 40.0 / (count($weights) - 1), array_keys($weights));
        }
        return $weights;
    }

    /**
     * Inti RNG weighted — MURNI (tanpa DB), sehingga dapat diuji unit test.
     * Menerima array bobot bebas (tidak harus total 100; dinormalisasi otomatis).
     *
     * @param array<string, float|int> $weights
     * @throws \InvalidArgumentException jika total bobot <= 0
     */
    public static function rollFromWeights(array $weights): string
    {
        $total = array_sum($weights);
        if ($total <= 0.0) {
            throw new \InvalidArgumentException('Total bobot harus lebih dari 0.');
        }
        // Skala 0..(total*100000) agar presisi persen hingga 5 desimal
        $roll   = random_int(0, (int) round($total * 100000) - 1) / 100000.0;
        $cursor = 0.0;
        $chosen = (string) array_key_first($weights);
        foreach ($weights as $key => $weight) {
            $cursor += (float) $weight;
            if ($roll < $cursor) {
                $chosen = (string) $key;
                break;
            }
        }
        return $chosen;
    }

    /**
     * Putar RNG weighted — murni random_int, tanpa pola, tidak bisa ditebak.
     * @return array{key:string,label:string,reward:int}
     */
    public static function roll(string $game): array
    {
        $chosen = self::rollFromWeights(self::weights($game));
        $def = self::GAMES[$game][$chosen];
        return ['key' => $chosen, 'label' => $def['label'], 'reward' => $def['reward']];
    }

    /**
     * Alur bermain lengkap (dipanggil Controller):
     *  1. Pastikan tiket harian segar (reset 00:00 WIB)
     *  2. Konsumsi 1 tiket secara atomik
     *  3. Roll RNG → kredit koin → catat history → audit
     *
     * @return array{ok:bool,message:string,result?:array,tickets_left?:int,coin_balance?:int}
     */
    public static function play(array $user, string $game): array
    {
        if (!self::gameExists($game)) {
            return ['ok' => false, 'message' => 'Game tidak dikenal.'];
        }

        $ticketsLeft = PlayTicketService::ensureFresh($user);
        if ($ticketsLeft <= 0) {
            return ['ok' => false, 'message' => 'Tiket harian habis! Reset lagi jam 00:00 WIB.', 'tickets_left' => 0];
        }

        try {
            $response = Database::transaction(function () use ($user, $game) {
                if (!PlayTicketService::consume((int) $user['id'])) {
                    return ['ok' => false, 'message' => 'Tiket tidak cukup.', 'tickets_left' => 0];
                }
                $result = self::roll($game);

                if ($result['reward'] > 0) {
                    User::addCoins((int) $user['id'], $result['reward']);
                }
                GameHistory::add((int) $user['id'], $game, $result['key'], $result['reward']);

                $balance = (int) Database::value('SELECT coin_balance FROM users WHERE id = ?', [$user['id']]);
                $tickets = PlayTicketService::currentTickets((int) $user['id']);

                if ($result['reward'] >= 100) {
                    AuditLogger::record('game.big_win', [
                        'game' => $game, 'reward' => $result['reward'],
                    ], 'warning', (int) $user['id']);
                    DiscordWebhook::send('🎰 Jackpot Mini Games', 'Seorang pemain menang besar!', DiscordWebhook::COLOR_CYAN, [
                        ['name' => 'Pemain', 'value' => $user['email'], 'inline' => true],
                        ['name' => 'Game', 'value' => $game, 'inline' => true],
                        ['name' => 'Hadiah', 'value' => $result['reward'] . ' Coin', 'inline' => true],
                    ]);
                }

                return [
                    'ok'            => true,
                    'message'       => $result['reward'] > 0 ? "Selamat! Anda mendapat {$result['label']} 🎉" : 'Zonk! Coba lagi besok 😅',
                    'result'        => $result,
                    'tickets_left'  => $tickets,
                    'coin_balance'  => $balance,
                ];
            });

            // Evaluasi achievement di LUAR transaksi utama (koin reward-nya ikut dihitung)
            if (($response['ok'] ?? false) === true) {
                $fresh = AchievementService::checkAndUnlock((int) $user['id']);
                if ($fresh !== []) {
                    $response['achievements']  = AchievementService::summarize($fresh);
                    $response['coin_balance']  = (int) Database::value('SELECT coin_balance FROM users WHERE id = ?', [$user['id']]);
                }
            }
            return $response;
        } catch (\Throwable $e) {
            AuditLogger::record('game.error', ['error' => $e->getMessage()], 'critical', (int) $user['id']);
            return ['ok' => false, 'message' => 'Terjadi kesalahan sistem. Coba lagi.'];
        }
    }

    /** Statistik hadiah untuk ditampilkan di panel RTP. @return array<string, array<string, float>> */
    public static function allWeights(): array
    {
        $out = [];
        foreach (array_keys(self::GAMES) as $game) {
            $out[$game] = self::weights($game);
        }
        return $out;
    }

    /** Simpan bobot dari panel Owner (validasi total = 100%). @return string|null pesan error */
    public static function saveWeights(string $game, array $input): ?string
    {
        if (!self::gameExists($game)) {
            return 'Game tidak dikenal.';
        }
        $prefix = self::SETTING_PREFIX[$game];
        $total = 0.0;
        $clean = [];
        foreach (array_keys(self::GAMES[$game]) as $key) {
            $val = (float) ($input[$prefix . $key] ?? -1);
            if ($val < 0 || $val > 100) {
                return "Persentase {$key} harus antara 0–100.";
            }
            $clean[$prefix . $key] = rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
            $total += $val;
        }
        if (abs($total - 100.0) > 0.01) {
            return "Total persentase harus tepat 100% (sekarang {$total}%).";
        }
        foreach ($clean as $k => $v) {
            Setting::set($k, (string) $v);
        }
        Setting::flush();
        return null;
    }
}
