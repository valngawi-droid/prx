<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

use ChiperX\Core\Config;
use ChiperX\Core\Env;

/**
 * Driver Paydisini.
 * Docs: https://paydisini.co.id/docs/api/
 * Signature callback: MD5(unique_code + api_key + status_code) — sesuai dokumentasi resmi.
 */
final class PaydisiniGateway implements PaymentGatewayInterface
{
    private function baseUrl(): string
    {
        return Config::secretBool('PAYDISINI_SANDBOX', true) ? 'https://api-sandbox.paydisini.co.id/v1/' : 'https://api.paydisini.co.id/v1/';
    }

    public function createInvoice(array $transaction, array $product, array $user, string $method): array
    {
        $apiKey = (string) Config::secret('PAYDISINI_API_KEY', '');
        if ($apiKey === '') {
            throw new \RuntimeException('PAYDISINI_API_KEY belum diisi di .env');
        }
        $uniqueCode = 'CX' . $transaction['id'] . 'T' . time();
        $serviceId  = $this->serviceId($method); // 11 = QRIS, 13 = DANA, dst.
        $signature  = md5($apiKey . $uniqueCode . 'NewTransaction');

        $payload = [
            'key'             => $apiKey,
            'request'         => 'new',
            'unique_code'     => $uniqueCode,
            'service'         => $serviceId,
            'amount'          => (int) $transaction['amount'],
            'note'            => $product['name'],
            'valid_time'      => 86400,
            'customer_email'  => $user['email'],
            'return_url'      => Config::appUrl() . '/store/checkout/' . $uniqueCode,
            'type_fee'        => 1,
            'payment_guide'   => true,
            'signature'       => $signature,
        ];
        $res = HttpClient::request('POST', $this->baseUrl(), http_build_query($payload), ['Content-Type: application/x-www-form-urlencoded']);
        $d = $res['body']['data'] ?? [];
        if (($res['body']['success'] ?? false) !== true || empty($d)) {
            throw new \RuntimeException($res['body']['msg'] ?? 'Gateway Paydisini menolak permintaan.');
        }
        return [
            'ref'          => $uniqueCode,
            'checkout_url' => $d['checkout_url'] ?? ($d['checkout_url_v2'] ?? null),
            'qr_url'       => $d['qr_content'] ?? null,
            'expired_at'   => isset($d['expired']) ? date('Y-m-d H:i:s', (int) $d['expired']) : null,
            'method_label' => 'PAYDISINI ' . strtoupper($method),
        ];
    }

    public function verifyCallback(string $rawBody, array $headers): ?array
    {
        parse_str($rawBody, $data);
        if (!is_array($data) || empty($data['unique_code'])) {
            return null;
        }
        $apiKey   = (string) Config::secret('PAYDISINI_API_KEY', '');
        $expected = md5((string) $data['unique_code'] . $apiKey . (string) ($data['status_code'] ?? ''));
        if ($apiKey === '' || empty($data['signature']) || !hash_equals($expected, (string) $data['signature'])) {
            return null;
        }
        $status = ((string) ($data['status'] ?? '') === 'Success') ? 'paid' : 'failed';
        return ['ref' => (string) $data['unique_code'], 'status' => $status];
    }

    private function serviceId(string $method): int
    {
        return match (strtolower($method)) {
            'qris'        => 11,
            'dana', 'ovo' => 13,
            'gopay'       => 16,
            default       => 11,
        };
    }

    /** Uji: request daftar channel pembayaran. */
    public function testConnection(): array
    {
        $key = (string) Config::secret('PAYDISINI_API_KEY', '');
        if ($key === '') {
            return ['ok' => false, 'message' => 'PAYDISINI_API_KEY belum diisi.'];
        }
        $res = HttpClient::request('POST', $this->baseUrl(), http_build_query([
            'key'     => $key,
            'request' => 'payment_channel',
        ]), ['Content-Type: application/x-www-form-urlencoded']);
        if (!empty($res['body']['success'])) {
            return ['ok' => true, 'message' => 'Terhubung ✔ — channel pembayaran tersedia.'];
        }
        return ['ok' => false, 'message' => 'Ditolak: ' . (string) ($res['body']['msg'] ?? ('HTTP ' . $res['code']))];
    }
}
