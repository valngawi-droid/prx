<?php

declare(strict_types=1);

namespace ChiperX\Models;

use ChiperX\Core\Database;

final class User
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    }

    /** Buat user baru saat login OTP pertama (auto-register, dukung referral). */
    public static function createFromEmail(string $email, string $role = 'user', ?string $referredByCode = null): int
    {
        $name  = ucfirst(strtok($email, '@') ?: 'Pengguna');
        $bonus = (int) (Setting::get('register_bonus') ?? '0');

        $referrerId = null;
        if ($referredByCode !== null && $referredByCode !== '') {
            $referrer   = self::findByReferralCode($referredByCode);
            $referrerId = $referrer['id'] ?? null;
        }

        Database::run(
            'INSERT INTO users (name, email, role, coin_balance, play_tickets, tickets_reset_at, referral_code, referred_by)
             VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?)',
            [$name, mb_strtolower($email), $role, max(0, $bonus), (int) (Setting::get('daily_tickets') ?? '3'), self::generateReferralCode(), $referrerId]
        );
        return Database::lastInsertId();
    }

    /** Kode referral unik 8 karakter hex (loop hingga unik). */
    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
        } while ((int) Database::value('SELECT COUNT(*) FROM users WHERE referral_code = ?', [$code]) > 0);
        return $code;
    }

    /** @return array<string, mixed>|null */
    public static function findByReferralCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        return $code === '' ? null : Database::one('SELECT * FROM users WHERE referral_code = ?', [$code]);
    }

    /** Pastikan user (termasuk akun lama) punya kode referral. Mengembalikan kodenya. */
    public static function ensureReferralCode(int $id): string
    {
        $code = Database::value('SELECT referral_code FROM users WHERE id = ?', [$id]);
        if (is_string($code) && $code !== '') {
            return $code;
        }
        $newCode = self::generateReferralCode();
        Database::run('UPDATE users SET referral_code = ? WHERE id = ? AND referral_code IS NULL', [$newCode, $id]);
        return (string) (Database::value('SELECT referral_code FROM users WHERE id = ?', [$id]) ?? $newCode);
    }

    /**
     * Klaim bonus koin harian — atomik, hanya 1x per hari WIB.
     * @return bool true jika klaim berhasil (belum pernah hari ini)
     */
    public static function claimDailyBonus(int $id, int $bonus): bool
    {
        $stmt = Database::run(
            'UPDATE users SET coin_balance = coin_balance + ?, last_daily_claim = CURDATE()
             WHERE id = ? AND (last_daily_claim IS NULL OR last_daily_claim < CURDATE())',
            [max(0, $bonus), $id]
        );
        return $stmt->rowCount() === 1;
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$id]);
    }

    /** Tambah koin secara atomik (positif = tambah, negatif = kurang). Gagal jika saldo minus. */
    public static function addCoins(int $id, int $delta): bool
    {
        $stmt = Database::run(
            'UPDATE users SET coin_balance = coin_balance + ? WHERE id = ? AND (coin_balance + ?) >= 0',
            [$delta, $id, $delta]
        );
        return $stmt->rowCount() === 1;
    }

    /** @return array<int, array<string, mixed>> */
    public static function paginate(int $page, int $perPage, string $search = '', string $roleFilter = ''): array
    {
        $where = '1=1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (email LIKE ? OR name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($roleFilter !== '' && in_array($roleFilter, ['owner', 'admin', 'user'], true)) {
            $where .= ' AND role = ?';
            $params[] = $roleFilter;
        }
        $offset = ($page - 1) * $perPage;
        return Database::all(
            "SELECT id, name, email, role, status, coin_balance, play_tickets, last_login, created_at
             FROM users WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
    }

    public static function countFiltered(string $search = '', string $roleFilter = ''): int
    {
        $where = '1=1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (email LIKE ? OR name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($roleFilter !== '' && in_array($roleFilter, ['owner', 'admin', 'user'], true)) {
            $where .= ' AND role = ?';
            $params[] = $roleFilter;
        }
        return (int) Database::value("SELECT COUNT(*) FROM users WHERE {$where}", $params);
    }

    public static function totalCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM users');
    }

    public static function updateProfile(int $id, string $name): void
    {
        Database::run('UPDATE users SET name = ? WHERE id = ?', [mb_substr($name, 0, 80), $id]);
    }

    /** Manajemen oleh Owner/Admin: role, status, koin. */
    public static function adminUpdate(int $id, array $data): void
    {
        Database::run(
            'UPDATE users SET name = ?, role = ?, status = ?, coin_balance = ? WHERE id = ?',
            [$data['name'], $data['role'], $data['status'], $data['coin_balance'], $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE users SET status = ? WHERE id = ?', [$status === 'banned' ? 'banned' : 'active', $id]);
    }

    public static function deleteById(int $id): void
    {
        Database::run('DELETE FROM users WHERE id = ?', [$id]);
    }

    /** Leaderboard koin (halaman dashboard). @return array<int, array<string, mixed>> */
    public static function topCoins(int $limit = 5): array
    {
        return Database::all(
            "SELECT name, coin_balance FROM users WHERE status = 'active' ORDER BY coin_balance DESC LIMIT {$limit}"
        );
    }

    /* ------------------------------------------------------------------
     * Username & Password (alur auth v2)
     * ------------------------------------------------------------------ */

    /** Algoritma hash password terkuat yang tersedia di build PHP ini. */
    public static function hashAlgo(): string
    {
        if (defined('PASSWORD_ARGON2ID') && in_array(constant('PASSWORD_ARGON2ID'), password_algos(), true)) {
            return constant('PASSWORD_ARGON2ID');
        }
        return PASSWORD_BCRYPT;
    }

    /** Cari user via USERNAME atau EMAIL (satu kolom input login). */
    public static function findByIdentity(string $identity): ?array
    {
        $identity = trim(mb_strtolower($identity));
        return Database::one(
            'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$identity, $identity]
        );
    }

    public static function usernameTaken(string $username): bool
    {
        return (int) Database::value('SELECT COUNT(*) FROM users WHERE username = ?', [mb_strtolower($username)]) > 0;
    }

    public static function setCredentials(int $id, string $username, string $passwordHash): void
    {
        Database::run('UPDATE users SET username = ?, password_hash = ? WHERE id = ?', [mb_strtolower($username), $passwordHash, $id]);
    }

    /** Registrasi PENUH (email terverifikasi OTP + username + password). */
    public static function createFull(string $email, string $username, string $passwordHash, string $role = 'user', ?string $referredByCode = null): int
    {
        $id = self::createFromEmail($email, $role, $referredByCode);
        self::setCredentials($id, $username, $passwordHash);
        return $id;
    }

    /* ---------- Profil: verified & badges ---------- */

    public static function findByUsername(string $username): ?array
    {
        return Database::one('SELECT * FROM users WHERE username = ?', [mb_strtolower(trim($username))]);
    }

    public static function setVerified(int $id, bool $verified): void
    {
        Database::run('UPDATE users SET is_verified = ? WHERE id = ?', [$verified ? 1 : 0, $id]);
    }

    /** Simpan tags kustom (CSV, maks 5, masing-masing @ 24 char, disanitasi). */
    public static function setBadges(int $id, string $csv): void
    {
        $tags = [];
        foreach (explode(',', $csv) as $tag) {
            $tag = trim(preg_replace('/[\x00-\x1F<>"]/', '', $tag) ?? '');
            if ($tag !== '') {
                $tags[] = mb_substr($tag, 0, 24);
            }
            if (count($tags) >= 5) {
                break;
            }
        }
        Database::run('UPDATE users SET badges = ? WHERE id = ?', [implode(',', $tags) ?: null, $id]);
    }

    public static function updateName(int $id, string $name): void
    {
        Database::run('UPDATE users SET name = ? WHERE id = ?', [mb_substr(trim($name), 0, 80) ?: 'Pengguna ChiperX', $id]);
    }
}
