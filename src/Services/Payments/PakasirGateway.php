<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

use ChiperX\Core\Config;
use ChiperX\Core\Env;

/**
 * Driver Pakasir — paling gampang untuk QRIS (buatannya developer Indonesia).
 * Docs: https://pakasir.com/dokumentasi
 *
 *  - Buat pembayaran: cukup redirect ke halaman bayar hosted (tanpa API create).
 *  - Webhook dikirim sebagai JSON; tanpa signature → diverifikasi ulang via
 *    API transactiondetail (mencegah webhook palsu).
 */
final class PakasirGateway implements PaymentGatewayInterface
{
    private function slug(): string
    {
        return (string) Config::secret('PAKASIR_SLUG', '');
    }

    public function createInvoice(array $transaction, array $product, array $user, string $method): array
    {
        $slug = $this->slug();
        if ($slug === '') {
            throw new \RuntimeException('PAKASIR_SLUG belum diisi di .env');
        }
        $orderId = 'CX-' . $transaction['id'] . '-' . time();
        $amount  = (int) $transaction['amount'];

        // Halaman bayar hosted Pakasir (QRIS)
        $checkoutUrl = 'https://pakasir.com/pay/' . rawurlencode($slug) . '/' . $amount
            . '?order_id=' . rawurlencode($orderId)
            . '&redirect=' . rawurlencode(Config::appUrl() . '/store/checkout/' . $orderId)
            . '&qris_only=1';

        return [
            'ref'          => $orderId,
            'checkout_url' => $checkoutUrl,
            'qr_url'       => null,
            'expired_at'   => date('Y-m-d H:i:s', time() + 86400),
            'method_label' => 'PAKASIR QRIS',
        ];
    }

    public function verifyCallback(string $rawBody, array $headers): ?array
    {
        $data = json_decode($rawBody, true);
        if (!is_array($data) || empty($data['order_id'])) {
            return null;
        }
        $incoming = (string) ($data['status'] ?? '');
        if ($incoming !== 'completed') {
            return ['ref' => (string) $data['order_id'], 'status' => 'failed'];
        }

        // Verifikasi ulang ke API Pakasir (webhook tidak bertanda tangan)
        $apiKey = (string) Config::secret('PAKASIR_API_KEY', '');
        if ($apiKey === '' || $this->slug() === '') {
            return null; // tanpa API key kita TIDAK mempercayai webhook
        }
        $query = http_build_query([
            'project'  => $this->slug(),
            'amount'   => (int) ($data['amount'] ?? 0),
            'order_id' => (string) $data['order_id'],
            'api_key'  => $apiKey,
        ]);
        $res = HttpClient::request('GET', 'https://pakasir.com/api/transactiondetail?' . $query, null);
        $status = (string) ($res['body']['transaction']['status'] ?? '');
        if ($status === 'completed') {
            return ['ref' => (string) $data['order_id'], 'status' => 'paid'];
        }
        return ['ref' => (string) $data['order_id'], 'status' => 'failed'];
    }

    /** Uji: transactiondetail dengan order fiktif — 200/404 = kredensial diterima. */
    public function testConnection(): array
    {
        $slug = $this->slug();
        $key  = (string) Config::secret('PAKASIR_API_KEY', '');
        if ($slug === '' || $key === '') {
            return ['ok' => false, 'message' => 'PAKASIR_SLUG / PAKASIR_API_KEY belum diisi.'];
        }
        $query = http_build_query([
            'project'  => $slug,
            'amount'   => 1000,
            'order_id' => 'CX-CONNTEST',
            'api_key'  => $key,
        ]);
        $res = HttpClient::request('GET', 'https://pakasir.com/api/transactiondetail?' . $query, null);
        if (in_array($res['code'], [401, 403], true)) {
            return ['ok' => false, 'message' => 'Ditolak — API key salah.'];
        }
        if (in_array($res['code'], [200, 404], true)) {
            return ['ok' => true, 'message' => 'Kredensial DITERIMA ✔ (transaksi uji memang tidak ada — normal).'];
        }
        return ['ok' => false, 'message' => 'HTTP ' . $res['code'] . ' — respon tidak dikenal.'];
    }
}
