<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Config;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\GameHistory;
use ChiperX\Models\Setting;
use ChiperX\Models\Ticket;
use ChiperX\Models\Transaction;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\PlayTicketService;

/**
 * Dashboard User: profil, koin, saldo, riwayat, tiket support.
 */
final class DashboardController extends Controller
{
    public function index(Request $req): string
    {
        $user = auth_user();
        $tickets = PlayTicketService::ensureFresh($user);
        $user['play_tickets'] = $tickets;

        // Akun lama (pra-referral) otomatis diberi kode saat membuka dashboard
        $refCode = User::ensureReferralCode((int) $user['id']);
        $user['referral_code'] = $refCode;

        return $this->panel('dashboard/index', [
            'title'        => 'Dashboard Saya',
            'user'         => $user,
            'history'      => Transaction::forUser((int) $user['id']),
            'games'        => GameHistory::forUser((int) $user['id']),
            'tickets'      => Ticket::forUser((int) $user['id']),
            'maxTickets'   => (int) (Setting::get('daily_tickets') ?? '3'),
            'dailyClaimed' => (($user['last_daily_claim'] ?? null) === date('Y-m-d')),
            'dailyBonus'   => (int) (Setting::get('daily_bonus') ?? '15'),
            'streak'       => (int) ($user['streak_count'] ?? 0),
            'gamesToday'   => GameHistory::countToday((int) $user['id']),
            'questClaimed' => (($user['quest_claimed_on'] ?? null) === date('Y-m-d')),
            'shouts'       => \ChiperX\Models\Shout::latest(25),
            'referralBonus' => (int) (Setting::get('referral_bonus') ?? '50'),
            'refLink'      => Config::appUrl() . '/login?ref=' . $refCode,
            'leaderboard'  => User::topCoins(5),
            'weeklyTop'    => \ChiperX\Services\LeaderboardService::weeklyTop(5),
            'myRewards'    => \ChiperX\Services\LeaderboardService::myRewards((int) $user['id']),
            'badges'       => \ChiperX\Models\Achievement::withUnlockStatus((int) $user['id']),
            'badgeCount'   => \ChiperX\Models\Achievement::unlockedCount((int) $user['id']),
            'badgeTotal'   => \ChiperX\Models\Achievement::totalActive(),
            'coinMult'     => self::coinMultiplier(),
            'coinWeek'     => \ChiperX\Core\Database::all(
                'SELECT DATE(created_at) AS d, SUM(reward) AS total FROM game_history
                 WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL 6 DAY
                 GROUP BY DATE(created_at) ORDER BY d ASC',
                [(int) $user['id']]
            ),
        ]);
    }

    /** ⚡ Pengali koin event global (diatur Owner; 0.5–10×). */
    private static function coinMultiplier(): float
    {
        return max(0.5, min(10.0, (float) (Setting::get('coin_multiplier', '1') ?? '1')));
    }

    /** POST /dashboard/claim-daily — bonus login harian (1x per hari WIB, atomik). */
    public function claimDaily(Request $req): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $base = max(0, (int) (Setting::get('daily_bonus') ?? '15'));

        // 💎 Fitur Premium: pemegang tag VIP/Premium → bonus harian 2× lipat
        $isVip = user_is_vip($user);
        if ($isVip) {
            $base *= 2;
        }
        // ⚡ Event pengali koin global (Owner)
        $mult = self::coinMultiplier();
        if ($mult !== 1.0) {
            $base = (int) round($base * $mult);
        }

        $res = User::claimDailyStreak((int) $user['id'], $base);
        if (!$res['ok']) {
            flash('warning', 'Bonus harian sudah Anda klaim hari ini. Kembali lagi setelah 00:00 WIB! ⏰');
            return redirect('/dashboard');
        }
        AuditLogger::record('daily_bonus.claim', ['reward' => $res['reward'], 'streak' => $res['streak']], 'info', (int) $user['id'], $req->ip());
        if ((int) $res['streak'] === 7) {
            \ChiperX\Models\Notification::add((int) $user['id'], '🔥 STREAK 7 HARI SEMPURNA!', 'Kamu menuntaskan beruntun penuh — reward maksimal +' . $res['reward'] . ' koin. Besok siklus baru dimulai!', '/dashboard', 'success');
            \ChiperX\Services\DiscordWebhook::send('🔥 Streak 7 Hari!', '', \ChiperX\Services\DiscordWebhook::COLOR_CYAN, [
                ['name' => 'User', 'value' => $user['email'], 'inline' => true],
                ['name' => 'Reward', 'value' => '+' . $res['reward'] . ' koin', 'inline' => true],
            ]);
        }
        $api = ['day' => (int) $res['streak']];
        $vipTag = $isVip ? ' 💎 VIP ×2!' : '';
        $multTag = $mult !== 1.0 ? ' ⚡ EVENT ×' . rtrim(rtrim(number_format($mult, 2), '0'), '.') . '!' : '';
        flash('success', "Hari ke-{$api['day']} beruntun! +{$res['reward']} ChiperX Coin diklaim! 🎁{$vipTag}{$multTag} Besok: hari ke-" . min(7, (int) $res['streak'] + 1) . ' 🔥');
        return redirect('/dashboard');
    }

    /** POST /dashboard/quest-claim — reward quest harian (klaim harian + 3 game). */
    public function questClaim(Request $req): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $uid  = (int) $user['id'];
        $dailyDone = (($user['last_daily_claim'] ?? null) === date('Y-m-d'));
        $gamesDone = GameHistory::countToday($uid) >= 3;
        if (!$dailyDone || !$gamesDone) {
            flash('warning', 'Quest belum lengkap — klaim bonus harian DAN main 3 game dulu hari ini.');
            return redirect('/dashboard#quest');
        }
        $mult   = self::coinMultiplier();
        $reward = (int) round(30 * $mult);
        if (!User::claimQuestReward($uid, $reward)) {
            flash('warning', 'Quest hari ini sudah diklaim. Besok ada yang baru! ⏰');
            return redirect('/dashboard#quest');
        }
        AuditLogger::record('quest.claim', ['reward' => $reward], 'info', $uid, $req->ip());
        \ChiperX\Models\Notification::add($uid, '🎯 Quest Harian Selesai! +' . $reward . ' koin', 'Quest lengkap: bonus harian + 3 game hari ini. Mantap!', '/dashboard', 'success');
        flash('success', "Quest harian selesai! +{$reward} koin 🎯🔥");
        return redirect('/dashboard#quest');
    }

    /** POST /dashboard/shout — kirim pesan shoutbox (throttle 5 detik/user). */
    public function shout(Request $req): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $msg  = trim($req->str('message', '', 190));
        if (mb_strlen($msg) < 2) {
            flash('error', 'Pesan terlalu pendek.');
            return redirect('/dashboard#chat');
        }
        $last = \ChiperX\Models\Shout::lastPostedAt((int) $user['id']);
        if ($last !== null && (time() - strtotime($last)) < 5) {
            flash('warning', 'Santai — 1 pesan tiap 5 detik ya. ⏱️');
            return redirect('/dashboard#chat');
        }
        \ChiperX\Models\Shout::add((int) $user['id'], $msg);
        if (random_int(1, 20) === 1) {
            \ChiperX\Models\Shout::prune(); // bersih-bersih sesekali (~5% request)
        }
        return redirect('/dashboard#chat');
    }

    /** GET /dashboard/shouts.json — polling shoutbox (10 dtk). */
    public function shoutsApi(Request $req): Response
    {
        $me      = auth_user();
        $canMod  = in_array(($me['role'] ?? 'user'), ['admin', 'owner'], true);
        $viewerId = (int) $me['id'];
        $rows = \ChiperX\Models\Shout::latest(25);
        $out  = array_map(static function (array $s) use ($canMod, $viewerId): array {
            return [
                'id'      => (int) $s['id'],
                'name'    => (string) $s['name'],
                'msg'     => (string) $s['message'],
                'ago'     => waktu_lalu((string) $s['created_at']),
                'role'    => (string) $s['role'],
                'verif'   => !empty($s['is_verified']),
                'mine'    => (int) $s['user_id'] === $viewerId,
                'can'     => $canMod || (int) $s['user_id'] === $viewerId,
            ];
        }, $rows);
        return Response::json(['items' => $out]);
    }

    /** POST /dashboard/shouts/{id}/delete — hapus pesan sendiri (admin/owner: pesan siapa pun). */
    public function shoutDelete(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me  = auth_user();
        $id  = (int) $params['id'];
        $row = \ChiperX\Core\Database::one('SELECT user_id FROM shouts WHERE id = ?', [$id]);
        if ($row && (in_array(($me['role'] ?? 'user'), ['admin', 'owner'], true) || (int) $row['user_id'] === (int) $me['id'])) {
            \ChiperX\Models\Shout::delete($id);
            flash('success', 'Pesan dihapus. 🧹');
        }
        return redirect('/dashboard#chat');
    }

    /** POST /dashboard/transfer — kirim koin ke member lain (fee 5%). */
    public function transfer(Request $req): Response
    {
        $this->guardCsrf();
        $user   = auth_user();
        $target = User::findByIdentity($req->str('target', '', 120));
        $amount = (int) $req->str('amount', '0', 12);
        if (!$target) {
            flash('error', 'Username/email tujuan tidak ditemukan.');
            return redirect('/dashboard#transfer');
        }
        $res = User::transferCoins((int) $user['id'], (int) $target['id'], $amount, 5);
        if (!$res['ok']) {
            flash('error', $res['message']);
            return redirect('/dashboard#transfer');
        }
        $fee = (int) ceil($amount * 5 / 100);
        AuditLogger::record('coin.transfer', ['to' => $target['email'], 'amount' => $amount, 'fee' => $fee], 'info', (int) $user['id'], $req->ip());
        \ChiperX\Models\Notification::add((int) $user['id'], '💸 Transfer terkirim', "{$amount} koin → {$target['name']} (biaya {$fee}).", '/dashboard');
        \ChiperX\Models\Notification::add((int) $target['id'], '🪙 Kamu menerima ' . $amount . ' koin!', 'Dari ' . (string) $user['name'] . ' via transfer P2P.', '/dashboard', 'success');
        flash('success', "Terkirim! {$amount} koin → {$target['name']} (biaya layanan {$fee}). 💸");
        return redirect('/dashboard#transfer');
    }

    public function updateProfile(Request $req): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $name = $req->str('name', '', 80);
        if ($name === '') {
            flash('error', 'Nama tidak boleh kosong.');
            return redirect('/dashboard');
        }
        User::updateProfile((int) $user['id'], $name);
        AuditLogger::record('profile.update', [], 'info', (int) $user['id'], $req->ip(), $req->userAgent());
        flash('success', 'Profil berhasil diperbarui.');
        return redirect('/dashboard');
    }

    public function createTicket(Request $req): Response
    {
        $this->guardCsrf();
        $user    = auth_user();
        $subject = $req->str('subject', '', 160);
        $message = $req->str('message', '', 2000);
        $category = $req->str('category', 'umum', 20);
        if ($subject === '' || mb_strlen($message) < 10) {
            flash('error', 'Subjek dan pesan (min. 10 karakter) wajib diisi.');
            return redirect('/dashboard#support');
        }
        Ticket::create((int) $user['id'], $subject, $category, $message);
        flash('success', 'Tiket bantuan dikirim. Admin akan segera membalas.');
        return redirect('/dashboard#support');
    }

    public function replyTicket(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user    = auth_user();
        $ticket  = Ticket::find((int) $params['id']);
        $message = $req->str('message', '', 2000);
        if (!$ticket || (int) $ticket['user_id'] !== (int) $user['id'] || $ticket['status'] === 'closed') {
            flash('error', 'Tiket tidak ditemukan / sudah ditutup.');
            return redirect('/dashboard#support');
        }
        if (mb_strlen($message) >= 2) {
            Ticket::reply((int) $ticket['id'], (int) $user['id'], $message, byStaff: false);
            flash('success', 'Balasan terkirim.');
        }
        return redirect('/dashboard#support');
    }
}
