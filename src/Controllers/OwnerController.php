<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Config;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\AppLog;
use ChiperX\Models\SiteLink;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\DiscordWebhook;
use ChiperX\Services\FileManagerService;
use ChiperX\Services\GameService;
use ChiperX\Services\MailService;
use ChiperX\Services\Payments\PaymentManager;
use ChiperX\Models\Setting;

/**
 * Panel Owner — GOD MODE:
 *  - Kelola Admin & User (add/edit/delete/ban)
 *  - File Manager server (tree/read/save/create/delete via Monaco Editor)
 *  - Atur link halaman All Link ChiperX
 *  - Atur RTP (win-rate) Mini Games
 *  - Viewer log audit + tes webhook Discord
 */
final class OwnerController extends Controller
{
    public function index(Request $req): string
    {
        return $this->panel('owner/index', [
            'title'       => 'Owner Control',
            'totalUsers'  => User::totalCount(),
            'totalAdmins' => \ChiperX\Core\Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
            'logsToday'   => AppLog::countToday(),
            'recentLogs'  => AppLog::recent(12),
        ]);
    }

    // =================== USER & ADMIN MANAGEMENT ===================

    public function users(Request $req): string
    {
        $page   = max(1, $req->int('page', 1));
        $search = $req->str('q', '', 60);
        $role   = $req->str('role', '', 10);
        return $this->panel('owner/users', [
            'title'   => 'Kelola Admin & User',
            'users'   => User::paginate($page, 15, $search, $role),
            'search'  => $search,
            'role'    => $role,
            'page'    => $page,
            'total'   => User::countFiltered($search, $role),
            'perPage' => 15,
        ]);
    }

    public function storeUser(Request $req): Response
    {
        $this->guardCsrf();
        $email = $req->email('email');
        $role  = $req->str('role', 'user', 10);
        if (!$email || User::findByEmail($email)) {
            flash('error', 'Email tidak valid / sudah terdaftar.');
            return redirect('/owner/users');
        }
        if (!in_array($role, ['admin', 'user'], true)) {
            $role = 'user';
        }
        User::createFromEmail($email, $role);
        AuditLogger::record('owner.user_create', ['email' => $email, 'role' => $role], 'warning', auth_user()['id'], $req->ip());
        DiscordWebhook::send('👑 Owner Menambah Akun', '', DiscordWebhook::COLOR_PURPLE, [
            ['name' => 'Email', 'value' => $email, 'inline' => true],
            ['name' => 'Role', 'value' => strtoupper($role), 'inline' => true],
        ]);
        flash('success', 'Akun ' . strtoupper($role) . ' dibuat.');
        return redirect('/owner/users');
    }

    public function updateUser(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $actor  = auth_user();
        $target = User::find((int) $params['id']);
        if (!$target) {
            flash('error', 'Akun tidak ditemukan.');
            return redirect('/owner/users');
        }
        if ($target['role'] === 'owner' && (int) $target['id'] !== (int) $actor['id']) {
            flash('error', 'Tidak dapat mengubah akun owner lain.');
            return redirect('/owner/users');
        }
        $role = $target['role'] === 'owner' ? 'owner' : (in_array($req->str('role'), ['admin', 'user'], true) ? $req->str('role') : 'user');
        User::adminUpdate((int) $target['id'], [
            'name'         => $req->str('name', '', 80) ?: $target['name'],
            'role'         => $role,
            'status'       => in_array($req->str('status'), ['active', 'banned'], true) ? $req->str('status') : 'active',
            'coin_balance' => max(0, $req->int('coin_balance', (int) $target['coin_balance'])),
        ]);
        // Tags kustom (opsional — hanya bila field dikirim)
        if ($req->input('badges') !== null) {
            User::setBadges((int) $target['id'], $req->str('badges', '', 190));
        }
        AuditLogger::record('owner.user_update', ['target' => $target['email'], 'role' => $role], 'warning', $actor['id'], $req->ip());
        flash('success', 'Akun diperbarui.');
        return redirect('/owner/users');
    }


    /* ==================================================================
     * INTEGRASI — semua API key bisa diedit dari web + diuji langsung
     * ================================================================== */

    /** Daftar kunci konfigurasi yang boleh dioverride via web (settings cfg_). */
    private const CFG_KEYS = [
        'APP_URL',
        'MAIL_DRIVER', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USERNAME', 'SMTP_PASSWORD',
        'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        'KIRIMEMAIL_API_KEY', 'KIRIMEMAIL_API_SECRET', 'KIRIMEMAIL_DOMAIN',
        'DISCORD_WEBHOOK_URL', 'DISCORD_ENABLED',
        'PAYMENT_DRIVER',
        'PAKASIR_SLUG', 'PAKASIR_API_KEY',
        'DUITKU_SANDBOX', 'DUITKU_MERCHANT_CODE', 'DUITKU_API_KEY',
        'TRIPAY_SANDBOX', 'TRIPAY_API_KEY', 'TRIPAY_PRIVATE_KEY', 'TRIPAY_MERCHANT_CODE',
        'MIDTRANS_SANDBOX', 'MIDTRANS_SERVER_KEY', 'MIDTRANS_CLIENT_KEY',
        'PAYDISINI_SANDBOX', 'PAYDISINI_API_KEY',
    ];
    /** Kunci bertipe checkbox (true/false). */
    private const CFG_BOOL_KEYS = ['DISCORD_ENABLED', 'DUITKU_SANDBOX', 'TRIPAY_SANDBOX', 'MIDTRANS_SANDBOX', 'PAYDISINI_SANDBOX'];

    /** GET /owner/integrations */
    public function integrations(): string
    {
        $values = [];
        foreach (self::CFG_KEYS as $key) {
            $values[$key] = Config::secret($key, \ChiperX\Core\Env::get($key, ''));
        }
        return $this->panel('owner/integrations', [
            'title'  => 'Integrasi & API Keys',
            'values' => $values,
            'bools'  => self::CFG_BOOL_KEYS,
        ]);
    }

    /** POST /owner/integrations — simpan semua override ke settings (cfg_*). */
    public function saveIntegrations(Request $req): Response
    {
        $this->guardCsrf();
        foreach (self::CFG_KEYS as $key) {
            if (in_array($key, self::CFG_BOOL_KEYS, true)) {
                Setting::set('cfg_' . $key, $req->input($key) !== null ? 'true' : 'false');
                continue;
            }
            $val = $req->str($key, '', 500);
            Setting::set('cfg_' . $key, trim($val));
        }
        Setting::flush();
        AuditLogger::record('owner.integrations_update', ['by' => auth_user()['email'] ?? '?'], 'critical', (int) (auth_user()['id'] ?? 0), $req->ip());
        DiscordWebhook::send('🔑 API Keys Diperbarui', 'Konfigurasi integrasi diubah dari panel web.', DiscordWebhook::COLOR_WARNING, [
            ['name' => 'Oleh', 'value' => auth_user()['email'] ?? '?', 'inline' => true],
        ]);
        flash('success', 'Semua API key tersimpan dan LANGSUNG berlaku (tanpa restart). Gunakan tombol Uji untuk memverifikasi. ✅');
        return redirect('/owner/integrations');
    }

    /** POST /owner/integrations/test — uji langsung: email | discord | payment. */
    public function testIntegration(Request $req): Response
    {
        $this->guardCsrf();
        $actor = auth_user();
        $kind  = $req->str('kind');

        if ($kind === 'email') {
            $pre = MailService::preflight();
            if (!$pre['ok']) {
                flash('error', '✗ Pra-uji gagal: ' . $pre['message']);
            } else {
                $ok = MailService::sendNotice(
                    (string) $actor['email'],
                    'Uji SMTP ChiperX ✅',
                    '<p style="color:#e2e8f0;">Jika email ini sampai, konfigurasi SMTP Anda <b style="color:#4ade80;">bekerja sempurna</b>.</p>'
                );
                $drv = strtoupper((string) Config::secret('MAIL_DRIVER', 'smtp'));
                flash($ok ? 'success' : 'error', $ok
                    ? '[' . $drv . '] Email uji terkirim ke ' . $actor['email'] . ' — cek Inbox (dan Spam).'
                    : '[' . $drv . '] ✗ Pengiriman ditolak: ' . (MailService::lastError() ?? 'penyebab tak diketahui') . ' — detail lengkap ada di storage/logs/mail.log');
            }

        } elseif ($kind === 'discord') {
            $ok = DiscordWebhook::send('🧪 Uji Webhook ChiperX', 'Kiriman percobaan dari panel Integrasi.', DiscordWebhook::COLOR_CYAN, [
                ['name' => 'Oleh', 'value' => (string) ($actor['email'] ?? '?'), 'inline' => true],
                ['name' => 'Waktu', 'value' => date('d M Y H:i') . ' WIB', 'inline' => true],
            ]);
            flash($ok ? 'success' : 'error', $ok
                ? 'Discord menerima webhook ✔ — cek channel Anda.'
                : 'Gagal — URL webhook salah/nonaktif, atau ekstensi curl belum terpasang.');

        } elseif ($kind === 'payment') {
            try {
                $res = PaymentManager::driver()->testConnection();
                flash($res['ok'] ? 'success' : 'error', '[' . strtoupper((string) Config::secret('PAYMENT_DRIVER', 'tripay')) . '] ' . $res['message']);
            } catch (\Throwable $e) {
                flash('error', 'Uji payment gagal: ' . $e->getMessage());
            }
        }
        AuditLogger::record('owner.integration_test', ['kind' => $kind], 'info', (int) ($actor['id'] ?? 0), $req->ip());
        return redirect('/owner/integrations');
    }

    /** POST /owner/users/{id}/verify — toggle centang biru. */
    public function verifyUser(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $target = User::find((int) $params['id']);
        if (!$target) {
            flash('error', 'Pengguna tidak ditemukan.');
            return redirect('/owner/users');
        }
        $now = empty($target['is_verified']);
        User::setVerified((int) $target['id'], $now);
        AuditLogger::record($now ? 'owner.verify_add' : 'owner.verify_remove', ['target' => $target['email']], 'critical', (int) auth_user()['id'], $req->ip());
        DiscordWebhook::send($now ? '✅ Centang Biru Diberikan' : '▫️ Centang Biru Dicabut', '', $now ? DiscordWebhook::COLOR_INFO : DiscordWebhook::COLOR_WARNING, [
            ['name' => 'Pengguna', 'value' => $target['email'], 'inline' => true],
            ['name' => 'Oleh', 'value' => auth_user()['email'] ?? '-', 'inline' => true],
        ]);
        flash('success', ($now ? '✓ Centang biru diberikan kepada ' : 'Centang biru dicabut dari ') . $target['email']);
        return redirect('/owner/users');
    }

    /** POST /owner/users/{id}/badges — atur tags kustom user mana pun. */
    public function badgesUser(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $target = User::find((int) $params['id']);
        if (!$target) {
            flash('error', 'Pengguna tidak ditemukan.');
            return redirect('/owner/users');
        }
        User::setBadges((int) $target['id'], $req->str('badges', '', 190));
        AuditLogger::record('owner.badges_update', ['target' => $target['email'], 'badges' => $req->str('badges', '', 190)], 'critical', (int) auth_user()['id'], $req->ip());
        flash('success', 'Tags ' . $target['email'] . ' diperbarui.');
        return redirect('/owner/users');
    }

    public function banUser(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $target = User::find((int) $params['id']);        if (!$target || $target['role'] === 'owner') {
            flash('error', 'Tidak dapat mem-ban akun ini.');
            return redirect('/owner/users');
        }
        $newStatus = $target['status'] === 'banned' ? 'active' : 'banned';
        User::setStatus((int) $target['id'], $newStatus);
        AuditLogger::record('owner.user_' . ($newStatus === 'banned' ? 'ban' : 'unban'), ['target' => $target['email']], 'critical', auth_user()['id'], $req->ip());
        DiscordWebhook::send($newStatus === 'banned' ? '🔨 Akun di-Ban' : '✅ Akun Diaktifkan Kembali', '', DiscordWebhook::COLOR_WARNING, [
            ['name' => 'Email', 'value' => $target['email'], 'inline' => true],
            ['name' => 'Oleh', 'value' => auth_user()['email'], 'inline' => true],
        ]);
        flash('success', 'Status akun: ' . strtoupper($newStatus));
        return redirect('/owner/users');
    }

    public function deleteUser(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $target = User::find((int) $params['id']);
        if (!$target || $target['role'] === 'owner') {
            flash('error', 'Tidak dapat menghapus akun ini.');
            return redirect('/owner/users');
        }
        User::deleteById((int) $target['id']);
        AuditLogger::record('owner.user_delete', ['target' => $target['email']], 'critical', auth_user()['id'], $req->ip());
        flash('success', 'Akun dihapus permanen.');
        return redirect('/owner/users');
    }

    // =================== FILE MANAGER (GOD MODE) ===================

    public function fileManager(Request $req): string
    {
        return $this->panel('owner/filemanager', ['title' => 'File Manager']);
    }

    public function fmTree(Request $req): Response
    {
        try {
            return $this->json(['ok' => true, 'tree' => FileManagerService::tree()]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function fmRead(Request $req): Response
    {
        try {
            $path = (string) ($req->input('path', ''));
            return $this->json(['ok' => true] + FileManagerService::read($path));
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function fmSave(Request $req): Response
    {
        if (!\ChiperX\Core\Csrf::validateRequest()) {
            return $this->json(['ok' => false, 'message' => 'CSRF tidak valid'], 419);
        }
        try {
            $body    = $req->json();
            $path    = (string) ($body['path'] ?? '');
            $content = (string) ($body['content'] ?? '');
            FileManagerService::save($path, $content, auth_user()['email']);
            return $this->json(['ok' => true, 'message' => 'File tersimpan + backup dibuat.']);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function fmCreate(Request $req): Response
    {
        if (!\ChiperX\Core\Csrf::validateRequest()) {
            return $this->json(['ok' => false, 'message' => 'CSRF tidak valid'], 419);
        }
        try {
            $body = $req->json();
            $path = (string) ($body['path'] ?? '');
            $kind = (string) ($body['kind'] ?? 'file');
            if ($kind === 'dir') {
                FileManagerService::mkdir($path, auth_user()['email']);
            } else {
                FileManagerService::create($path, auth_user()['email']);
            }
            return $this->json(['ok' => true, 'message' => 'Berhasil dibuat.']);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function fmDelete(Request $req): Response
    {
        if (!\ChiperX\Core\Csrf::validateRequest()) {
            return $this->json(['ok' => false, 'message' => 'CSRF tidak valid'], 419);
        }
        try {
            $body = $req->json();
            FileManagerService::delete((string) ($body['path'] ?? ''), auth_user()['email']);
            return $this->json(['ok' => true, 'message' => 'Berhasil dihapus.']);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // =================== KELOLA LINKS (All Link ChiperX) ===================

    public function links(Request $req): string
    {
        return $this->panel('owner/links', [
            'title' => 'Atur All Link',
            'links' => SiteLink::allLinks(),
        ]);
    }

    public function storeLink(Request $req): Response
    {
        $this->guardCsrf();
        $data = $this->validateLink($req);
        if (isset($data['error'])) {
            flash('error', $data['error']);
            return redirect('/owner/links');
        }
        SiteLink::create($data);
        AuditLogger::record('owner.link_create', ['title' => $data['title']], 'info', auth_user()['id'], $req->ip());
        flash('success', 'Link ditambahkan.');
        return redirect('/owner/links');
    }

    public function updateLink(Request $req, array $params): Response
    {
        $this->guardCsrf();
        if (!SiteLink::find((int) $params['id'])) {
            flash('error', 'Link tidak ditemukan.');
            return redirect('/owner/links');
        }
        $data = $this->validateLink($req);
        if (isset($data['error'])) {
            flash('error', $data['error']);
            return redirect('/owner/links');
        }
        SiteLink::updateById((int) $params['id'], $data);
        flash('success', 'Link diperbarui.');
        return redirect('/owner/links');
    }

    public function deleteLink(Request $req, array $params): Response
    {
        $this->guardCsrf();
        SiteLink::deleteById((int) $params['id']);
        flash('success', 'Link dihapus.');
        return redirect('/owner/links');
    }

    /** @return array<string, mixed> */
    private function validateLink(Request $req): array
    {
        $title = $req->str('title', '', 120);
        $url   = $req->str('url', '', 500);
        if ($title === '' || !preg_match('#^https?://#i', $url)) {
            return ['error' => 'Judul wajib diisi & URL harus diawali http(s)://'];
        }
        $icon = $req->str('icon_class', 'link', 30);
        if (!in_array($icon, ['discord', 'telegram', 'instagram', 'github', 'youtube', 'web', 'link'], true)) {
            $icon = 'link';
        }
        $color = $req->str('color', '#8b5cf6', 20);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#8b5cf6';
        }
        return [
            'title'      => $title,
            'url'        => $url,
            'icon_class' => $icon,
            'color'      => $color,
            'order_num'  => $req->int('order_num'),
            'is_active'  => $req->input('is_active') ? 1 : 0,
        ];
    }

    // =================== SETTINGS (RTP + Situs) ===================

    public function settings(Request $req): string
    {
        return $this->panel('owner/settings', [
            'title'   => 'Pengaturan Sistem & RTP',
            'settings' => Setting::all(),
            'weights' => GameService::allWeights(),
        ]);
    }

    public function saveSettings(Request $req): Response
    {
        $this->guardCsrf();
        $actor = auth_user();

        // Simpan RTP tiap game (tervalidasi total 100%)
        foreach (['gacha', 'mystery_box', 'card_flip'] as $game) {
            $error = GameService::saveWeights($game, $_POST);
            if ($error !== null) {
                flash('error', "RTP {$game}: {$error}");
                return redirect('/owner/settings');
            }
        }
        Setting::set('site_tagline', $req->str('site_tagline', '', 120) ?: 'ChiperX');
        Setting::set('register_bonus', (string) max(0, $req->int('register_bonus', 25)));
        Setting::set('daily_tickets', (string) max(1, min(10, $req->int('daily_tickets', 3))));
        Setting::set('daily_bonus', (string) max(0, $req->int('daily_bonus', 15)));
        Setting::set('referral_bonus', (string) max(0, $req->int('referral_bonus', 50)));

        // Tema tampilan web (warna divalidasi ketat hex #RRGGBB)
        foreach (['purple', 'cyan', 'green'] as $c) {
            $val = $req->str('theme_' . $c, '', 10);
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                Setting::set('theme_' . $c, strtolower($val));
            }
        }
        if ($req->input('hero_desc') !== null) {
            Setting::set('hero_desc', $req->str('hero_desc', '', 300));
        }

        // Kredensial gerbang rahasia /zszdgj/login — password di-hash bcrypt,
        // nilai mentah TIDAK PERNAH disimpan di mana pun.
        $gateUser = $req->str('admin_gate_user', '', 60);
        $gatePass = $req->str('admin_gate_pass', '', 200);
        if ($gateUser !== '' || $gatePass !== '') {
            \ChiperX\Services\GateService::updateCredentials($gateUser ?: null, $gatePass ?: null);
            AuditLogger::record('owner.gate_credentials_update', ['by' => $actor['email']], 'critical', (int) $actor['id'], $req->ip());
        }
        Setting::flush();

        AuditLogger::record('owner.settings_update', ['by' => $actor['email']], 'critical', $actor['id'], $req->ip());
        DiscordWebhook::send('⚙️ Pengaturan Sistem Diubah Owner', 'RTP game / konfigurasi situs diperbarui.', DiscordWebhook::COLOR_WARNING, [
            ['name' => 'Owner', 'value' => $actor['email'], 'inline' => true],
        ]);
        flash('success', 'Semua pengaturan tersimpan.');
        return redirect('/owner/settings');
    }

    // =================== LOG VIEWER & DISCORD TEST ===================

    public function logs(Request $req): string
    {
        $filter = $req->str('action', '', 60);
        return $this->panel('owner/logs', [
            'title'  => 'Audit Logs',
            'logs'   => AppLog::recent(100, $filter),
            'filter' => $filter,
        ]);
    }

    public function testDiscord(Request $req): Response
    {
        $this->guardCsrf();
        $ok = DiscordWebhook::send('🧪 Tes Webhook ChiperX', 'Jika pesan ini muncul, integrasi Discord AKTIF ✅', DiscordWebhook::COLOR_CYAN, [
            ['name' => 'Dites oleh', 'value' => auth_user()['email'], 'inline' => true],
        ]);
        flash($ok ? 'success' : 'error', $ok ? 'Webhook terkirim ke Discord ✅' : 'Gagal mengirim — cek DISCORD_WEBHOOK_URL di .env');
        return redirect('/owner');
    }

    // =================== API TOKENS (Public API) ===================

    public function apiTokens(Request $req): string
    {
        return $this->panel('owner/apitokens', [
            'title'  => 'API Tokens',
            'tokens' => \ChiperX\Models\ApiToken::all(),
            'scopes' => \ChiperX\Models\ApiToken::SCOPES,
        ]);
    }

    public function storeApiToken(Request $req): Response
    {
        $this->guardCsrf();
        $name   = $req->str('name', '', 80);
        $scopes = $_POST['scopes'] ?? [];
        $scopes = array_values(array_intersect(\ChiperX\Models\ApiToken::SCOPES, is_array($scopes) ? $scopes : []));
        if ($name === '' || $scopes === []) {
            flash('error', 'Nama token & minimal satu scope wajib diisi.');
            return redirect('/owner/api-tokens');
        }
        [, $plain] = \ChiperX\Models\ApiToken::create($name, implode(',', $scopes));
        AuditLogger::record('owner.api_token_create', ['name' => $name, 'scopes' => $scopes], 'warning', auth_user()['id'], $req->ip());
        flash('success', 'Token dibuat! Salin SEKARANG — hanya tampil sekali: ' . $plain);
        return redirect('/owner/api-tokens');
    }

    public function revokeApiToken(Request $req, array $params): Response
    {
        $this->guardCsrf();
        \ChiperX\Models\ApiToken::revoke((int) $params['id']);
        AuditLogger::record('owner.api_token_revoke', ['id' => (int) $params['id']], 'warning', auth_user()['id'], $req->ip());
        flash('success', 'Token dicabut.');
        return redirect('/owner/api-tokens');
    }
}
