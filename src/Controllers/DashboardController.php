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
            'referralBonus' => (int) (Setting::get('referral_bonus') ?? '50'),
            'refLink'      => Config::appUrl() . '/login?ref=' . $refCode,
            'leaderboard'  => User::topCoins(5),
            'weeklyTop'    => \ChiperX\Services\LeaderboardService::weeklyTop(5),
            'myRewards'    => \ChiperX\Services\LeaderboardService::myRewards((int) $user['id']),
            'badges'       => \ChiperX\Models\Achievement::withUnlockStatus((int) $user['id']),
            'badgeCount'   => \ChiperX\Models\Achievement::unlockedCount((int) $user['id']),
            'badgeTotal'   => \ChiperX\Models\Achievement::totalActive(),
        ]);
    }

    /** POST /dashboard/claim-daily — bonus login harian (1x per hari WIB, atomik). */
    public function claimDaily(Request $req): Response
    {
        $this->guardCsrf();
        $user  = auth_user();
        $bonus = max(0, (int) (Setting::get('daily_bonus') ?? '15'));

        if (!User::claimDailyBonus((int) $user['id'], $bonus)) {
            flash('warning', 'Bonus harian sudah Anda klaim hari ini. Kembali lagi setelah 00:00 WIB! ⏰');
            return redirect('/dashboard');
        }
        AuditLogger::record('daily_bonus.claim', ['bonus' => $bonus], 'info', (int) $user['id'], $req->ip());
        flash('success', "Bonus harian +{$bonus} ChiperX Coin berhasil diklaim! 🎁");
        return redirect('/dashboard');
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
