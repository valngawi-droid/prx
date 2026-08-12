<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Models\AppLog;

/**
 * Audit terpadu: simpan ke tabel `logs` + mirror ke Discord.
 */
final class AuditLogger
{
    public static function record(string $action, array $meta = [], string $level = 'info', ?int $userId = null, ?string $ip = null, ?string $agent = null): void
    {
        try {
            AppLog::add($userId, $action, $level, $ip, $agent, $meta ?: null);
        } catch (\Throwable) {
            // Jangan ganggu request utama jika tabel log gagal
        }
    }
}
