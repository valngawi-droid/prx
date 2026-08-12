<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class Changelog
{
    /** @return array<int, array<string, mixed>> */
    public static function latest(int $limit = 20): array
    {
        return Database::all("SELECT * FROM changelogs ORDER BY released_at DESC, id DESC LIMIT {$limit}");
    }
}
