<?php

declare(strict_types=1);

namespace ChiperX\Middleware;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Core\View;

/**
 * Panel Admin — role admin & owner.
 */
final class AdminMiddleware
{
    public function handle(Request $request): bool
    {
        $user = auth_user();
        if (!$user) {
            header('Location: /login', true, 302);
            return false;
        }
        if (!in_array($user['role'], ['admin', 'owner'], true)) {
            Response::html(View::render('errors/403', [], 'layouts/main'), 403)->send();
            return false;
        }
        // Lapis kedua: gerbang rahasia wajib terbuka dalam sesi ini
        if (!\ChiperX\Services\GateService::isUnlocked()) {
            if (!\ChiperX\Core\Session::get('intended')) {
                \ChiperX\Core\Session::set('intended', $_SERVER['REQUEST_URI'] ?? '/admin');
            }
            header('Location: ' . \ChiperX\Services\GateService::PATH, true, 302);
            return false;
        }
        return true;
    }
}
