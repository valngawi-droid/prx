<?php

declare(strict_types=1);

namespace ChiperX\Middleware;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Core\View;

/**
 * God Mode — HANYA role owner.
 */
final class OwnerMiddleware
{
    public function handle(Request $request): bool
    {
        $user = auth_user();
        if (!$user) {
            header('Location: /login', true, 302);
            return false;
        }
        if ($user['role'] !== 'owner') {
            Response::html(View::render('errors/403', [], 'layouts/main'), 403)->send();
            return false;
        }
        // Lapis kedua: gerbang rahasia wajib terbuka dalam sesi ini
        if (!\ChiperX\Services\GateService::isUnlocked()) {
            if (!\ChiperX\Core\Session::get('intended')) {
                \ChiperX\Core\Session::set('intended', $_SERVER['REQUEST_URI'] ?? '/owner');
            }
            header('Location: ' . \ChiperX\Services\GateService::PATH, true, 302);
            return false;
        }
        return true;
    }
}
