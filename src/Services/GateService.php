<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Session;
use ChiperX\Models\Setting;

/**
 * Gerbang panel rahasia (/zszdgj/login) — verifikasi lapis kedua SETELAH OTP.
 *
 * Pertahanan berlapis:
 *  - Password TIDAK PERNAH disimpan mentah — hanya hash BCrypt di tabel settings
 *    (auto-seed dari default saat pertama kali dijalankan; password default: 321).
 *  - Anti brute-force (throttling): 5x gagal → IP dikunci 10 menit +
 *    jeda paksa 2 detik setiap percobaan gagal (hydra/Kali jadi tidak praktis).
 *  - Session flag "admin_gate_ok" dihapus setiap login OTP baru → wajib gate ulang.
 */
final class GateService
{
    public const PATH            = '/zszdgj/login';
    private const MAX_FAILS      = 5;
    private const LOCK_SECONDS   = 600; // 10 menit
    private const FAIL_WINDOW    = 600; // penghitungan gagal dalam 10 menit terakhir

    /** Kredensial: username + hash bcrypt (auto-seed saat pertama jalan). */
    public static function credentials(): array
    {
        $user = Setting::get('admin_gate_user');
        $hash = Setting::get('admin_gate_pass_hash');
        if (!$hash) {
            // Seed pertama kali — langsung di-hash (bcrypt), tidak pernah plaintext
            $hash = password_hash('321', PASSWORD_BCRYPT);
            Setting::set('admin_gate_pass_hash', $hash);
        }
        if (!$user) {
            $user = 'ChiperX';
            Setting::set('admin_gate_user', $user);
        }
        return ['user' => $user, 'hash' => $hash];
    }

    /** Verifikasi kredensial gerbang (timing-safe via password_verify). */
    public static function verify(string $username, string $password): bool
    {
        $cred = self::credentials();
        $userOk = hash_equals($cred['user'], $username);
        $passOk = password_verify($password, $cred['hash']);
        return $userOk && $passOk;
    }

    /** Ganti username/password gerbang (dipanggil dari owner settings). */
    public static function updateCredentials(?string $newUser, ?string $newPass): void
    {
        if ($newUser !== null && $newUser !== '') {
            Setting::set('admin_gate_user', mb_substr(trim($newUser), 0, 60));
        }
        if ($newPass !== null && $newPass !== '') {
            Setting::set('admin_gate_pass_hash', password_hash($newPass, PASSWORD_BCRYPT));
        }
    }

    public static function isUnlocked(): bool
    {
        return Session::get('admin_gate_ok') === true;
    }

    public static function unlock(): void
    {
        Session::set('admin_gate_ok', true);
    }

    public static function forget(): void
    {
        Session::forget('admin_gate_ok');
    }

    // ------------------------------------------------------------------
    // Anti brute-force berbasis file per-IP (tahan restart, tanpa DB)
    // ------------------------------------------------------------------

    /** Sisa detik kunci; 0 = tidak terkunci. */
    public static function lockedSeconds(string $ip): int
    {
        $state = self::readState($ip);
        $until = (int) ($state['locked_until'] ?? 0);
        return max(0, $until - time());
    }

    /** Catat percobaan gagal; mengunci IP bila melewati ambang. */
    public static function recordFail(string $ip): void
    {
        $state = self::readState($ip);
        $now   = time();
        $fails = array_filter(
            (array) ($state['fails'] ?? []),
            static fn (int $ts): bool => $ts > $now - self::FAIL_WINDOW
        );
        $fails[] = $now;
        $state['fails'] = $fails;
        if (count($fails) >= self::MAX_FAILS) {
            $state['locked_until'] = $now + self::LOCK_SECONDS;
            $state['fails'] = [];
        }
        self::writeState($ip, $state);
    }

    public static function clearFails(string $ip): void
    {
        $state = self::readState($ip);
        $state['fails'] = [];
        $state['locked_until'] = 0;
        self::writeState($ip, $state);
    }

    private static function key(string $ip): string
    {
        return hash('sha256', 'gate:' . $ip);
    }

    private static function dir(): string
    {
        $dir = BASE_PATH . '/storage/cache/gate';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /** @return array{fails?: array<int,int>, locked_until?: int} */
    private static function readState(string $ip): array
    {
        $file = self::dir() . '/' . self::key($ip) . '.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $state */
    private static function writeState(string $ip, array $state): void
    {
        @file_put_contents(
            self::dir() . '/' . self::key($ip) . '.json',
            json_encode($state),
            LOCK_EX
        );
    }
}
