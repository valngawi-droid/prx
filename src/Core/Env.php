<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Loader file .env tanpa dependensi eksternal.
 * Nilai yang sudah ada di environment server tidak ditimpa.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !is_file($path)) {
            self::$loaded = true;
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $val = $_ENV[$key] ?? getenv($key);
        return ($val === false || $val === null || $val === '') ? $default : (string) $val;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === null) {
            return $default;
        }
        return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }

    /** Ambil nilai env sebagai integer (nilai non-numerik → default). */
    public static function int(string $key, int $default = 0): int
    {
        $val = self::get($key);
        return ($val === null || !is_numeric($val)) ? $default : (int) $val;
    }
}
