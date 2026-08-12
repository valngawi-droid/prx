<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Transaction
{
    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO transactions (user_id, product_id, amount, payment_method, status, gateway_ref, checkout_url, qr_url, expired_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['user_id'], $d['product_id'], $d['amount'], $d['payment_method'],
                $d['status'] ?? 'pending', $d['gateway_ref'] ?? null,
                $d['checkout_url'] ?? null, $d['qr_url'] ?? null, $d['expired_at'] ?? null,
            ]
        );
        return Database::lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT t.*, p.name AS product_name, p.file_url, p.type AS product_type
             FROM transactions t JOIN products p ON p.id = t.product_id WHERE t.id = ?',
            [$id]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findByRef(string $ref): ?array
    {
        return Database::one(
            'SELECT t.*, p.name AS product_name FROM transactions t JOIN products p ON p.id = t.product_id WHERE t.gateway_ref = ?',
            [$ref]
        );
    }

    public static function markPaid(int $id): void
    {
        Database::run("UPDATE transactions SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'pending'", [$id]);
    }

    public static function markFailed(int $id, string $status = 'failed'): void
    {
        $status = in_array($status, ['failed', 'expired', 'refunded'], true) ? $status : 'failed';
        Database::run("UPDATE transactions SET status = ? WHERE id = ? AND status = 'pending'", [$status, $id]);
    }

    /** Milik user (untuk halaman download). @return array<string, mixed>|null */
    public static function ownedPaid(int $txId, int $userId): ?array
    {
        return Database::one(
            "SELECT t.*, p.name AS product_name, p.file_url
             FROM transactions t JOIN products p ON p.id = t.product_id
             WHERE t.id = ? AND t.user_id = ? AND t.status = 'paid'",
            [$txId, $userId]
        );
    }

    /** Riwayat pembelian/tukar user. @return array<int, array<string, mixed>> */
    public static function forUser(int $userId, int $limit = 20): array
    {
        return Database::all(
            "SELECT t.*, p.name AS product_name, p.type AS product_type
             FROM transactions t JOIN products p ON p.id = t.product_id
             WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT {$limit}",
            [$userId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 25): array
    {
        return Database::all(
            "SELECT t.*, u.email, p.name AS product_name
             FROM transactions t
             JOIN users u ON u.id = t.user_id
             JOIN products p ON p.id = t.product_id
             ORDER BY t.created_at DESC LIMIT {$limit}"
        );
    }

    public static function totalPaidCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM transactions WHERE status = 'paid'");
    }

    public static function totalRevenue(): int
    {
        return (int) Database::value("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'paid' AND payment_method <> 'coin'");
    }
}
