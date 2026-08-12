<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

use ChiperX\Core\Config;
use ChiperX\Core\Env;

/**
 * Driver Duitku (merchant API v2) — gampang & populer untuk QRIS/e-wallet/VA.
 * Docs: https://docs.duitku.com
 * Signature create  : MD5(merchantCode + merchantOrderId + amount + apiKey)
 * Signature callback: MD5(merchantCode + amount + merchantOrderId + apiKey)
 */
final class DuitkuGateway implements PaymentGatewayInterface
{
    private function baseUrl(): string
    {
        return Config::secretBool('DUITKU_SANDBOX', true)
            ? 'https://sandbox.duitku.com/webapi/api/merchant'
            : 'https://passport.duitku.com/webapi/api/merchant';
    }

    public function createInvoice(array $transaction, array $product, array $user, string $method): array
    {
        $merchantCode = (string) Config::secret('DUITKU_MERCHANT_CODE', '');
        $apiKey       = (string) Config::secret('DUITKU_API_KEY', '');
        if ($merchantCode === '' || $apiKey === '') {
            throw new \RuntimeException('DUITKU_MERCHANT_CODE / DUITKU_API_KEY belum diisi di .env');
        }

        $orderId = 'CX-' . $transaction['id'] . '-' . time();
        $amount  = (int) $transaction['amount'];

        $payload = [
            'merchantCode'    => $merchantCode,
            'paymentAmount'   => $amount,
            'paymentMethod'   => $method === 'QRIS' ? 'SP' : $method, // SP = QRIS di Duitku
            'merchantOrderId' => $orderId,
            'productDetails'  => mb_substr($product['name'], 0, 200),
            'customerVaName'  => mb_substr($user['name'], 0, 50),
            'email'           => $user['email'],
            'callbackUrl'     => Config::appUrl() . '/webhook/duitku',
            'returnUrl'       => Config::appUrl() . '/store/checkout/' . $orderId,
            'expiryPeriod'    => 1440, // menit (24 jam)
            'signature'       => md5($merchantCode . $orderId . $amount . $apiKey),
        ];

        $res = HttpClient::post($this->baseUrl() . '/v2/inquiry', $payload);
        $body = $res['body'];
        if (($body['statusCode'] ?? '') !== '00') {
            throw new \RuntimeException('Duitku menolak: ' . ($body['statusMessage'] ?? 'unknown error'));
        }
        return [
            'ref'          => $orderId,
            'checkout_url' => $body['paymentUrl'] ?? null,
            'qr_url'       => null,
            'expired_at'   => date('Y-m-d H:i:s', time() + 86400),
            'method_label' => 'DUITKU ' . strtoupper($method),
        ];
    }

    public function verifyCallback(string $rawBody, array $headers): ?array
    {
        parse_str($rawBody, $data);
        if (!is_array($data) || empty($data['merchantOrderId'])) {
            return null;
        }
        $apiKey   = (string) Config::secret('DUITKU_API_KEY', '');
        $raw      = (string) ($data['merchantCode'] ?? '') . (string) ($data['amount'] ?? '') . (string) $data['merchantOrderId'] . $apiKey;
        $expected = md5($raw);
        if ($apiKey === '' || !hash_equals($expected, (string) ($data['signature'] ?? ''))) {
            return null;
        }
        $status = match ((string) ($data['resultCode'] ?? '')) {
            '00'    => 'paid',
            '02'    => 'expired',
            default => 'failed',
        };
        return ['ref' => (string) $data['merchantOrderId'], 'status' => $status];
    }

    /** Uji: inquiry daftar metode pembayaran (signature md5 resmi). */
    public function testConnection(): array
    {
        $mc  = (string) Config::secret('DUITKU_MERCHANT_CODE', '');
        $key = (string) Config::secret('DUITKU_API_KEY', '');
        if ($mc === '' || $key === '') {
            return ['ok' => false, 'message' => 'DUITKU_MERCHANT_CODE / DUITKU_API_KEY belum diisi.'];
        }
        $dt  = date('Y-m-d H:i:s');
        $res = HttpClient::post($this->baseUrl() . '/paymentmethod/getpaymentmethod', [
            'merchantCode' => $mc,
            'amount'       => 10000,
            'datetime'     => $dt,
            'signature'    => md5($mc . '10000' . $dt . $key),
        ]);
        $methods = $res['body']['paymentFee'] ?? [];
        if (is_array($methods) && $methods !== []) {
            return ['ok' => true, 'message' => 'Terhubung ✔ — ' . count($methods) . ' metode pembayaran aktif.'];
        }
        return ['ok' => false, 'message' => 'Ditolak: ' . (string) ($res['body']['statusMessage'] ?? ('HTTP ' . $res['code']))];
    }
}
