<?php

declare(strict_types=1);

namespace ChiperX\Middleware;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\ApiToken;
use ChiperX\Services\ApiAuth;
use ChiperX\Services\AuditLogger;

/**
 * Validasi Bearer token untuk endpoint /api/v1/*.
 * Header: Authorization: Bearer cx_...
 */
final class ApiTokenMiddleware
{
    public function handle(Request $request): bool
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            Response::json(['ok' => false, 'message' => 'Header Authorization: Bearer <token> wajib diisi.'], 401)->send();
            return false;
        }
        $token = ApiToken::findActiveByPlain($m[1]);
        if ($token === null) {
            Response::json(['ok' => false, 'message' => 'Token tidak valid atau sudah dicabut.'], 403)->send();
            return false;
        }
        ApiToken::touch((int) $token['id']);
        ApiAuth::setToken($token);
        AuditLogger::record('api.call', ['token' => $token['name'], 'path' => $request->path], 'info', null, $request->ip());
        return true;
    }
}
