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
            'posts'  => $this->safePosts((int) $user['id']),
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
        return $this->view('profile/show', [
            'title'      => $target['name'],
            'p'          => $this->stats($tid),
            'target'     => $target,
            'isSelf'     => $me && (int) $me['id'] === $tid,
            'posts'      => $this->safePosts($tid),
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

    /** Postingan profil — aman bila tabel sosial belum dimigrasi. */
    private function safePosts(int $userId): array
    {
        try {
            return Post::forUser($userId, 9);
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
