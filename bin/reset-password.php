<?php

declare(strict_types=1);

/**
 * ============================================================
 * ChiperX — Reset Password dari CLI (Termux friendly!)
 * ============================================================
 * Penyelamat saat lupa password dan email OTP belum bisa diandalkan.
 *
 *   php bin/reset-password.php                              → daftar semua akun
 *   php bin/reset-password.php email@kamu.com 'PassBaru123' → reset password
 *
 * Password minimal 6 karakter. Hash otomatis memakai algoritma terkuat
 * yang tersedia di build PHP ini (Argon2id, fallback BCrypt).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya untuk CLI.');
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'ChiperX\\')) {
        $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
if (is_file(BASE_PATH . '/src/Core/helpers.php')) {
    require_once BASE_PATH . '/src/Core/helpers.php';
}

use ChiperX\Core\Database;
use ChiperX\Core\Env;
use ChiperX\Models\User;

Env::load(BASE_PATH . '/.env');

$ok   = static fn(string $s): string => "  \033[32m✔\033[0m {$s}";
$err  = static fn(string $s): string => "  \033[31m✘\033[0m {$s}";
$info = static fn(string $s): string => "  \033[36mℹ\033[0m {$s}";

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║   ChiperX — Reset Password (CLI) 🔑          ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

try {
    Database::pdo();
} catch (\Throwable $e) {
    echo $err('Database tidak bisa dihubungi: ' . $e->getMessage()) . "\n";
    echo $info('Nyalakan dulu: mysqladmin -u root ping || ( mysqld_safe & sleep 10 )') . "\n\n";
    exit(1);
}

// ── Mode DAFTAR: tanpa argumen ──
$email = $argv[1] ?? null;
$pass  = $argv[2] ?? null;

if ($email === null) {
    echo $info('Daftar akun (email — username — role):') . "\n";
    foreach (Database::all('SELECT email, username, role, password_hash FROM users ORDER BY id') as $u) {
        $punyaPass = ($u['password_hash'] ?? '') !== '' ? '🔑' : '—';
        echo "   • {$u['email']} — " . ($u['username'] ?: '(tanpa username)') . " — {$u['role']} {$punyaPass}\n";
    }
    echo "\n" . $info("Pakai: php bin/reset-password.php email@kamu.com 'PasswordBaru'") . "\n\n";
    exit(0);
}

// ── Mode RESET ──
$email = mb_strtolower(trim((string) $email));
$user = User::findByEmail($email);
if (!$user) {
    echo $err("Akun {$email} tidak ditemukan.") . "\n";
    echo $info("Cek ejaan: php bin/reset-password.php  (tanpa argumen untuk daftar akun)") . "\n\n";
    exit(1);
}
if (!is_string($pass) || mb_strlen($pass) < 6) {
    echo $err('Password minimal 6 karakter. Contoh: php bin/reset-password.php ' . $email . " 'PassBaru123'") . "\n\n";
    exit(1);
}

$algo = User::hashAlgo();
$hash = password_hash($pass, $algo);
Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, (int) $user['id']]);

// Username kosong + belum ada → isi otomatis dari bagian depan email agar
// bisa login via "username/email + password" tanpa langkah tambahan.
if (($user['username'] ?? '') === '' || $user['username'] === null) {
    $base  = preg_replace('/[^a-z0-9_]/', '', str_replace('.', '_', explode('@', $email)[0]));
    $uname = $base !== '' ? $base : 'user' . (int) $user['id'];
    $i = 1;
    while (User::usernameTaken($uname)) {
        $uname = $base . (++$i);
    }
    Database::run('UPDATE users SET username = ? WHERE id = ?', [$uname, (int) $user['id']]);
    echo $ok("Username otomatis dibuat: {$uname}") . "\n";
}

echo $ok("Password {$email} berhasil direset! (hash: " . ($algo === PASSWORD_BCRYPT ? 'BCrypt' : 'Argon2id') . ")") . "\n";
echo $info('Sekarang bisa login di /login dengan email/username + password baru.') . "\n";
echo $info("Ganti lagi kapan pun lewat perintah yang sama. Jangan bagikan output ini.\n\n");
exit(0);
