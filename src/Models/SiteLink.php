<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class SiteLink
{
    /** @return array<int, array<string, mixed>> Link aktif untuk halaman publik. */
    public static function active(): array
    {
        return Database::all('SELECT * FROM links WHERE is_active = 1 ORDER BY order_num ASC, id ASC');
    }

    /** @return array<int, array<string, mixed>> */
    public static function allLinks(): array
    {
        return Database::all('SELECT * FROM links ORDER BY order_num ASC, id ASC');
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM links WHERE id = ?', [$id]);
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO links (title, url, icon_class, color, order_num, is_active) VALUES (?, ?, ?, ?, ?, ?)',
            [$d['title'], $d['url'], $d['icon_class'], $d['color'], $d['order_num'], $d['is_active']]
        );
        return Database::lastInsertId();
    }

    public static function updateById(int $id, array $d): void
    {
        Database::run(
            'UPDATE links SET title = ?, url = ?, icon_class = ?, color = ?, order_num = ?, is_active = ? WHERE id = ?',
            [$d['title'], $d['url'], $d['icon_class'], $d['color'], $d['order_num'], $d['is_active'], $id]
        );
    }

    public static function deleteById(int $id): void
    {
        Database::run('DELETE FROM links WHERE id = ?', [$id]);
    }
}
