<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Pembungkus request HTTP dengan sanitasi input bawaan.
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Method spoofing untuk form HTML (PUT/DELETE)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoof = strtoupper((string) $_POST['_method']);
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoof;
            }
        }
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($uri), '/');
        return new self($method, $path === '//' ? '/' : ($path === '/' ? '/' : rtrim($path, '/')));
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function str(string $key, string $default = '', int $maxLen = 500): string
    {
        $v = trim((string) ($this->input($key, $default)));
        return mb_substr($v, 0, $maxLen);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->input($key, $default);
    }

    public function email(string $key): ?string
    {
        $v = strtolower($this->str($key, '', 190));
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
    }

    public function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function ip(): string
    {
        // Hormati reverse proxy tepercaya (Cloudflare/Nginx)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return substr($ip, 0, 45);
    }

    public function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 250);
    }

    public function isAjax(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
}
