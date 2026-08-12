<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Models\Announcement;
use ChiperX\Models\Changelog;
use ChiperX\Models\Feedback;
use ChiperX\Models\Setting;
use ChiperX\Models\SiteLink;
use ChiperX\Models\Transaction;
use ChiperX\Models\User;

/**
 * Halaman-halaman publik: Beranda, Informasi ChiperX, All Links.
 */
final class HomeController extends Controller
{
    public function index(Request $req): string
    {
        [$totalUsers, $totalTx, $totalProducts] = [0, 0, 0];
        $announcements = $feedbacks = $weeklyTop = [];
        try {
            $totalUsers    = User::totalCount();
            $totalTx       = Transaction::totalPaidCount();
            $totalProducts = \ChiperX\Models\Product::totalCount();
            $announcements = Announcement::active(2);
            $feedbacks     = Feedback::approved(6);
            $weeklyTop     = \ChiperX\Services\LeaderboardService::weeklyTop(3);
        } catch (\Throwable) {
            // DB belum ter-setup: beranda tetap tampil dengan angka 0
        }
        return $this->view('home/index', [
            'title'         => 'Beranda',
            'tagline'       => Setting::get('site_tagline', 'Platform Digital Komunitas Generasi Baru'),
            'heroDesc'      => Setting::get('hero_desc', 'Mainkan mini games setiap hari, kumpulkan ChiperX Coin, dan tukarkan dengan project eksklusif — tanpa keluar uang sepeser pun.'),
            'totalUsers'    => $totalUsers,
            'totalTx'       => $totalTx,
            'totalProducts' => $totalProducts,
            'announcements' => $announcements,
            'feedbacks'     => $feedbacks,
            'weeklyTop'     => $weeklyTop,
        ]);
    }

    public function info(Request $req): string
    {
        $changelogs = [];
        try {
            $changelogs = Changelog::latest();
        } catch (\Throwable) {
        }
        return $this->view('info/index', [
            'title'      => 'Informasi ChiperX',
            'changelogs' => $changelogs,
        ]);
    }

    public function links(Request $req): string
    {
        $links = [];
        try {
            $links = SiteLink::active();
        } catch (\Throwable) {
        }
        return $this->view('links/index', ['title' => 'All Link ChiperX', 'links' => $links]);
    }

    public function sendFeedback(Request $req): \ChiperX\Core\Response
    {
        $this->guardCsrf();
        $name    = $req->str('name', '', 80);
        $message = $req->str('message', '', 1000);
        $rating  = $req->int('rating', 5);
        if ($name === '' || mb_strlen($message) < 5) {
            flash('error', 'Nama dan pesan (min. 5 karakter) wajib diisi.');
            return redirect('/#testimoni');
        }
        try {
            Feedback::add($name, $message, $rating, $req->ip());
            flash('success', 'Terima kasih! Ulasan Anda menunggu moderasi admin.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        return redirect('/#testimoni');
    }
}
