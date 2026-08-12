<?php

declare(strict_types=1);

namespace ChiperX\Services;

/**
 * Konteks token API aktif untuk request berjalan (diisi ApiTokenMiddleware).
 */
final class ApiAuth
{
    /** @var array<string, mixed>|null */
    private static ?array $token = null;

    public static function setToken(array $token): void
    {
        self::$token = $token;
    }

    /** @return array<string, mixed>|null */
    public static function token(): ?array
    {
        return self::$token;
    }

    /** Cek scope token. Scope '*' = akses penuh. */
    public static function hasScope(string $scope): bool
    {
        if (self::$token === null) {
            return false;
        }
        $scopes = array_map('trim', explode(',', (string) (self::$token['scopes'] ?? '')));
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
