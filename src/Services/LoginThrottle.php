<?php

declare(strict_types=1);

namespace ChiperX\Services;

/**
 * Throttle login password per-IP (file-based, tahan restart):
 * 5x gagal dalam 10 menit → kunci 10 menit. Ditambah jeda paksa di controller.
 */
final class LoginThrottle
{
    private const MAX_FAILS    = 5;
    private const LOCK_SECONDS = 600;
    private const FAIL_WINDOW  = 600;

    public static function lockedSeconds(string $ip): int
    {
        $state = self::read($ip);
        return max(0, (int) ($state['locked_until'] ?? 0) - time());
    }

    public static function recordFail(string $ip): void
    {
        $state = self::read($ip);
        $now   = time();
        $fails = array_filter((array) ($state['fails'] ?? []), static fn (int $ts): bool => $ts > $now - self::FAIL_WINDOW);
        $fails[] = $now;
        $state['fails'] = $fails;
        if (count($fails) >= self::MAX_FAILS) {
            $state['locked_until'] = $now + self::LOCK_SECONDS;
            $state['fails'] = [];
        }
        self::write($ip, $state);
    }

    public static function clear(string $ip): void
    {
        self::write($ip, ['fails' => [], 'locked_until' => 0]);
    }

    // ------------------------------------------------ internal ------------------------------------------------

    private static function file(string $ip): string
    {
        $dir = BASE_PATH . '/storage/cache/throttle';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . '/login_' . hash('sha256', $ip) . '.json';
    }

    /** @return array<string, mixed> */
    private static function read(string $ip): array
    {
        $file = self::file($ip);
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $state */
    private static function write(string $ip, array $state): void
    {
        @file_put_contents(self::file($ip), json_encode($state), LOCK_EX);
    }
}
