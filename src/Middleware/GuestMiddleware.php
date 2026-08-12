<?php

declare(strict_types=1);

namespace ChiperX\Middleware;

use ChiperX\Core\Request;

/**
 * Hanya untuk tamu (belum login). User aktif diarahkan ke dashboard-nya.
 */
final class GuestMiddleware
{
    public function handle(Request $request): bool
    {
        $user = auth_user();
        if ($user) {
            $target = match ($user['role']) {
                'owner' => '/owner',
                'admin' => '/admin',
                default => '/dashboard',
            };
            header('Location: ' . $target, true, 302);
            return false;
        }
        return true;
    }
}
