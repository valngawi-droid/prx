<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Csrf;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\GameHistory;
use ChiperX\Services\GameService;
use ChiperX\Services\PlayTicketService;

/**
 * Mini Games: tampilan + endpoint bermain (JSON).
 */
final class GameController extends Controller
{
    public function index(Request $req): string
    {
        $user = auth_user();
        $tickets = PlayTicketService::ensureFresh($user);
        return $this->view('games/index', [
            'title'      => 'Mini Games',
            'user'       => $user,
            'tickets'    => $tickets,
            'maxTickets' => (int) (\ChiperX\Models\Setting::get('daily_tickets') ?? '3'),
            'history'    => GameHistory::forUser((int) $user['id'], 8),
            'weights'    => GameService::allWeights(), // untuk render segmen roda
        ]);
    }

    /** POST /games/play — JSON {ok, message, result, tickets_left, coin_balance} */
    public function play(Request $req): Response
    {
        if (!Csrf::validateRequest()) {
            return $this->json(['ok' => false, 'message' => 'Token CSRF tidak valid. Muat ulang halaman.'], 419);
        }
        $user = auth_user();
        $body = $req->json();
        $game = (string) ($body['game'] ?? $req->input('game', ''));

        if (!GameService::gameExists($game)) {
            return $this->json(['ok' => false, 'message' => 'Game tidak dikenal.'], 422);
        }
        return $this->json(GameService::play($user, $game));
    }
}
