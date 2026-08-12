<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Csrf;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Core\Session;
use ChiperX\Models\OtpCode;
use ChiperX\Models\Setting;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\DiscordWebhook;
use ChiperX\Services\OtpService;

/**
 * Autentikasi TANPA password — murni Email OTP.
 *
 * Alur:
 *  1. /login            → form email
 *  2. POST /auth/otp/send    → buat & kirim OTP (cooldown 60 dtk)
 *  3. /verify           → input 6 digit
 *  4. POST /auth/otp/verify  → valid, buat secure session
 *  5. POST /auth/otp/resend  → kirim ulang (cooldown dicek di service)
 */
final class AuthController extends Controller
{
    public function showLogin(Request $req): string
    {
        // Tangkap kode referral dari URL (?ref=CODE) dan simpan ke session
        $ref = preg_replace('/[^A-Z0-9]/', '', strtoupper($req->str('ref', '', 12)));
        if ($ref !== '') {
            Session::set('ref_code', $ref);
        }
        return $this->view('auth/login', [
            'title'   => 'Masuk',
            'refCode' => Session::get('ref_code'),
        ]);
    }

    public function sendOtp(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $email = $req->email('email');
        if (!$email) {
            flash('error', 'Format email tidak valid.');
            return redirect('/login');
        }
        $result = OtpService::request($email, $req->ip());
        if (!$result['ok']) {
            flash('error', $result['message']);
            return redirect('/login');
        }
        Session::set('otp_email', $email);
        // Bila email tidak benar-benar terkirim (mode log / SMTP gagal),
        // arahkan user membaca kode dari storage/logs/mail.log
        $note = ($result['mailed'] ?? false)
            ? 'Kode OTP 6 digit telah dikirim ke ' . $email . '. Cek inbox/spam.'
            : $result['message'] . ' 📄';
        flash(($result['mailed'] ?? false) ? 'success' : 'warning', $note);
        return redirect('/verify');
    }

    public function showVerify(Request $req): Response|string
    {
        $email = Session::get('otp_email');
        if (!is_string($email)) {
            flash('warning', 'Masukkan email Anda dulu.');
            return redirect('/login');
        }
        // Sisa masa berlaku OTP aktif (detik) — untuk hitung mundur real-time di layar
        $active  = OtpCode::latestActive($email);
        $ttlLeft = $active ? max(0, strtotime((string) $active['expires_at']) - time()) : 0;

        return $this->view('auth/verify', [
            'title'    => 'Verifikasi OTP',
            'email'    => $email,
            'cooldown' => OtpCode::resendCooldown($email),
            'ttlLeft'  => $ttlLeft,
        ]);
    }

    public function verifyOtp(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $email = Session::get('otp_email');
        if (!is_string($email)) {
            // Fallback tahan-lumat sesi: email ikut terkirim sebagai hidden field;
            // bukti kepemilikan tetap KODE OTP 6 digit yang dicocokkan ke database.
            $email = $req->email('email');
            if (is_string($email)) {
                Session::set('otp_email', $email);
            }
        }
        if (!is_string($email)) {
            flash('warning', 'Sesi formulir hilang — minta kode OTP baru ya.');
            return redirect('/login');
        }
        // Gabungkan 6 kotak input (otp_1..otp_6) atau satu input penuh
        $code = $req->str('otp');
        if ($code === '') {
            $parts = [];
            for ($i = 1; $i <= 6; $i++) {
                $parts[] = preg_replace('/\D/', '', $req->str("otp_{$i}"));
            }
            $code = implode('', $parts);
        }
        $code = preg_replace('/\D/', '', $code) ?? '';

        $result = OtpService::verify($email, $code);
        if (!$result['ok']) {
            AuditLogger::record('login.failed', ['email' => $email, 'reason' => $result['message']], 'warning', null, $req->ip(), $req->userAgent());
            DiscordWebhook::logLogin($email, $req->ip(), false);
            flash('error', $result['message']);
            return redirect('/verify');
        }

        // OTP valid → dua skenario: email belum terdaftar (lanjut register)
        // atau akun lama (login langsung; tanpa password → wajib lengkapi)
        $user = User::findByEmail($email);
        $isNew = false;
        if (!$user) {
            Session::set('reg_email', $email);
            Session::set('reg_otp_ok', true);
            Session::forget('otp_email');
            flash('info', 'Email terverifikasi ✅ — langkah terakhir: atur username & password.');
            return redirect('/register/lengkapi');
        }
        if (($user['status'] ?? 'active') === 'banned') {
            AuditLogger::record('login.banned_attempt', ['email' => $email], 'warning', null, $req->ip(), $req->userAgent());
            DiscordWebhook::send('⛔ Akun Ter-Banned Mencoba Login', '', DiscordWebhook::COLOR_DANGER, [
                ['name' => 'Email', 'value' => $email, 'inline' => true],
                ['name' => 'IP', 'value' => $req->ip(), 'inline' => true],
            ]);
            Session::forget('otp_email');
            flash('error', 'Akun Anda diblokir. Hubungi admin ChiperX.');
            return redirect('/login');
        }

        // Secure session: regenerasi ID untuk mencegah session fixation
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::forget('otp_email');
        User::touchLogin((int) $user['id']);

        AuditLogger::record('login', ['email' => $email], 'info', (int) $user['id'], $req->ip(), $req->userAgent());
        DiscordWebhook::logLogin($email, $req->ip(), true);

        // Akun lama tanpa password → WAJIB atur username & password dulu
        if (empty($user['password_hash'] ?? null)) {
            flash('info', 'Satu langkah lagi: atur username & password untuk login cepat berikutnya. 🔑');
            return redirect('/akun/lengkapi');
        }

        // Admin/Owner WAJIB melewati gerbang rahasia (lapis ke-2) setiap login baru
        if (in_array($user['role'], ['admin', 'owner'], true)) {
            \ChiperX\Services\GateService::forget();
            flash('info', 'OTP diterima ✅ — satu lapis lagi untuk membuka panel.');
            return redirect(\ChiperX\Services\GateService::PATH);
        }

        flash('success', $isNew ? 'Selamat datang di ChiperX! Bonus koin sudah masuk 🎁' : 'Selamat datang kembali, ' . $user['name'] . '!');
        $intended = Session::get('intended');
        Session::forget('intended');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            return redirect($intended);
        }
        return redirect(match ($user['role']) {
            'owner' => '/owner',
            'admin' => '/admin',
            default => '/dashboard',
        });
    }

    public function resendOtp(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $email = Session::get('otp_email');
        if (!is_string($email)) {
            return redirect('/login');
        }
        $result = OtpService::request($email, $req->ip());
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        return redirect('/verify');
    }

    public function logout(Request $req): Response
    {
        $user = auth_user();
        if ($user) {
            AuditLogger::record('logout', [], 'info', (int) $user['id'], $req->ip(), $req->userAgent());
        }
        Session::destroy();
        return redirect('/');
    }

    /* ==================================================================
     * AUTH v2 — Username/Password + Magic Link & Registrasi 3 langkah
     * ================================================================== */

    /** GET /login/otp — jalur alternatif: login via kode OTP. */
    public function showLoginOtp(Request $req): string
    {
        $ref = preg_replace('/[^A-Z0-9]/', '', strtoupper($req->str('ref', '', 12)));
        if ($ref !== '') {
            Session::set('ref_code', $ref);
        }
        return $this->view('auth/login-otp', ['title' => 'Masuk via OTP', 'refCode' => Session::get('ref_code')]);
    }

    /** POST /auth/login — username/email + password → kirim magic link. */
    public function loginPassword(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $ip = $req->ip();

        $lock = \ChiperX\Services\LoginThrottle::lockedSeconds($ip);
        if ($lock > 0) {
            flash('error', "Terlalu banyak percobaan gagal. Terkunci — coba lagi dalam {$lock} detik.");
            return redirect('/login');
        }

        $identity = $req->str('identity', '', 190);
        $password = $req->str('password', '', 200);
        $user     = $identity !== '' ? User::findByIdentity($identity) : null;

        // Akun ada tapi belum punya password → arahkan ke jalur OTP
        if ($user && empty($user['password_hash'] ?? null)) {
            flash('warning', 'Akun ini belum mengatur password. Gunakan "Masuk via kode OTP", lalu atur password Anda.');
            return redirect('/login/otp');
        }

        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            \ChiperX\Services\LoginThrottle::recordFail($ip);
            sleep(2); // perlambat brute-force
            AuditLogger::record('login.password_failed', ['identity' => mb_substr($identity, 0, 60)], 'warning', null, $ip, $req->userAgent());
            DiscordWebhook::logLogin(mb_substr($identity, 0, 60), $ip, false);
            flash('error', 'Username/email atau password salah.');
            return redirect('/login');
        }

        if (($user['status'] ?? 'active') === 'banned') {
            AuditLogger::record('login.banned_attempt', ['email' => $user['email']], 'warning', null, $ip, $req->userAgent());
            flash('error', 'Akun Anda diblokir. Hubungi admin ChiperX.');
            return redirect('/login');
        }

        \ChiperX\Services\LoginThrottle::clear($ip);

        // Terbitkan magic link (sekali pakai, 10 menit)
        $token = \ChiperX\Models\LoginToken::create((int) $user['id'], $ip);
        $url   = rtrim(\ChiperX\Core\Config::appUrl(), '/') . '/auth/magic/' . $token;
        $sent  = \ChiperX\Services\MailService::sendMagicLink($user['email'], $url, \ChiperX\Models\LoginToken::TTL_MINUTES);

        Session::set('magic_pending', (int) $user['id']);
        Session::set('magic_sent_at', time());
        AuditLogger::record('login.magic_sent', ['email' => $user['email']], 'info', (int) $user['id'], $ip, $req->userAgent());
        if (!$sent) {
            flash('warning', 'Email tidak terkirim via SMTP — tautan dicatat di storage/logs/mail.log 📄');
        }
        return redirect('/login/cek-email');
    }

    /** GET /login/cek-email — ruang tunggu; polling otomatis melanjutkan. */
    public function checkEmail(Request $req): Response|string
    {
        $pending = Session::get('magic_pending');
        if (!is_int($pending)) {
            flash('warning', 'Masukkan username & password dulu.');
            return redirect('/login');
        }
        $user = User::find($pending);
        if (!$user) {
            Session::forget('magic_pending');
            return redirect('/login');
        }
        $sentAt = (int) Session::get('magic_sent_at', 0);
        return $this->view('auth/check-email', [
            'title'    => 'Cek Email',
            'email'    => $user['email'],
            'cooldown' => max(0, 60 - (time() - $sentAt)),
        ]);
    }

    /** GET /auth/login-status — JSON untuk polling halaman cek-email. */
    public function loginStatus(Request $req): Response
    {
        $user = auth_user();
        $redirect = '/dashboard';
        if ($user && in_array($user['role'], ['admin', 'owner'], true)) {
            $redirect = \ChiperX\Services\GateService::PATH;
        }
        return $this->json(['logged_in' => $user !== null, 'redirect' => $redirect]);
    }

    /** POST /auth/login/resend — kirim ulang magic link. */
    public function resendMagic(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $pending = Session::get('magic_pending');
        if (!is_int($pending)) {
            return redirect('/login');
        }
        $sentAt = (int) Session::get('magic_sent_at', 0);
        $sisa = 60 - (time() - $sentAt);
        if ($sisa > 0) {
            flash('error', "Tunggu {$sisa} detik sebelum kirim ulang.");
            return redirect('/login/cek-email');
        }
        $user = User::find($pending);
        if (!$user) {
            Session::forget('magic_pending');
            return redirect('/login');
        }
        $token = \ChiperX\Models\LoginToken::create((int) $user['id'], $req->ip());
        $url   = rtrim(\ChiperX\Core\Config::appUrl(), '/') . '/auth/magic/' . $token;
        \ChiperX\Services\MailService::sendMagicLink($user['email'], $url, \ChiperX\Models\LoginToken::TTL_MINUTES);
        Session::set('magic_sent_at', time());
        flash('success', 'Tautan baru telah dikirim ulang. 📧');
        return redirect('/login/cek-email');
    }

    /** GET /auth/magic/{token} — klik dari email → masuk. */
    public function magic(Request $req, array $params): Response
    {
        $userId = \ChiperX\Models\LoginToken::consume((string) ($params['token'] ?? ''));
        if ($userId === null) {
            flash('error', 'Tautan tidak valid atau sudah kedaluwarsa (10 menit / sekali pakai). Silakan login ulang.');
            return redirect('/login');
        }
        $user = User::find($userId);
        if (!$user) {
            return redirect('/login');
        }
        if (($user['status'] ?? 'active') === 'banned') {
            flash('error', 'Akun Anda diblokir. Hubungi admin ChiperX.');
            return redirect('/login');
        }

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::forget('magic_pending');
        Session::forget('magic_sent_at');
        User::touchLogin((int) $user['id']);
        AuditLogger::record('login.magic', ['email' => $user['email']], 'info', (int) $user['id'], $req->ip(), $req->userAgent());
        DiscordWebhook::logLogin($user['email'], $req->ip(), true);

        if (empty($user['password_hash'] ?? null)) {
            flash('info', 'Atur username & password untuk login cepat berikutnya. 🔑');
            return redirect('/akun/lengkapi');
        }
        if (in_array($user['role'], ['admin', 'owner'], true)) {
            \ChiperX\Services\GateService::forget();
            flash('info', 'Verifikasi email diterima ✅ — satu lapis lagi untuk membuka panel.');
            return redirect(\ChiperX\Services\GateService::PATH);
        }
        flash('success', 'Selamat datang kembali, ' . $user['name'] . '! ✨');
        $intended = Session::get('intended');
        Session::forget('intended');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            return redirect($intended);
        }
        return redirect('/dashboard');
    }

    /** GET /register — langkah 1: email. */
    public function showRegister(Request $req): string
    {
        $ref = preg_replace('/[^A-Z0-9]/', '', strtoupper($req->str('ref', '', 12)));
        if ($ref !== '') {
            Session::set('ref_code', $ref);
        }
        return $this->view('auth/register', ['title' => 'Daftar', 'refCode' => Session::get('ref_code')]);
    }

    /** POST /auth/register/send — kirim OTP registrasi. */
    public function registerSend(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $email = $req->email('email');
        if (!$email) {
            flash('error', 'Format email tidak valid.');
            return redirect('/register');
        }
        if (User::findByEmail($email)) {
            flash('warning', 'Email sudah terdaftar — silakan masuk.');
            return redirect('/login');
        }
        $result = OtpService::request($email, $req->ip());
        if (!$result['ok']) {
            flash('error', $result['message']);
            return redirect('/register');
        }
        Session::set('reg_email', $email);
        $note = ($result['mailed'] ?? false)
            ? 'Kode OTP dikirim ke ' . $email . '. Cek inbox/spam.'
            : $result['message'] . ' 📄';
        flash('success', $note);
        return redirect('/register/verify');
    }

    /** GET /register/verify — langkah 2: 6 kotak OTP. */
    public function showRegisterVerify(Request $req): Response|string
    {
        $email = Session::get('reg_email');
        if (!is_string($email)) {
            flash('warning', 'Masukkan email Anda dulu.');
            return redirect('/register');
        }
        $active  = OtpCode::latestActive($email);
        $ttlLeft = $active ? max(0, strtotime((string) $active['expires_at']) - time()) : 0;
        return $this->view('auth/verify', [
            'title'        => 'Verifikasi Email',
            'email'        => $email,
            'cooldown'     => OtpCode::resendCooldown($email),
            'ttlLeft'      => $ttlLeft,
            'action'       => '/auth/register/verify',
            'resendAction' => '/auth/register/send',
        ]);
    }

    /** POST /auth/register/verify — validasi OTP registrasi. */
    public function registerVerify(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $email = Session::get('reg_email');
        if (!is_string($email)) {
            // Fallback: email dari hidden field; OTP 6 digit tetap pembuktinya.
            $email = $req->email('email');
            if (is_string($email)) {
                Session::set('reg_email', $email);
            }
        }
        if (!is_string($email)) {
            flash('warning', 'Sesi formulir hilang — ulangi pendaftaran ya.');
            return redirect('/register');
        }
        $code = $req->str('otp');
        if ($code === '') {
            $parts = [];
            for ($i = 1; $i <= 6; $i++) {
                $parts[] = preg_replace('/\D/', '', $req->str("otp_{$i}"));
            }
            $code = implode('', $parts);
        }
        $code = preg_replace('/\D/', '', $code) ?? '';

        $result = OtpService::verify($email, $code);
        if (!$result['ok']) {
            flash('error', $result['message']);
            return redirect('/register/verify');
        }
        Session::set('reg_otp_ok', true);
        flash('success', 'Email terverifikasi ✅ — langkah terakhir!');
        return redirect('/register/lengkapi');
    }

    /** GET /register/lengkapi — langkah 3: username & password. */
    public function completeForm(Request $req): Response|string
    {
        if (!(is_string(Session::get('reg_email')) && Session::get('reg_otp_ok') === true)) {
            flash('warning', 'Verifikasi email dulu.');
            return redirect('/register');
        }
        return $this->view('auth/complete', [
            'title'    => 'Atur Username & Password',
            'heading'  => 'Hampir Selesai! 🎉',
            'intro'    => 'Email terverifikasi. Buat kredensial login Anda:',
            'emailCtx' => Session::get('reg_email'),
            'action'   => '/auth/register/complete',
        ]);
    }

    /** POST /auth/register/complete — buat akun penuh + auto-login. */
    public function completeSave(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $email = Session::get('reg_email');
        if (!(is_string($email) && Session::get('reg_otp_ok') === true)) {
            return redirect('/register');
        }
        if (User::findByEmail($email)) {
            Session::forget('reg_email');
            Session::forget('reg_otp_ok');
            flash('warning', 'Email sudah terdaftar — silakan masuk.');
            return redirect('/login');
        }

        $check = $this->validateCredentials($req);
        if ($check['error'] !== null) {
            flash('error', $check['error']);
            return redirect('/register/lengkapi');
        }

        // Referral dari session (bila datang via link undangan)
        $refCode  = Session::get('ref_code');
        $referrer = is_string($refCode) && $refCode !== '' ? User::findByReferralCode($refCode) : null;

        $id = User::createFull($email, $check['username'], $check['hash'], 'user', $referrer['referral_code'] ?? null);
        $user = User::find($id);

        DiscordWebhook::logRegister($email, $req->ip());
        AuditLogger::record('register', ['email' => $email, 'username' => $check['username']], 'info', $id, $req->ip(), $req->userAgent());

        if ($referrer && (int) $referrer['id'] !== $id) {
            $refBonus = max(0, (int) (Setting::get('referral_bonus') ?? '0'));
            if ($refBonus > 0) {
                User::addCoins((int) $referrer['id'], $refBonus);
            }
            AuditLogger::record('referral.reward', [
                'referrer' => $referrer['email'], 'new_user' => $email, 'bonus' => $refBonus,
            ], 'info', (int) $referrer['id'], $req->ip());
            DiscordWebhook::send('👥 Referral Baru', 'Anggota baru bergabung lewat link undangan.', DiscordWebhook::COLOR_PURPLE, [
                ['name' => 'Pengundang', 'value' => $referrer['email'], 'inline' => true],
                ['name' => 'Anggota Baru', 'value' => $email, 'inline' => true],
                ['name' => 'Bonus', 'value' => $refBonus . ' Coin', 'inline' => true],
            ]);
            \ChiperX\Services\AchievementService::checkAndUnlock((int) $referrer['id']);
        }

        Session::forget('reg_email');
        Session::forget('reg_otp_ok');
        Session::forget('ref_code');

        Session::regenerate();
        Session::set('user_id', $id);
        User::touchLogin($id);
        flash('success', 'Selamat datang di ChiperX! Bonus koin sudah masuk 🎁');
        return redirect('/dashboard');
    }

    /** GET /akun/lengkapi — akun lama tanpa password wajib melengkapi. */
    public function accountCompleteForm(Request $req): string
    {
        $user = auth_user();
        return $this->view('auth/complete', [
            'title'    => 'Atur Username & Password',
            'heading'  => 'Amankan Akun Anda 🔑',
            'intro'    => 'Atur username & password — mulai sekarang bisa login tanpa menunggu OTP.',
            'emailCtx' => $user['email'] ?? '',
            'action'   => '/akun/lengkapi',
        ]);
    }

    /** POST /akun/lengkapi */
    public function accountCompleteSave(Request $req): Response
    {
        Csrf::abortIfInvalid();
        $user = auth_user();
        if (!$user) {
            return redirect('/login');
        }
        $check = $this->validateCredentials($req, (int) $user['id']);
        if ($check['error'] !== null) {
            flash('error', $check['error']);
            return redirect('/akun/lengkapi');
        }
        User::setCredentials((int) $user['id'], $check['username'], $check['hash']);
        AuditLogger::record('account.set_credentials', [], 'info', (int) $user['id'], $req->ip());
        flash('success', 'Username & password tersimpan! ✅ Login berikutnya jauh lebih cepat.');

        if (in_array($user['role'], ['admin', 'owner'], true)) {
            \ChiperX\Services\GateService::forget();
            return redirect(\ChiperX\Services\GateService::PATH);
        }
        return redirect('/dashboard');
    }

    /**
     * Validasi bersama username + password + konfirmasi.
     * @return array{error:?string, username:string, hash:string}
     */
    private function validateCredentials(Request $req, ?int $selfId = null): array
    {
        $username = mb_strtolower(trim($req->str('username', '', 40)));
        $pass1    = $req->str('password', '', 200);
        $pass2    = $req->str('password2', '', 200);

        if (!preg_match('/^[a-z0-9_.]{3,40}$/', $username)) {
            return ['error' => 'Username 3–40 karakter: huruf kecil/angka/titik/garis bawah saja.', 'username' => '', 'hash' => ''];
        }
        $taken = \ChiperX\Core\Database::value('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?', [$username, $selfId ?? 0]);
        if ((int) $taken > 0) {
            return ['error' => 'Username itu sudah dipakai. Pilih yang lain.', 'username' => '', 'hash' => ''];
        }
        if (mb_strlen($pass1) < 6) {
            return ['error' => 'Password minimal 6 karakter.', 'username' => '', 'hash' => ''];
        }
        if ($pass1 !== $pass2) {
            return ['error' => 'Konfirmasi password tidak sama.', 'username' => '', 'hash' => ''];
        }
        return ['error' => null, 'username' => $username, 'hash' => password_hash($pass1, User::hashAlgo())];
    }
}
