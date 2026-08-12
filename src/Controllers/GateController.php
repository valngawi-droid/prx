<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Csrf;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Core\Session;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\DiscordWebhook;
use ChiperX\Services\GateService;

/**
 * Gerbang rahasia panel admin/owner — /zszdgj/login.
 *
 * Alur keamanan berlapis (tahan brute-force ala Kali/hydra):
 *  1. Hanya bisa diakses SETELAH login OTP (user_id di session) DAN role admin/owner.
 *  2. Username + password diverifikasi terhadap HASH bcrypt (tidak ada plaintext).
 *  3. 5x gagal / 10 mnt → IP terkunci 10 menit + jeda 2 detik per percobaan gagal.
 *  4. Semua kejadian (gagal/berhasil/terkunci) tercatat di audit log + Discord.
 */
final class GateController extends Controller
{
    /** GET /zszdgj/login — form lapis kedua. */
    public function show(Request $req): Response|string
    {
        $user = auth_user();
        if (!$user) {
            flash('warning', 'Masuk dengan OTP terlebih dahulu.');
            return redirect('/login');
        }
        if (!in_array($user['role'], ['admin', 'owner'], true)) {
            return $this->forbidden();
        }
        if (GateService::isUnlocked()) {
            return redirect($user['role'] === 'owner' ? '/owner' : '/admin');
        }
        return $this->view('auth/gate', [
            'title'    => 'Area Terbatas',
            'lockedFor' => GateService::lockedSeconds($req->ip()),
        ]);
    }

    /** POST /zszdgj/login — verifikasi username + password (hashed). */
    public function login(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['admin', 'owner'], true)) {
            return redirect('/login');
        }

        $ip = $req->ip();
        $lockedFor = GateService::lockedSeconds($ip);
        if ($lockedFor > 0) {
            AuditLogger::record('gate.locked_attempt', ['email' => $user['email']], 'warning', (int) $user['id'], $ip, $req->userAgent());
            flash('error', "Terlalu banyak percobaan. IP terkunci — coba lagi dalam {$lockedFor} detik.");
            return redirect(GateService::PATH);
        }

        $username = $req->str('username', '', 60);
        $password = $req->str('password', '', 200);

        if (!GateService::verify($username, $password)) {
            GateService::recordFail($ip);
            sleep(2); // jeda anti brute-force — membuat serangan kamus sangat lambat
            AuditLogger::record('gate.failed', ['email' => $user['email'], 'user_input' => $username], 'warning', (int) $user['id'], $ip, $req->userAgent());
            DiscordWebhook::send('🛡️ Percobaan Akses Gerbang GAGAL', '', DiscordWebhook::COLOR_DANGER, [
                ['name' => 'Akun', 'value' => $user['email'], 'inline' => true],
                ['name' => 'IP', 'value' => $ip, 'inline' => true],
            ]);
            flash('error', 'Kredensial salah. Percobaan dicatat.');
            return redirect(GateService::PATH);
        }

        GateService::clearFails($ip);
        GateService::unlock();
        AuditLogger::record('gate.unlocked', ['email' => $user['email']], 'info', (int) $user['id'], $ip, $req->userAgent());
        DiscordWebhook::send('🔓 Gerbang Panel Terbuka', 'Verifikasi lapis kedua berhasil.', DiscordWebhook::COLOR_SUCCESS, [
            ['name' => 'Akun', 'value' => $user['email'], 'inline' => true],
            ['name' => 'IP', 'value' => $ip, 'inline' => true],
        ]);
        flash('success', 'Akses panel diberikan. Selamat bertugas, ' . ($user['name'] ?? 'bos') . '! 🛡️');

        $intended = Session::get('intended');
        Session::forget('intended');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            return redirect($intended);
        }
        return redirect($user['role'] === 'owner' ? '/owner' : '/admin');
    }

    /** POST /zszdgj/logout — kunci kembali gerbang (tanpa logout akun). */
    public function logout(Request $req): Response
    {
        Csrf::abortIfInvalid();
        GateService::forget();
        flash('success', 'Gerbang panel dikunci kembali. 🔒');
        return redirect('/');
    }

    private function forbidden(): Response
    {
        http_response_code(403);
        return Response::html(\ChiperX\Core\View::render('errors/403', ['title' => 'Akses Ditolak'], 'layouts/main'), 403);
    }
}
