<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Product;
use ChiperX\Models\Transaction;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;

/**
 * Redeem Center: tukar ChiperX Coin dengan file/source code.
 */
final class RedeemController extends Controller
{
    public function index(Request $req): string
    {
        $products = [];
        try {
            $products = Product::forRedeem();
        } catch (\Throwable) {
            flash('warning', 'Katalog sedang disegarkan — coba sebentar lagi ya. 🙏');
        }
        return $this->view('redeem/index', [
            'title'    => 'Redeem Center',
            'user'     => auth_user(),
            'products' => $products,
        ]);
    }

    /** POST /redeem-code — klaim KODE kustom buatan admin (kuota + kedaluwarsa). */
    public function claimCode(Request $req): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $res  = \ChiperX\Models\RedeemCode::claim((int) $user['id'], $req->str('code', '', 40));
        if ($res['ok']) {
            AuditLogger::record('redeem_code.claim', ['code' => strtoupper(trim($req->str('code', '', 40))), 'coins' => $res['coins']], 'info', (int) $user['id'], $req->ip());
            \ChiperX\Models\Notification::add((int) $user['id'], '🎟️ Kode Redeem + ' . $res['coins'] . ' koin', 'Kode berhasil diklaim.', '/redeem', 'success');
        }
        // Pesan "sudah pernah diklaim" = info ramah (kuning), bukan error merah
        $type = $res['ok'] ? 'success'
            : (preg_match('~sudah|pernah~i', (string) $res['message']) ? 'warning' : 'error');
        flash($type, $res['message']);
        return redirect('/redeem');
    }

    /** POST /redeem/{id} — potong koin & buka akses (transaksi atomik). */
    public function redeem(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user    = auth_user();
        $product = Product::find((int) $params['id']);

        if (!$product || !$product['is_active'] || $product['type'] !== 'coin_redeem') {
            flash('error', 'Produk tidak tersedia.');
            return redirect('/redeem');
        }
        if ((int) $user['coin_balance'] < (int) $product['price']) {
            flash('error', 'Koin Anda tidak cukup. Mainkan Mini Games untuk mengumpulkan koin!');
            return redirect('/redeem');
        }
        $already = Database::value(
            "SELECT COUNT(*) FROM transactions WHERE user_id = ? AND product_id = ? AND status = 'paid' AND payment_method = 'coin'",
            [$user['id'], $product['id']]
        );
        if ((int) $already > 0) {
            flash('warning', 'Anda sudah menukar item ini. Cek menu Riwayat di dashboard.');
            return redirect('/dashboard');
        }

        try {
            $txId = Database::transaction(function () use ($user, $product) {
                // Kunci baris user dulu → semua operasi koin terserialisasi per-user (anti race)
                Database::run('SELECT id FROM users WHERE id = ? FOR UPDATE', [$user['id']]);

                $dup = Database::value(
                    "SELECT COUNT(*) FROM transactions WHERE user_id = ? AND product_id = ? AND status = 'paid' AND payment_method = 'coin'",
                    [$user['id'], $product['id']]
                );
                if ((int) $dup > 0) {
                    throw new \RuntimeException('Item ini sudah pernah Anda tukar. Cek menu Riwayat di dashboard.');
                }
                if (!Product::decrementStock((int) $product['id'])) {
                    throw new \RuntimeException('Stok habis.');
                }
                if (!User::addCoins((int) $user['id'], -(int) $product['price'])) {
                    throw new \RuntimeException('Saldo koin tidak mencukupi.');
                }
                return Transaction::create([
                    'user_id'        => (int) $user['id'],
                    'product_id'     => (int) $product['id'],
                    'amount'         => (int) $product['price'],
                    'payment_method' => 'coin',
                    'status'         => 'paid',
                ]);
            });
            Database::run('UPDATE transactions SET paid_at = NOW() WHERE id = ?', [$txId]);

            AuditLogger::record('redeem', ['product' => $product['name'], 'cost' => $product['price']], 'info', (int) $user['id'], $req->ip());

            // Evaluasi achievement pasca-redeem
            $fresh   = \ChiperX\Services\AchievementService::checkAndUnlock((int) $user['id']);
            $badgeNote = $fresh !== [] ? ' Badge baru terbuka: ' . implode(', ', array_column($fresh, 'name')) . '! 🏅' : '';

            flash('success', 'Berhasil ditukar! Akses unduhan telah terbuka 🎉' . $badgeNote);
            return redirect('/download/' . $txId);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            return redirect('/redeem');
        }
    }
}
