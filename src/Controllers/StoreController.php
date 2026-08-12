<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Product;
use ChiperX\Models\Transaction;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\Payments\PaymentManager;

/**
 * ChiperX Store: produk premium dengan auto-payment.
 * Alur: pilih produk → buat invoice gateway → halaman checkout →
 * webhook gateway → status PAID → file bisa diunduh.
 */
final class StoreController extends Controller
{
    public function index(Request $req): string
    {
        return $this->view('store/index', [
            'title'    => 'ChiperX Store',
            'products' => Product::forStore(),
            'methods'  => PaymentManager::availableMethods(),
        ]);
    }

    /** POST /store/buy/{id} — buat transaksi pending + invoice gateway. */
    public function buy(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user    = auth_user();
        $product = Product::find((int) $params['id']);
        $method  = strtoupper($req->str('method', 'QRIS', 20));

        if (!$product || !$product['is_active'] || $product['type'] !== 'premium_store') {
            flash('error', 'Produk tidak tersedia.');
            return redirect('/store');
        }
        if (!array_key_exists($method, PaymentManager::availableMethods())) {
            flash('error', 'Metode pembayaran tidak valid.');
            return redirect('/store');
        }

        try {
            $txId = Database::transaction(function () use ($user, $product, $method) {
                // Kunci baris user → serialisasi pembelian paralel (anti double-spend stok)
                Database::run('SELECT id FROM users WHERE id = ? FOR UPDATE', [$user['id']]);
                if (!Product::decrementStock((int) $product['id'])) {
                    throw new \RuntimeException('Stok produk habis.');
                }
                return Transaction::create([
                    'user_id'        => (int) $user['id'],
                    'product_id'     => (int) $product['id'],
                    'amount'         => (int) $product['price'],
                    'payment_method' => strtolower($method),
                    'status'         => 'pending',
                ]);
            });

            $tx      = Transaction::find($txId);
            $invoice = PaymentManager::driver()->createInvoice($tx, $product, $user, $method);

            Database::run(
                'UPDATE transactions SET gateway_ref = ?, checkout_url = ?, qr_url = ?, expired_at = ? WHERE id = ?',
                [$invoice['ref'], $invoice['checkout_url'], $invoice['qr_url'], $invoice['expired_at'], $txId]
            );
            AuditLogger::record('store.checkout_created', ['ref' => $invoice['ref'], 'product' => $product['name']], 'info', (int) $user['id'], $req->ip());
            return redirect('/store/checkout/' . rawurlencode($invoice['ref']));
        } catch (\Throwable $e) {
            AuditLogger::record('store.checkout_failed', ['error' => $e->getMessage()], 'warning', (int) $user['id'], $req->ip());
            flash('error', 'Gagal membuat tagihan: ' . $e->getMessage());
            return redirect('/store');
        }
    }

    /** GET /store/checkout/{ref} — instruksi pembayaran + polling status. */
    public function checkout(Request $req, array $params): Response|string
    {
        $user = auth_user();
        $tx   = Transaction::findByRef((string) $params['ref']);
        if (!$tx || (int) $tx['user_id'] !== (int) $user['id']) {
            flash('error', 'Transaksi tidak ditemukan.');
            return redirect('/store');
        }
        return $this->view('store/checkout', [
            'title' => 'Checkout #' . $tx['id'],
            'tx'    => $tx,
        ]);
    }

    /** GET /store/status/{ref} — JSON untuk auto-refresh status di halaman checkout. */
    public function status(Request $req, array $params): Response
    {
        $user = auth_user();
        $tx   = Transaction::findByRef((string) $params['ref']);
        if (!$tx || (int) $tx['user_id'] !== (int) $user['id']) {
            return $this->json(['ok' => false], 404);
        }
        return $this->json(['ok' => true, 'status' => $tx['status'], 'paid_at' => $tx['paid_at']]);
    }

    /** Batalkan transaksi pending milik sendiri (stok dikembalikan). */
    public function cancel(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $tx   = Transaction::findByRef((string) $params['ref']);
        if ($tx && (int) $tx['user_id'] === (int) $user['id'] && $tx['status'] === 'pending') {
            Database::transaction(function () use ($tx) {
                Transaction::markFailed((int) $tx['id'], 'failed');
                Product::restoreStock((int) $tx['product_id']);
            });
            flash('success', 'Transaksi dibatalkan.');
        }
        return redirect('/store');
    }
}
