<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Models\Transaction;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\DiscordWebhook;
use ChiperX\Services\Payments\PaymentManager;

/**
 * Penerima callback payment gateway. Endpoint ini PUBLIK tapi
 * diamankan oleh verifikasi SIGNATURE gateway (HMAC/SHA/MD5).
 */
final class WebhookController extends Controller
{
    /** POST /webhook/{driver} — tripay | midtrans | paydisini */
    public function handle(Request $req, array $params): \ChiperX\Core\Response
    {
        $driver = PaymentManager::driverByName((string) $params['driver']);
        if (!$driver) {
            return $this->json(['success' => false, 'message' => 'driver tidak dikenal'], 404);
        }

        $rawBody = file_get_contents('php://input') ?: '';
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }

        $event = $driver->verifyCallback($rawBody, $headers);
        if ($event === null) {
            AuditLogger::record('webhook.invalid_signature', ['driver' => $params['driver']], 'critical', null, $req->ip());
            return $this->json(['success' => false, 'message' => 'signature tidak valid'], 403);
        }

        $tx = Transaction::findByRef($event['ref']);
        if (!$tx) {
            return $this->json(['success' => false, 'message' => 'transaksi tidak ditemukan'], 404);
        }
        if ($tx['status'] !== 'pending') {
            return $this->json(['success' => true, 'message' => 'sudah diproses']);
        }

        if ($event['status'] === 'paid') {
            Database::transaction(function () use ($tx) {
                Transaction::markPaid((int) $tx['id']);
            });
            $buyer   = User::find((int) $tx['user_id']);
            $product = \ChiperX\Models\Product::find((int) $tx['product_id']);
            AuditLogger::record('webhook.paid', ['ref' => $event['ref']], 'info', (int) $tx['user_id'], $req->ip());
            DiscordWebhook::logPurchase(
                $buyer['email'] ?? '-',
                $product['name'] ?? '-',
                (int) $tx['amount'],
                (string) $tx['payment_method'],
                $event['ref']
            );
            return $this->json(['success' => true]);
        }

        Transaction::markFailed((int) $tx['id'], $event['status']);
        \ChiperX\Models\Product::restoreStock((int) $tx['product_id']); // stok kembali saat gagal/expired
        AuditLogger::record('webhook.' . $event['status'], ['ref' => $event['ref']], 'warning', (int) $tx['user_id'], $req->ip());
        return $this->json(['success' => true]);
    }
}
