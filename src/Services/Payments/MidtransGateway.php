<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

use ChiperX\Core\Config;
use ChiperX\Core\Env;

/**
 * Driver Midtrans (Snap).
 * Docs: https://docs.midtrans.com
 * Signature notifikasi: SHA512(order_id + status_code + gross_amount + serverKey)
 */
final class MidtransGateway implements PaymentGatewayInterface
{
    private function baseUrl(): string
    {
        return Config::secretBool('MIDTRANS_SANDBOX', true)
            ? 'https://app.sandbox.midtrans.com/snap/v1'
            : 'https://app.midtrans.com/snap/v1';
    }

    public function createInvoice(array $transaction, array $product, array $user, string $method): array
    {
        $serverKey = (string) Config::secret('MIDTRANS_SERVER_KEY', '');
        if ($serverKey === '') {
            throw new \RuntimeException('MIDTRANS_SERVER_KEY belum diisi di .env');
        }
        $orderId = 'CX-' . $transaction['id'] . '-' . time();
        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $transaction['amount'],
            ],
            'customer_details' => [
                'first_name' => $user['name'],
                'email'      => $user['email'],
            ],
            'item_details' => [[
                'id'       => $product['slug'],
                'price'    => (int) $transaction['amount'],
                'quantity' => 1,
                'name'     => mb_substr($product['name'], 0, 50),
            ]],
            'expiry' => ['unit' => 'hours', 'duration' => 24],
        ];
        $auth = 'Authorization: Basic ' . base64_encode($serverKey . ':');
        $res  = HttpClient::post($this->baseUrl() . '/transactions', $payload, [$auth]);
        if (empty($res['body']['token'])) {
            throw new \RuntimeException($res['body']['error_messages'][0] ?? 'Gateway Midtrans menolak permintaan.');
        }
        return [
            'ref'          => $orderId,
            'checkout_url' => $res['body']['redirect_url'] ?? null,
            'qr_url'       => null,
            'expired_at'   => date('Y-m-d H:i:s', time() + 86400),
            'method_label' => 'SNAP ' . strtoupper($method),
        ];
    }

    public function verifyCallback(string $rawBody, array $headers): ?array
    {
        $serverKey = (string) Config::secret('MIDTRANS_SERVER_KEY', '');
        $data      = json_decode($rawBody, true);
        if (!is_array($data) || empty($data['order_id'])) {
            return null;
        }
        $raw = ($data['order_id'] ?? '') . ($data['status_code'] ?? '') . ($data['gross_amount'] ?? '') . $serverKey;
        if (!hash_equals(hash('sha512', $raw), (string) ($data['signature_key'] ?? ''))) {
            return null;
        }
        $txStatus  = $data['transaction_status'] ?? '';
        $fraud     = $data['fraud_status'] ?? 'accept';
        $status = match (true) {
            $txStatus === 'capture' && $fraud === 'accept' => 'paid',
            $txStatus === 'settlement'                     => 'paid',
            $txStatus === 'expire'                         => 'expired',
            in_array($txStatus, ['cancel', 'deny'], true)  => 'failed',
            default                                        => 'failed',
        };
        return ['ref' => (string) $data['order_id'], 'status' => $status];
    }

    /** Uji: GET status order fiktif — 404 berarti key diterima (tanpa membuat transaksi). */
    public function testConnection(): array
    {
        $serverKey = (string) Config::secret('MIDTRANS_SERVER_KEY', '');
        if ($serverKey === '') {
            return ['ok' => false, 'message' => 'MIDTRANS_SERVER_KEY belum diisi.'];
        }
        $api = Config::secretBool('MIDTRANS_SANDBOX', true)
            ? 'https://api.sandbox.midtrans.com/v2/CX-CONNTEST/status'
            : 'https://api.midtrans.com/v2/CX-CONNTEST/status';
        $res = HttpClient::request('GET', $api, null, [
            'Authorization: Basic ' . base64_encode($serverKey . ':'),
            'Accept: application/json',
        ]);
        if ($res['code'] === 401) {
            return ['ok' => false, 'message' => 'Ditolak (401) — Server Key salah.'];
        }
        if (in_array($res['code'], [200, 404], true)) {
            $mode = Config::secretBool('MIDTRANS_SANDBOX', true) ? 'sandbox' : 'production';
            return ['ok' => true, 'message' => "Server Key DITERIMA ✔ ({$mode})."];
        }
        return ['ok' => false, 'message' => 'HTTP ' . $res['code'] . ' — respon tidak dikenal.'];
    }
}
