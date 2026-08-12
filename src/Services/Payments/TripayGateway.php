<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

use ChiperX\Core\Config;
use ChiperX\Core\Env;

/**
 * Driver Tripay (payment closed).
 * Docs: https://tripay.co.id/developer
 * Signature callback: HMAC-SHA256(rawBody, privateKey)
 */
final class TripayGateway implements PaymentGatewayInterface
{
    private function baseUrl(): string
    {
        return Config::secretBool('TRIPAY_SANDBOX', true) ? 'https://tripay.co.id/api-sandbox' : 'https://tripay.co.id/api';
    }

    public function createInvoice(array $transaction, array $product, array $user, string $method): array
    {
        $apiKey       = (string) Config::secret('TRIPAY_API_KEY', '');
        $privateKey   = (string) Config::secret('TRIPAY_PRIVATE_KEY', '');
        $merchantCode = (string) Config::secret('TRIPAY_MERCHANT_CODE', '');
        if ($apiKey === '' || $privateKey === '' || $merchantCode === '') {
            throw new \RuntimeException('Kredensial Tripay belum diisi di .env');
        }

        $merchantRef = 'CX-' . $transaction['id'] . '-' . time();
        $amount      = (int) $transaction['amount'];
        $signature   = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);

        $payload = [
            'method'         => $method, // QRIS, BRIVA, OVO, DANA, dll.
            'merchant_ref'   => $merchantRef,
            'amount'         => $amount,
            'customer_name'  => $user['name'],
            'customer_email' => $user['email'],
            'customer_phone' => '08000000000',
            'order_items'    => [[
                'sku'      => $product['slug'],
                'name'     => $product['name'],
                'price'    => $amount,
                'quantity' => 1,
            ]],
            'return_url'   => Config::appUrl() . '/store/checkout/' . $merchantRef,
            'expired_time' => time() + (24 * 60 * 60),
            'signature'    => $signature,
        ];

        $res = HttpClient::post($this->baseUrl() . '/transaction/create', $payload, ['Authorization: Bearer ' . $apiKey]);
        if (($res['body']['success'] ?? false) !== true) {
            throw new \RuntimeException($res['body']['message'] ?? 'Gateway Tripay menolak permintaan.');
        }
        $d = $res['body']['data'];
        return [
            'ref'          => $merchantRef,
            'checkout_url' => $d['checkout_url'] ?? null,
            'qr_url'       => $d['qr_url'] ?? null,
            'expired_at'   => isset($d['expired_time']) ? date('Y-m-d H:i:s', (int) $d['expired_time']) : null,
            'method_label' => strtoupper($method),
        ];
    }

    public function verifyCallback(string $rawBody, array $headers): ?array
    {
        $privateKey = (string) Config::secret('TRIPAY_PRIVATE_KEY', '');
        $signature  = $headers['x-callback-signature'] ?? '';
        $valid      = hash_hmac('sha256', $rawBody, $privateKey);
        if ($privateKey === '' || !hash_equals($valid, (string) $signature)) {
            return null;
        }
        $data = json_decode($rawBody, true);
        if (!is_array($data) || empty($data['merchant_ref'])) {
            return null;
        }
        $status = match ($data['status'] ?? '') {
            'PAID'    => 'paid',
            'EXPIRED' => 'expired',
            'FAILED'  => 'failed',
            default   => 'failed',
        };
        return ['ref' => (string) $data['merchant_ref'], 'status' => $status];
    }

    /** Uji: GET channel merchant — Bearer API key. */
    public function testConnection(): array
    {
        $apiKey = (string) Config::secret('TRIPAY_API_KEY', '');
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'TRIPAY_API_KEY belum diisi.'];
        }
        $res = HttpClient::request('GET', $this->baseUrl() . '/merchant/payment-channel', null, ['Authorization: Bearer ' . $apiKey]);
        if ($res['code'] === 200) {
            $mode = Config::secretBool('TRIPAY_SANDBOX', true) ? 'SANDBOX' : 'PRODUKSI';
            return ['ok' => true, 'message' => "Terhubung ✔ ({$mode}) — " . count((array) ($res['body']['data'] ?? [])) . ' channel aktif.'];
        }
        if (in_array($res['code'], [401, 403], true)) {
            return ['ok' => false, 'message' => 'Ditolak (HTTP ' . $res['code'] . ') — API key salah/kedaluwarsa.'];
        }
        return ['ok' => false, 'message' => 'HTTP ' . $res['code'] . ' — ' . (string) ($res['body']['message'] ?? 'respon tidak dikenal')];
    }
}
