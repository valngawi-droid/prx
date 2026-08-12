<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Key-value settings dengan cache per-request.
 */
final class Setting
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value]
        );
        self::$cache[$key] = $value;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        self::load();
        return self::$cache ?? [];
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            foreach (Database::all('SELECT `key`, `value` FROM settings') as $row) {
                self::$cache[$row['key']] = (string) ($row['value'] ?? '');
            }
        } catch (\Throwable) {
            // DB belum siap (mis. saat instalasi) — pakai default
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
