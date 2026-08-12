<?php

declare(strict_types=1);

namespace ChiperX\Middleware;

use ChiperX\Core\Request;
use ChiperX\Core\Session;

/**
 * Wajib login. AJAX → 401 JSON, browser → redirect /login.
 */
final class AuthMiddleware
{
    public function handle(Request $request): bool
    {
        if (is_logged_in()) {
            return true;
        }
        if ($request->isAjax()) {
            \ChiperX\Core\Response::json(['ok' => false, 'message' => 'Sesi berakhir, silakan login ulang.'], 401)->send();
            return false;
        }
        Session::set('intended', $request->path);
        flash('warning', 'Silakan login terlebih dahulu.');
        header('Location: /login', true, 302);
        return false;
    }
}
