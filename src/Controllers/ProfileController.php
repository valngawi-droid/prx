<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Csrf;
use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Core\Session;
use ChiperX\Models\Post;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;

/**
 * Profil pengguna:
 *  - GET  /profil          → profil sendiri (edit nama, bio; owner bisa atur tags kustom diri)
 *  - POST /profil          → simpan perubahan
 *  - GET  /u/{username}    → profil publik siapa pun (ala Instagram: bio + grid postingan)
 */
final class ProfileController extends Controller
{
    public function show(Request $req): Response|string
    {
        $user = auth_user();
        if (!$user) {
            return redirect('/login');
        }
        return $this->view('profile/show', [
            'title'  => 'Profil Saya',
            'p'      => $this->stats((int) $user['id']),
            'target' => $user,
            'isSelf' => true,
            'posts'  => $this->safePosts((int) $user['id'], true), // pemilik juga melihat arsipnya
        ]);
    }

    public function update(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $user = auth_user();
        if (!$user) {
            return redirect('/login');
        }

        $name = $req->str('name', '', 80);
        if ($name !== '' && $name !== $user['name']) {
            User::updateName((int) $user['id'], $name);
            AuditLogger::record('profile.update_name', [], 'info', (int) $user['id'], $req->ip());
        }

        // Bio singkat untuk profil publik (semua role)
        if ($req->input('bio') !== null) {
            User::updateBio((int) $user['id'], $req->str('bio', '', 160));
        }

        // HANYA owner yang boleh menulis tags kustom pada profilnya sendiri
        if (($user['role'] ?? 'user') === 'owner' && $req->input('badges') !== null) {
            User::setBadges((int) $user['id'], $req->str('badges', '', 190));
            AuditLogger::record('profile.update_badges', ['badges' => $req->str('badges', '', 190)], 'info', (int) $user['id'], $req->ip());
        }

        flash('success', 'Profil diperbarui. ✨');
        return redirect('/profil');
    }

    /** Preset warna aksen profil (neon aman di tema gelap). */
    private const ACCENTS = ['#a78bfa', '#22d3ee', '#4ade80', '#f472b6', '#fbbf24', '#f87171'];

    /**
     * POST /profil/visual — 🖼️ Avatar + 🎇 Sampul (upload LANGSUNG dari HP),
     * 💬 status/mood, 🎨 warna aksen profil.
     */
    public function updateVisual(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $user = auth_user();
        if (!$user) {
            return redirect('/login');
        }
        $uid  = (int) $user['id'];
        $cols = [];

        foreach (['avatar' => 'avatar', 'cover' => 'sampul'] as $field => $bucket) {
            $f = $_FILES[$field] ?? null;
            if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $cols[$field] = \ChiperX\Services\UploadService::saveImage($f, $bucket, 5);
                } catch (\RuntimeException $e) {
                    flash('error', ($field === 'avatar' ? 'Avatar' : 'Sampul') . ': ' . $e->getMessage());
                    return redirect('/profil');
                }
            }
        }
        if ($req->input('status_text') !== null) {
            $cols['status_text'] = mb_substr(trim($req->str('status_text', '', 60)), 0, 60);
        }
        $accent = strtolower($req->str('accent', '', 10));
        if ($accent !== '' && in_array($accent, self::ACCENTS, true)) {
            $cols['accent'] = $accent;
        }

        if ($cols !== []) {
            User::setVisuals($uid, $cols);
            AuditLogger::record('profile.update_visual', array_keys($cols), 'info', $uid, $req->ip());
        }
        flash('success', 'Tampilan profil diperbarui! Makin kece 😎✨');
        return redirect('/profil');
    }

    /** GET /pengaturan — pusat kendali akun (username, password, info). */
    public function settings(Request $req): Response|string
    {
        $user = auth_user();
        if (!$user) {
            return redirect('/login');
        }
        return $this->panel('profile/settings', [
            'title' => 'Pengaturan Akun',
            'user'  => $user,
            'hasPassword' => (string) ($user['password_hash'] ?? '') !== '',
        ]);
    }

    /** POST /pengaturan/username — ganti username unik. */
    public function saveUsername(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $user = auth_user();
        if (!$user) {
            return redirect('/login');
        }
        $new = mb_strtolower(trim($req->str('username', '', 24)));
        if (!preg_match('/^[a-z0-9_.]{3,20}$/', $new)) {
            flash('error', 'Username 3-20 karakter: huruf kecil, angka, titik, atau underscore saja.');
            return redirect('/pengaturan');
        }
        if ($new !== mb_strtolower((string) ($user['username'] ?? '')) && User::usernameTaken($new)) {
            flash('error', 'Username "@' . $new . '" sudah dipakai member lain. Coba yang lain ya!');
            return redirect('/pengaturan');
        }
        User::setUsername((int) $user['id'], $new);
        AuditLogger::record('profile.change_username', ['to' => $new], 'info', (int) $user['id'], $req->ip());
        flash('success', 'Username berhasil diganti → @' . $new . ' ✨');
        return redirect('/pengaturan');
    }

    /** POST /pengaturan/password — ganti password (verifikasi lama bila sudah ada). */
    public function savePassword(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $user = auth_user();
        if (!$user) {
            return redirect('/login');
        }
        $curHash = (string) ($user['password_hash'] ?? '');
        $cur     = $req->str('current_password', '', 200);
        $new     = $req->str('password', '', 200);
        $confirm = $req->str('password_confirm', '', 200);

        if ($curHash !== '' && !password_verify($cur, $curHash)) {
            flash('error', 'Password lama salah. Coba lagi ya!');
            return redirect('/pengaturan');
        }
        if (strlen($new) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
            return redirect('/pengaturan');
        }
        if ($new !== $confirm) {
            flash('error', 'Konfirmasi password tidak sama.');
            return redirect('/pengaturan');
        }
        User::setPassword((int) $user['id'], password_hash($new, User::hashAlgo()));
        AuditLogger::record('profile.change_password', [], 'warning', (int) $user['id'], $req->ip());
        flash('success', 'Password berhasil diganti! Akun makin aman 🔐');
        return redirect('/pengaturan');
    }

    /** GET /pencapaian — galeri achievement dengan status terbuka/terkunci. */
    public function achievements(Request $req): string
    {
        $user = auth_user();
        return $this->panel('dashboard/achievements', [
            'title'      => 'Pencapaian Saya',
            'badges'     => \ChiperX\Models\Achievement::withUnlockStatus((int) $user['id']),
            'badgeCount' => \ChiperX\Models\Achievement::unlockedCount((int) $user['id']),
            'badgeTotal' => \ChiperX\Models\Achievement::totalActive(),
        ]);
    }

    /** GET /u/{username} (lamanet) → alihkan ke format sosial /profil/@username. */
    public function legacyRedirect(Request $req, array $params): Response
    {
        $uname = ltrim((string) ($params['username'] ?? ''), '@');
        return redirect('/profil/@' . rawurlencode($uname));
    }

    public function publicShow(Request $req, array $params): Response|string
    {
        $uname  = ltrim((string) ($params['username'] ?? ''), '@');
        $target = User::findByUsername($uname);
        if (!$target) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => 'Pengguna tidak ditemukan']);
        }
        $me = auth_user();
        $tid = (int) $target['id'];
        // ⚡ AUTO-FIX profil OWNER yang masih default ("Escape the Ordinary"): pasang preset premium dev
        if (($target['role'] ?? '') === 'owner'
            && trim((string) ($target['avatar'] ?? '')) === ''
            && str_contains(strtolower((string) ($target['bio'] ?? '')), 'escape the ordinary')) {
            User::setVisuals($tid, [
                'avatar'      => 'owner.webp',
                'cover'       => 'owner-cover.webp',
                'status_text' => 'Halo, saya Dev / Super Owner AI-V Technology 🚀',
                'accent'      => '#22d3ee',
            ]);
            $target = User::find($tid) ?? $target;
        }
        return $this->view('profile/show', [
            'title'      => $target['name'],
            'p'          => $this->stats($tid),
            'target'     => $target,
            'isSelf'     => $me && (int) $me['id'] === $tid,
            'posts'      => $this->safePosts($tid, $me && (int) $me['id'] === $tid),
            'followers'  => \ChiperX\Models\Follow::followersCount($tid),
            'following'  => \ChiperX\Models\Follow::followingCount($tid),
            'isFollowing' => $me ? \ChiperX\Models\Follow::isFollowing((int) $me['id'], $tid) : false,
            'storyCount' => $this->safeStoryCount($tid),
        ]);
    }

    /** POST /profil/@{username}/follow — ikuti / batal mengikuti (atomik). */
    public function follow(Request $req, array $params): Response
    {
        Csrf::abortIfInvalid();
        $me = auth_user();
        if (!$me) {
            return redirect('/login');
        }
        $uname  = ltrim((string) ($params['username'] ?? ''), '@');
        $target = User::findByUsername($uname);
        if (!$target || (int) $target['id'] === (int) $me['id']) {
            flash('error', 'Tidak bisa mengikuti akun ini.');
            return redirect('/profil/@' . rawurlencode($uname));
        }
        $res = \ChiperX\Models\Follow::toggle((int) $me['id'], (int) $target['id']);
        if ($res['following']) {
            \ChiperX\Models\Notification::add((int) $target['id'], '➕ ' . $me['name'] . ' mulai mengikutimu!', null, '/profil/@' . rawurlencode((string) $me['username']));
            flash('success', 'Kamu sekarang mengikuti ' . $target['name'] . '! ➕');
        } else {
            flash('success', 'Berhenti mengikuti ' . $target['name'] . '.');
        }
        return redirect('/profil/@' . rawurlencode($uname));
    }

    /** Postingan profil — aman bila tabel sosial belum dimigrasi. Pemilik melihat juga arsipnya. */
    private function safePosts(int $userId, bool $includeArchived = false): array
    {
        try {
            return Post::forUser($userId, 9, $includeArchived);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Statistik ringkas untuk kartu profil. */
    private function stats(int $userId): array
    {
        return [
            'games'  => (int) (Database::value('SELECT COUNT(*) FROM game_history WHERE user_id = ?', [$userId]) ?? 0),
            'wins'   => (int) (Database::value('SELECT COUNT(*) FROM game_history WHERE user_id = ? AND reward > 0', [$userId]) ?? 0),
            'orders' => (int) (Database::value("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'paid'", [$userId]) ?? 0),
            'posts'  => $this->safeCount($userId),
        ];
    }

    private function safeCount(int $userId): int
    {
        try {
            return Post::countByUser($userId);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeStoryCount(int $userId): int
    {
        try {
            return \ChiperX\Models\Story::activeCount($userId);
        } catch (\Throwable) {
            return 0;
        }
    }
}
