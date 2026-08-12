<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Config;
use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
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

    /** GET /members — direktori publik semua member (badges on display). */
    public function members(Request $req): string
    {
        $q = $req->str('q', '', 40);
        $members = [];
        $total = 0;
        try {
            $total = (int) Database::value("SELECT COUNT(*) FROM users WHERE status = 'active'");
            if ($q !== '') {
                $members = Database::all(
                    "SELECT id, name, username, role, is_verified, badges, created_at FROM users
                     WHERE status = 'active' AND (name LIKE ? OR username LIKE ? OR email LIKE ?)
                     ORDER BY id DESC LIMIT 24",
                    ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']
                );
            } else {
                $members = Database::all(
                    "SELECT id, name, username, role, is_verified, badges, created_at FROM users
                     WHERE status = 'active' ORDER BY id DESC LIMIT 24"
                );
            }
        } catch (\Throwable) {
        }
        return $this->view('home/members', [
            'title'   => 'Members ChiperX',
            'members' => $members,
            'total'   => $total,
            'q'       => $q,
        ]);
    }

    /** GET /status — halaman status publik ala status.enterprise. */
    public function status(Request $req): string
    {
        $dbOk = false;
        $tables = 0;
        $ms = -1;
        $t0 = microtime(true);
        try {
            Database::pdo()->query('SELECT 1');
            $dbOk = true;
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $tables = count(Database::all('SHOW TABLES'));
        } catch (\Throwable) {
        }
        return $this->view('home/status', [
            'title'   => 'Status Sistem',
            'dbOk'    => $dbOk,
            'dbMs'    => $ms,
            'tables'  => $tables,
            'phpVer'  => PHP_VERSION,
            'mailDrv' => strtoupper((string) Config::secret('MAIL_DRIVER', 'smtp')),
            'appUrl'  => Config::appUrl(),
            'wib'     => date('d M Y H:i:s'),
        ]);
    }

    /** GET /robots.txt — SEO: arahkan crawler, sembunyikan area privat. */
    public function robots(Request $req): Response
    {
        $base = Config::appUrl();
        $body = "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /zszdgj\n"
            . "Disallow: /owner\n"
            . "Disallow: /admin\n"
            . "Disallow: /dashboard\n"
            . "Disallow: /api/\n"
            . "\nSitemap: {$base}/sitemap.xml\n";
        return new Response($body, 200, 'text/plain; charset=utf-8');
    }

    /** GET /sitemap.xml — SEO: indeks halaman publik untuk Google/Bing. */
    public function sitemap(Request $req): Response
    {
        $base = Config::appUrl();
        $urls = ['/' => '1.0', '/info' => '0.8', '/links' => '0.7', '/store' => '0.8', '/redeem' => '0.6', '/games' => '0.6', '/login' => '0.5', '/register' => '0.5'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $path => $prio) {
            $xml .= '  <url><loc>' . htmlspecialchars($base . $path, ENT_XML1) . '</loc>'
                . '<changefreq>daily</changefreq><priority>' . $prio . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';
        return new Response($xml, 200, 'application/xml; charset=utf-8');
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
