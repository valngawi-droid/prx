<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

/**
 * Kode redeem kustom (admin buat, user klaim koin).
 * Klaim atomik: satu user hanya bisa sekali per kode; kuota & kedaluwarsa
 * dijaga di dalam transaksi dengan FOR UPDATE.
 */
final class RedeemCode
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Database::all('SELECT * FROM redeem_codes ORDER BY id DESC LIMIT 100');
    }

    /** @return array{ok:bool,message:string} */
    public static function create(string $code, int $coins, ?int $quota, ?string $expiresAt, int $by): array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9_\-]{4,40}$/', $code)) {
            return ['ok' => false, 'message' => 'Kode 4–40 karakter: huruf/angka/strip/underscore.'];
        }
        if ($coins < 1 || $coins > 100000) {
            return ['ok' => false, 'message' => 'Koin antara 1–100.000.'];
        }
        if (Database::value('SELECT COUNT(*) FROM redeem_codes WHERE code = ?', [$code]) > 0) {
            return ['ok' => false, 'message' => 'Kode itu sudah ada.'];
        }
        Database::run(
            'INSERT INTO redeem_codes (code, coins, quota, expires_at, created_by) VALUES (?, ?, ?, ?, ?)',
            [$code, $coins, $quota, $expiresAt !== '' ? $expiresAt : null, $by]
        );
        return ['ok' => true, 'message' => 'Kode ' . $code . ' aktif!'];
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE redeem_codes SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    /** @return array{ok:bool,message:string,coins:int} */
    public static function claim(int $userId, string $code): array
    {
        $fail = static fn(string $m): array => ['ok' => false, 'message' => $m, 'coins' => 0];
        $code = strtoupper(trim($code));
        if ($code === '') {
            return $fail('Masukkan kodenya dulu ya.');
        }
        return Database::transaction(static function () use ($userId, $code, $fail): array {
            $row = Database::one('SELECT * FROM redeem_codes WHERE code = ? LIMIT 1 FOR UPDATE', [$code]);
            if (!$row || !(int) $row['is_active']) {
                return $fail('Kode tidak ditemukan atau sudah nonaktif. 💀');
            }
            if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
                return $fail('Kode sudah kedaluwarsa. ⏰');
            }
            if ($row['quota'] !== null && (int) $row['used'] >= (int) $row['quota']) {
                return $fail('Kuota kode ini sudah habis — siapa cepat dia dapat! 🏁');
            }
            if (Database::value('SELECT COUNT(*) FROM redeem_code_claims WHERE code_id = ? AND user_id = ?', [(int) $row['id'], $userId]) > 0) {
                return $fail('Kamu sudah pernah klaim kode ini. 😄');
            }
            Database::run('INSERT INTO redeem_code_claims (code_id, user_id) VALUES (?, ?)', [(int) $row['id'], $userId]);
            Database::run('UPDATE redeem_codes SET used = used + 1 WHERE id = ?', [(int) $row['id']]);
            Database::run('UPDATE users SET coin_balance = coin_balance + ? WHERE id = ?', [(int) $row['coins'], $userId]);
            return ['ok' => true, 'message' => 'Kode valid! +' . (int) $row['coins'] . ' koin masuk. 🎉', 'coins' => (int) $row['coins']];
        });
    }
}
