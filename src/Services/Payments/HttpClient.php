<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

/**
 * HTTP client kecil berbasis cURL untuk komunikasi API gateway.
 */
final class HttpClient
{
    /** @return array{code:int, body:array<string, mixed>} */
    public static function post(string $url, array $payload, array $headers = []): array
    {
        return self::request('POST', $url, json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}', array_merge(['Content-Type: application/json'], $headers));
    }

    /** @return array{code:int, body:array<string, mixed>} */
    public static function request(string $method, string $url, ?string $body, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Ekstensi PHP "curl" belum terpasang (di Termux: pkg install php-curl). Pembayaran online butuh ekstensi ini.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return ['code' => $code, 'body' => is_array($decoded) ? $decoded : []];
    }
}
