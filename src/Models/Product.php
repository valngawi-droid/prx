<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Product
{
    /** @return array<int, array<string, mixed>> */
    public static function forRedeem(): array
    {
        return Database::all(
            "SELECT * FROM products WHERE type = 'coin_redeem' AND is_active = 1 ORDER BY is_featured DESC, price ASC"
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function forStore(): array
    {
        return Database::all(
            "SELECT * FROM products WHERE type = 'premium_store' AND is_active = 1 ORDER BY is_featured DESC, price ASC"
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function allAdmin(): array
    {
        return Database::all('SELECT * FROM products ORDER BY created_at DESC');
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM products WHERE id = ?', [$id]);
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO products (name, slug, type, price, stock, file_url, description, image, is_featured, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['name'], self::slugify($d['name']), $d['type'], $d['price'], $d['stock'],
                $d['file_url'], $d['description'], $d['image'] ?? null,
                $d['is_featured'] ?? 0, $d['is_active'] ?? 1, $d['created_by'] ?? null,
            ]
        );
        return Database::lastInsertId();
    }

    public static function updateById(int $id, array $d): void
    {
        Database::run(
            'UPDATE products SET name = ?, type = ?, price = ?, stock = ?, file_url = ?, description = ?, is_featured = ?, is_active = ? WHERE id = ?',
            [$d['name'], $d['type'], $d['price'], $d['stock'], $d['file_url'], $d['description'], $d['is_featured'] ?? 0, $d['is_active'] ?? 1, $id]
        );
    }

    public static function deleteById(int $id): void
    {
        Database::run('DELETE FROM products WHERE id = ?', [$id]);
    }

    /**
     * Kurangi stok secara atomik; NULL = unlimited (selalu sukses).
     * Wajib dipanggil di dalam transaksi (SELECT ... FOR UPDATE mengunci baris).
     */
    public static function decrementStock(int $id): bool
    {
        $row = Database::one('SELECT stock FROM products WHERE id = ? FOR UPDATE', [$id]);
        if (!$row) {
            return false;
        }
        if ($row['stock'] === null) {
            return true; // stok tak terbatas
        }
        if ((int) $row['stock'] <= 0) {
            return false;
        }
        $stmt = Database::run('UPDATE products SET stock = stock - 1 WHERE id = ? AND stock > 0', [$id]);
        return $stmt->rowCount() === 1;
    }

    /** Kembalikan stok saat transaksi gagal/dibatalkan (no-op untuk stok unlimited). */
    public static function restoreStock(int $id): void
    {
        Database::run('UPDATE products SET stock = stock + 1 WHERE id = ? AND stock IS NOT NULL', [$id]);
    }

    public static function totalCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM products WHERE is_active = 1');
    }

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $base = $slug !== '' ? $slug : 'produk';
        $slug = $base;
        $i = 1;
        while (Database::value('SELECT COUNT(*) FROM products WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
