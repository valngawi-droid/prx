<?php

declare(strict_types=1);

/**
 * ============================================================
 * ChiperX — Test Runner mandiri (tanpa PHPUnit)
 * Jalankan:  php tests/run.php   atau   composer test
 * ============================================================
 */
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Core/Env.php';
require BASE_PATH . '/src/Core/Config.php';
require BASE_PATH . '/src/Core/Csrf.php';
require BASE_PATH . '/src/Core/Session.php';
require BASE_PATH . '/src/Core/helpers.php';
require BASE_PATH . '/src/Services/GameService.php';
require BASE_PATH . '/src/Services/FileManagerService.php';
require BASE_PATH . '/src/Services/LeaderboardService.php';
require BASE_PATH . '/src/Models/ApiToken.php';
require BASE_PATH . '/src/Services/Payments/HttpClient.php';
require BASE_PATH . '/src/Services/Payments/PaymentGatewayInterface.php';
require BASE_PATH . '/src/Services/Payments/DuitkuGateway.php';
require BASE_PATH . '/src/Services/Payments/PakasirGateway.php';

use ChiperX\Core\Csrf;
use ChiperX\Models\ApiToken;
use ChiperX\Services\FileManagerService;
use ChiperX\Services\GameService;
use ChiperX\Services\LeaderboardService;

date_default_timezone_set('Asia/Jakarta');

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "  ✓ {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ✗ {$name}\n      → {$e->getMessage()}\n";
        $failed++;
    }
}

function ok(bool $cond, string $msg = 'assertion gagal'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

echo "\n🧪 CHIPERX TEST SUITE\n";
echo str_repeat('─', 56) . "\n\n";

// ============================================================
echo "🎰 1. RNG / RTP Mini Games\n";
// ============================================================

test('Distribusi gacha 200.000 putaran akurat (toleransi ±0,6%)', function () {
    $weights = ['zonk' => 60.0, 'coin_10' => 25.0, 'coin_50' => 10.0, 'coin_100' => 5.0];
    $n = 200_000;
    $hits = array_fill_keys(array_keys($weights), 0);
    for ($i = 0; $i < $n; $i++) {
        $hits[GameService::rollFromWeights($weights)]++;
    }
    foreach ($weights as $key => $pct) {
        $actual = ($hits[$key] / $n) * 100;
        ok(
            abs($actual - $pct) < 0.6,
            sprintf('%s: harapan %.1f%%, aktual %.3f%% (selisih %.3f%%)', $key, $pct, $actual, abs($actual - $pct))
        );
    }
});

test('Bobot 0 tidak pernah terpilih (20.000 putaran)', function () {
    $weights = ['zonk' => 99.9, 'coin_100' => 0.0, 'coin_10' => 0.1];
    for ($i = 0; $i < 20_000; $i++) {
        ok(GameService::rollFromWeights($weights) !== 'coin_100', 'bobot 0 malah terpilih!');
    }
});

test('Bobot tidak harus total 100 — dinormalisasi otomatis', function () {
    // total 240 → coin_50 = 120/240 = 50%
    $weights = ['zonk' => 120.0, 'coin_50' => 120.0];
    $n = 60_000;
    $hits = 0;
    for ($i = 0; $i < $n; $i++) {
        if (GameService::rollFromWeights($weights) === 'coin_50') {
            $hits++;
        }
    }
    $actual = ($hits / $n) * 100;
    ok(abs($actual - 50.0) < 1.2, sprintf('harapan ~50%%, aktual %.2f%%', $actual));
});

test('Total bobot 0 → melempar InvalidArgumentException', function () {
    try {
        GameService::rollFromWeights(['zonk' => 0.0, 'coin_10' => 0.0]);
        ok(false, 'harusnya melempar exception');
    } catch (InvalidArgumentException) {
        ok(true);
    }
});

test('Definisi 3 game lengkap & konsisten', function () {
    ok(GameService::gameExists('gacha'));
    ok(GameService::gameExists('mystery_box'));
    ok(GameService::gameExists('card_flip'));
    ok(!GameService::gameExists('cheat_engine'));
});

// ============================================================
echo "\n🛡️ 2. Keamanan CSRF\n";
// ============================================================

test('Token CSRF: generate → validate cocok', function () {
    session_status() === PHP_SESSION_ACTIVE || @session_start();
    $_SESSION = ['_csrf' => bin2hex(random_bytes(32))];
    ok(Csrf::validate($_SESSION['_csrf']), 'token valid ditolak');
});

test('Token CSRF: token salah/NULL ditolak', function () {
    ok(!Csrf::validate('token-palsu'), 'token palsu diterima!');
    ok(!Csrf::validate(null), 'token null diterima!');
});

// ============================================================
echo "\n🗂️ 3. File Manager — anti path traversal\n";
// ============================================================

test('Path sah di dalam root diterima', function () {
    $abs = FileManagerService::resolve('public/index.php');
    ok(str_ends_with(str_replace('\\', '/', $abs), 'public/index.php'), 'resolve salah: ' . $abs);
    ok(FileManagerService::toRelative($abs) === 'public/index.php');
});

test('Path traversal ../../ DITOLAK', function () {
    foreach (['../../../../etc/passwd', '..\\..\\windows\\win.ini', '../outside'] as $evil) {
        try {
            FileManagerService::resolve($evil);
            ok(false, "traversal lolos: {$evil}");
        } catch (RuntimeException) {
            ok(true);
        }
    }
});

test('Null byte injection DITOLAK', function () {
    try {
        FileManagerService::resolve("public/index.php\0.jpg");
        ok(false, 'null byte lolos');
    } catch (RuntimeException) {
        ok(true);
    }
});

test('Pemetaan bahasa Monaco Editor benar', function () {
    ok(FileManagerService::monacoLanguage('php') === 'php');
    ok(FileManagerService::monacoLanguage('sql') === 'sql');
    ok(FileManagerService::monacoLanguage('xyz') === 'plaintext');
});

// ============================================================
echo "\n🏆 4. Leaderboard Mingguan (batas pekan)\n";
// ============================================================

test('Batas pekan selalu mulai Senin & span 7 hari', function () {
    foreach (['2026-08-10 10:00', '2026-08-12 15:00', '2026-08-16 23:59', '2026-08-17 00:01'] as $d) {
        [$start, $end, $period] = LeaderboardService::weekBounds(strtotime($d));
        ok((int) date('N', strtotime($start)) === 1, "start bukan Senin untuk $d: $start");
        ok(strtotime($end) - strtotime($start) === 604800, 'span bukan tepat 7 hari');
        ok(preg_match('/^\d{4}-W\d{2}$/', $period) === 1, "format period salah: $period");
    }
});

test('Tanggal dalam satu pekan menghasilkan period yang sama', function () {
    [, , $a] = LeaderboardService::weekBounds(strtotime('2026-08-11 08:00'));
    [, , $b] = LeaderboardService::weekBounds(strtotime('2026-08-15 22:00'));
    ok($a === $b, "period beda: $a vs $b");
});

// ============================================================
echo "\n🔑 5. API Token (hashing)\n";
// ============================================================

test('Generate token: format cx_ + 48 hex, hash SHA-256 64 karakter', function () {
    $plain = ApiToken::generate();
    ok(preg_match('/^cx_[a-f0-9]{48}$/', $plain) === 1, 'format token salah');
    ok(strlen(ApiToken::hash($plain)) === 64, 'hash bukan SHA-256');
});

test('Verify: token benar diterima, token salah/null byte ditolak', function () {
    $plain = ApiToken::generate();
    $hash  = ApiToken::hash($plain);
    ok(ApiToken::verify($plain, $hash), 'token valid ditolak');
    ok(!ApiToken::verify('cx_palsu', $hash), 'token palsu diterima!');
    ok(!ApiToken::verify($plain . "\0evil", $hash), 'null byte lolos!');
});

// ============================================================
echo "\n🧍 6. Helper auth (regresi TypeError)\n";
// ============================================================

test('auth_user() tamu → null berkali-kali tanpa TypeError', function () {
    @session_start();
    $_SESSION = [];
    ok(auth_user() === null, 'panggilan 1 harus null');
    ok(auth_user() === null, 'panggilan 2 harus null (cache false tidak bocor)');
    ok(is_logged_in() === false, 'tamu seharusnya tidak dianggap login');
});

test('e() escape XSS dengan benar', function () {
    ok(e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;', 'escape gagal');
    ok(e('"<b>\'') === '&quot;&lt;b&gt;&#039;', 'quote escape gagal');
});

// ============================================================
echo "\n💳 7. Payment Gateway — signature Duitku & safety Pakasir\n";
// ============================================================

test('Duitku callback: signature valid diterima & dimap PAID', function () {
    $g  = new ChiperX\Services\Payments\DuitkuGateway();
    $key = \ChiperX\Core\Env::get('DUITKU_API_KEY', ''); // '' di lingkungan test
    $orderId = 'CX-TEST-1';
    $sig  = md5('MRC-1' . '50000' . $orderId . $key);
    $body = http_build_query([
        'merchantCode' => 'MRC-1', 'amount' => '50000', 'merchantOrderId' => $orderId,
        'resultCode' => '00', 'signature' => $sig,
    ]);
    $event = $g->verifyCallback($body, []);
    ok($event !== null && $event['ref'] === $orderId && $event['status'] === 'paid', 'callback valid ditolak');
});

test('Duitku callback: signature diubah → DITOLAK', function () {
    $g = new ChiperX\Services\Payments\DuitkuGateway();
    $body = http_build_query([
        'merchantCode' => 'MRC-1', 'amount' => '50000', 'merchantOrderId' => 'CX-X',
        'resultCode' => '00', 'signature' => 'md5palsu123',
    ]);
    ok($g->verifyCallback($body, []) === null, 'signature palsu diterima!');
});

test('Pakasir webhook: tanpa API key → ditolak (anti webhook palsu)', function () {
    $g = new ChiperX\Services\Payments\PakasirGateway();
    // env test tidak punya PAKASIR_API_KEY → harus null
    ok($g->verifyCallback('{"order_id":"CX-1","amount":1000,"status":"completed"}', []) === null,
        'webhook tanpa verifikasi diterima!');
    ok($g->verifyCallback('{"order_id":"CX-1","status":"canceled"}', [])['status'] === 'failed',
        'status cancel harus failed');
});

// ============================================================
echo "\n" . str_repeat('─', 56) . "\n";
echo "HASIL: {$passed} lulus, {$failed} gagal\n\n";
exit($failed > 0 ? 1 : 0);
