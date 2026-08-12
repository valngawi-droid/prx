<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Csrf;
use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Core\Session;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;

/**
 * Profil pengguna:
 *  - GET  /profil          → profil sendiri (edit nama; owner bisa atur tags kustom diri)
 *  - POST /profil          → simpan perubahan
 *  - GET  /u/{username}    → profil publik siapa pun
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

        // HANYA owner yang boleh menulis tags kustom pada profilnya sendiri
        if (($user['role'] ?? 'user') === 'owner' && $req->input('badges') !== null) {
            User::setBadges((int) $user['id'], $req->str('badges', '', 190));
            AuditLogger::record('profile.update_badges', ['badges' => $req->str('badges', '', 190)], 'info', (int) $user['id'], $req->ip());
        }

        flash('success', 'Profil diperbarui. ✨');
        return redirect('/profil');
    }

    public function publicShow(Request $req, array $params): Response|string
    {
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if (!$target) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => 'Pengguna tidak ditemukan']);
        }
        $me = auth_user();
        return $this->view('profile/show', [
            'title'  => $target['name'],
            'p'      => $this->stats((int) $target['id']),
            'target' => $target,
            'isSelf' => $me && (int) $me['id'] === (int) $target['id'],
        ]);
    }

    /** Statistik ringkas untuk kartu profil. */
    private function stats(int $userId): array
    {
        return [
            'games'   => (int) Database::value('SELECT COUNT(*) FROM game_history WHERE user_id = ?', [$userId]),
            'orders'  => (int) Database::value("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'paid'", [$userId]),
            'wins'    => (int) Database::value("SELECT COUNT(*) FROM game_history WHERE user_id = ? AND reward > 0", [$userId]),
        ];
    }
}
