<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\GameHistory;
use ChiperX\Models\Product;
use ChiperX\Models\Transaction;
use ChiperX\Models\User;
use ChiperX\Services\ApiAuth;
use ChiperX\Services\LeaderboardService;

/**
 * Public API v1 — dirancang untuk integrasi bot Discord / tools komunitas.
 * Semua endpoint wajib Bearer token (lihat ApiTokenMiddleware).
 *
 *   GET /api/v1/stats            (scope: stats)
 *   GET /api/v1/leaderboard      (scope: leaderboard)
 *   GET /api/v1/user/{email}     (scope: user)
 */
final class ApiController extends Controller
{
    private function needScope(string $scope): ?Response
    {
        if (ApiAuth::hasScope($scope)) {
            return null;
        }
        return $this->json(['ok' => false, 'message' => "Token tidak memiliki scope '{$scope}'."], 403);
    }

    public function stats(Request $req): Response
    {
        if ($resp = $this->needScope('stats')) {
            return $resp;
        }
        return $this->json([
            'ok'   => true,
            'data' => [
                'total_users'              => User::totalCount(),
                'total_transactions_paid'  => Transaction::totalPaidCount(),
                'total_products'           => Product::totalCount(),
                'total_games_played'       => GameHistory::totalPlays(),
                'coins_in_circulation'     => (int) Database::value('SELECT COALESCE(SUM(coin_balance),0) FROM users'),
            ],
            'server_time' => date('c'),
        ]);
    }

    public function leaderboard(Request $req): Response
    {
        if ($resp = $this->needScope('leaderboard')) {
            return $resp;
        }
        $allTime = array_map(static fn (array $u) => [
            'name' => $u['name'], 'coins' => (int) $u['coin_balance'],
        ], User::topCoins(10));
        $weekly = array_map(static fn (array $u) => [
            'name' => $u['name'], 'earned_this_week' => (int) $u['earned'],
        ], LeaderboardService::weeklyTop(5));

        return $this->json(['ok' => true, 'data' => ['all_time' => $allTime, 'weekly' => $weekly]]);
    }

    public function userShow(Request $req, array $params): Response
    {
        if ($resp = $this->needScope('user')) {
            return $resp;
        }
        $email = strtolower(trim((string) ($params['email'] ?? '')));
        $user  = User::findByEmail($email);
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'Pengguna tidak ditemukan.'], 404);
        }
        // Data minim — TIDAK membocorkan email/saldo sensitif lebih dari yang di-query
        return $this->json([
            'ok'   => true,
            'data' => [
                'name'         => $user['name'],
                'coin_balance' => (int) $user['coin_balance'],
                'play_tickets' => (int) $user['play_tickets'],
                'role'         => $user['role'],
                'member_since' => $user['created_at'],
            ],
        ]);
    }
}
