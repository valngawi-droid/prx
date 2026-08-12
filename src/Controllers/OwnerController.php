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
        // 🕐 AUTO-BACKUP harian (bila diaktifkan Owner di halaman Backup)
        try {
            if (Setting::get('backup_auto', '0') === '1' && Setting::get('backup_auto_last', '') !== date('Y-m-d')) {
                Setting::set('backup_auto_last', date('Y-m-d'));
                $sql = $this->dumpDatabase();
                if ($sql !== null) {
                    $dir = BASE_PATH . '/storage/backups';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0750, true);
                    }
                    $file = $dir . '/chiperx-auto-' . date('Ymd') . '.sql.gz';
                    file_put_contents($file, gzencode($sql, 6));
                    AuditLogger::record('owner.backup_auto', ['file' => basename($file)], 'info', (int) auth_user()['id']);
                }
            }
        } catch (\Throwable) {
            // jangan ganggu pembukaan panel
        }

        // Data grafik 7 hari terakhir: pendaftaran baru & transaksi sukses
        $labels = $regSeries = $txSeries = [];
        try {
            $days = [];
            for ($i = 6; $i >= 0; $i--) {
                $days[] = date('Y-m-d', strtotime("-{$i} day"));
            }
            $regs = [];
            foreach (\ChiperX\Core\Database::all("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d") as $r) {
                $regs[$r['d']] = (int) $r['c'];
            }
            $txs = [];
            foreach (\ChiperX\Core\Database::all("SELECT DATE(created_at) d, COUNT(*) c FROM transactions WHERE status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d") as $r) {
                $txs[$r['d']] = (int) $r['c'];
            }
            foreach ($days as $d) {
                $labels[]    = date('d M', strtotime($d));
                $regSeries[] = $regs[$d] ?? 0;
                $txSeries[]  = $txs[$d] ?? 0;
            }
        } catch (\Throwable) {
            // grafik kosong saja bila DB belum siap
        }
        return $this->panel('owner/index', [
            'title'       => 'Owner Control',
            'totalUsers'  => User::totalCount(),
            'totalAdmins' => \ChiperX\Core\Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
            'logsToday'   => AppLog::countToday(),
            'recentLogs'  => AppLog::recent(12),
            'chartLabels' => $labels,
            'chartRegs'   => $regSeries,
            'chartTx'     => $txSeries,
        ]);
    }

    /** POST /owner/broadcast — kirim notifikasi lonceng ke SEMUA user. */
    public function broadcast(Request $req): Response
    {
        $this->guardCsrf();
        $title = $req->str('title', '', 120);
        $body  = $req->str('body', '', 300);
        if (mb_strlen($title) < 3) {
            flash('error', 'Judul broadcast minimal 3 karakter.');
            return redirect('/owner');
        }
        $sent = 0;
        foreach (\ChiperX\Core\Database::all('SELECT id FROM users') as $u) {
            \ChiperX\Models\Notification::add((int) $u['id'], '📣 ' . $title, $body !== '' ? $body : null, '/notifikasi', 'info');
            $sent++;
        }
        AuditLogger::record('owner.broadcast', ['title' => $title, 'sent' => $sent], 'critical', (int) auth_user()['id'], $req->ip());
        DiscordWebhook::send('📣 Broadcast Notifikasi', $title, DiscordWebhook::COLOR_WARNING, [
            ['name' => 'Terkirim ke', 'value' => $sent . ' user', 'inline' => true],
            ['name' => 'Oleh', 'value' => auth_user()['email'] ?? '?', 'inline' => true],
        ]);
        flash('success', "Broadcast terkirim ke 🔕 {$sent} user!");
        return redirect('/owner');
    }

    // ---------------- BACKUP DATABASE ----------------

    /** GET /owner/backups — daftar file cadangan. */
    public function backups(Request $req): string
    {
        $dir   = BASE_PATH . '/storage/backups';
        $files = [];
        foreach (glob($dir . '/*.sql.gz') ?: [] as $f) {
            $files[] = [
                'name' => basename($f),
                'size' => filesize($f) ?: 0,
                'time' => date('d M Y H:i', filemtime($f) ?: time()),
            ];
        }
        usort($files, static fn($a, $b) => strcmp($b['name'], $a['name']));
        return $this->panel('owner/backups', [
            'title' => 'Backup Database',
            'files' => $files,
        ]);
    }

    /** POST /owner/backups/create — mysqldump → .sql.gz (satu klik). */
    public function backupCreate(Request $req): Response
    {
        $this->guardCsrf();
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $sql = $this->dumpDatabase();
        if ($sql === null) {
            flash('error', 'Dump gagal/kosong — cek kredensial DB & status MariaDB (mysqldump terpasang?).');
            return redirect('/owner/backups');
        }
        $file = $dir . '/chiperx-' . date('Ymd-His') . '.sql.gz';
        file_put_contents($file, gzencode($sql, 6));
        AuditLogger::record('owner.backup_create', ['file' => basename($file)], 'critical', (int) auth_user()['id'], $req->ip());
        flash('success', 'Backup dibuat: ' . basename($file) . ' (' . round(filesize($file) / 1024, 1) . ' KB) 💾');
        return redirect('/owner/backups');
    }

    /** Dump SQL database via mysqldump/mariadb-dump → string|null. */
    private function dumpDatabase(): ?string
    {
        $cfg = Config::database();
        $bin = null;
        foreach (['mysqldump', 'mariadb-dump'] as $cand) {
            $p = trim((string) @shell_exec('command -v ' . $cand . ' 2>/dev/null'));
            if ($p !== '') {
                $bin = $cand;
                break;
            }
        }
        if ($bin === null) {
            return null;
        }
        $cmd = sprintf(
            '%s -h%s -P%s -u%s -p%s %s --single-transaction --quick 2>/dev/null',
            $bin,
            escapeshellarg((string) $cfg['host']),
            escapeshellarg((string) $cfg['port']),
            escapeshellarg((string) $cfg['user']),
            escapeshellarg((string) $cfg['pass']),
            escapeshellarg((string) $cfg['name'])
        );
        $sql = (string) @shell_exec($cmd);
        return strlen($sql) >= 200 ? $sql : null;
    }

    /** GET /owner/backups/{file}/download — unduh cadangan (owner only). */
    public function backupDownload(Request $req, array $params): Response
    {
        $name = basename((string) ($params['file'] ?? ''));
        $path = BASE_PATH . '/storage/backups/' . $name;
        if (!preg_match('/^chiperx-\d{8}-\d{6}\.sql\.gz$/', $name) || !is_file($path)) {
            return Response::html('File backup tidak ditemukan.', 404);
        }
        AuditLogger::record('owner.backup_download', ['file' => $name], 'critical', (int) auth_user()['id'], $req->ip());
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // =================== 🧯 FIREWALL (anti-deface/hack) ===================

    /** POST /owner/backups/restore — 🔁 kembalikan DB dari file .sql.gz (ketik RESTORE). */
    public function backupRestore(Request $req): Response
    {
        $this->guardCsrf();
        $actor = auth_user();
        if ($req->str('confirm', '', 12) !== 'RESTORE') {
            flash('error', 'Ketik RESTORE persis untuk konfirmasi — tindakan ini menimpa database!');
            return redirect('/owner/backups');
        }
        $name = basename((string) $req->str('file', '', 90));
        if (!preg_match('/^[a-zA-Z0-9\-_.]+\.sql\.gz$/', $name)) {
            flash('error', 'Nama file tidak sah.');
            return redirect('/owner/backups');
        }
        $path = BASE_PATH . '/storage/backups/' . $name;
        if (!is_file($path)) {
            flash('error', 'File backup tidak ditemukan.');
            return redirect('/owner/backups');
        }
        $cfg = Config::database();
        // Cari klien mysql/mariadb
        $bin = null;
        foreach (['mariadb', 'mysql'] as $cand) {
            $p = trim((string) @shell_exec('command -v ' . $cand . ' 2>/dev/null'));
            if ($p !== '') {
                $bin = $cand;
                break;
            }
        }
        if ($bin === null) {
            flash('error', 'Klien mysql/mariadb tidak ditemukan — jalankan: pkg install mariadb');
            return redirect('/owner/backups');
        }
        $cmd = sprintf(
            'gunzip -c %s | %s -h%s -P%s -u%s -p%s %s 2>&1',
            escapeshellarg($path),
            $bin,
            escapeshellarg((string) $cfg['host']),
            escapeshellarg((string) $cfg['port']),
            escapeshellarg((string) $cfg['user']),
            escapeshellarg((string) $cfg['pass']),
            escapeshellarg((string) $cfg['name'])
        );
        $out = (string) @shell_exec($cmd);
        AuditLogger::record('owner.backup_restore', ['file' => $name, 'by' => $actor['email']], 'critical', (int) $actor['id'], $req->ip());
        DiscordWebhook::send('🔁 Database DIPULIHKAN dari Backup', 'File: `' . $name . '`', DiscordWebhook::COLOR_DANGER, [
            ['name' => 'Oleh', 'value' => (string) $actor['email'], 'inline' => true],
            ['name' => 'Waktu', 'value' => date('d M Y H:i') . ' WIB', 'inline' => true],
        ]);
        flash($out === '' ? 'success' : 'warning', $out === ''
            ? 'Database berhasil dipulihkan dari ' . $name . ' ✅ Muat ulang halaman ini.'
            : 'Restore selesai dengan pesan: ' . mb_substr($out, 0, 300));
        return redirect('/owner/backups');
    }

    /** POST /owner/backups/auto — 🕐 aktif/matikan auto-backup harian. */
    public function backupAutoToggle(Request $req): Response
    {
        $this->guardCsrf();
        Setting::set('backup_auto', $req->input('on') !== null ? '1' : '0');
        Setting::flush();
        flash('success', $req->input('on') !== null
            ? 'Auto-backup HARIAN aktif — dibuat otomatis saat Owner membuka panel tiap hari. 🕐💾'
            : 'Auto-backup harian dimatikan.');
        return redirect('/owner/backups');
    }

    /** POST /owner/clean — 🧹 pembersih data sekali klik. */
    public function clean(Request $req): Response
    {
        $this->guardCsrf();
        $actor = auth_user();
        $db    = \ChiperX\Core\Database::class;
        $steps = [];
        $try = static function (string $label, callable $fn) use (&$steps): void {
            try {
                $n = $fn();
                $steps[] = $label . ': ' . $n;
            } catch (\Throwable) {
                $steps[] = $label . ': dilewati';
            }
        };
        $try('Stories >2 hari', static fn () => $db::run('DELETE FROM stories WHERE created_at < NOW() - INTERVAL 2 DAY')->rowCount());
        $try('Typing indicator basi', static fn () => $db::run('DELETE FROM dm_typing WHERE ts < NOW() - INTERVAL 1 HOUR')->rowCount());
        $try('DM musnah (self-destruct)', static fn () => $db::run('DELETE FROM messages WHERE expire_at IS NOT NULL AND expire_at < NOW() - INTERVAL 1 HOUR')->rowCount());
        $try('Notifikasi >30 hari', static fn () => $db::run('DELETE FROM notifications WHERE created_at < NOW() - INTERVAL 30 DAY')->rowCount());
        $try('Jurnal ancaman >30 hari', static fn () => $db::run('DELETE FROM threat_log WHERE created_at < NOW() - INTERVAL 30 DAY')->rowCount());
        $try('Shoutbox >7 hari', static fn () => $db::run('DELETE FROM shouts WHERE created_at < NOW() - INTERVAL 7 DAY')->rowCount());
        $try('Rate-limit kemarin', static fn () => $db::run('DELETE FROM fw_rate WHERE bucket < ?', [date('YmdHi', strtotime('-2 hours'))])->rowCount());
        $try('File tmp upload', static fn () => \ChiperX\Services\UploadService::pruneTmp(3600));

        AuditLogger::record('owner.cleanup', ['steps' => $steps], 'warning', (int) $actor['id'], $req->ip());
        flash('success', 'Pembersihan selesai 🧹✨ — ' . implode(' · ', $steps) . ' dihapus.');
        return redirect('/owner/firewall');
    }

    /** GET /owner/export/{what}.csv — ⬇️ ekspor logs & threat log. */
    public function exportCsv(Request $req, array $params): Response
    {
        $what = (string) ($params['what'] ?? '');
        switch ($what) {
            case 'logs':
                $head = ['ID', 'User ID', 'Aksi', 'Level', 'IP', 'Waktu'];
                $rows = \ChiperX\Core\Database::all('SELECT id, user_id, action, level, ip, created_at FROM logs ORDER BY id DESC LIMIT 5000');
                break;
            case 'threats':
                $head = ['ID', 'IP', 'Level', 'Pola', 'URI', 'Agen', 'Waktu'];
                $rows = \ChiperX\Core\Database::all('SELECT id, ip, level, pattern, uri, agent, created_at FROM threat_log ORDER BY id DESC LIMIT 5000');
                break;
            default:
                flash('error', 'Jenis ekspor tidak dikenal.');
                return redirect('/owner');
        }
        AuditLogger::record('owner.export_csv', ['what' => $what], 'warning', (int) auth_user()['id'], $req->ip());
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="chiperx-' . $what . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    public function firewall(Request $req): string
    {
        $bans = []; $threats = []; $stats = ['active' => 0, 'permanent' => 0, 'threats24h' => 0, 'critical24h' => 0];
        $chart7 = [];
        try {
            $bans    = \ChiperX\Services\Firewall::bannedList();
            $threats = \ChiperX\Services\Firewall::threats(60);
            $stats   = \ChiperX\Services\Firewall::stats();
            $chart7  = \ChiperX\Core\Database::all(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c, COALESCE(SUM(level = 'critical'),0) AS crit
                 FROM threat_log WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)"
            );
        } catch (\Throwable) {
            flash('warning', 'Tabel firewall belum ada — jalankan migrasi 010 dulu ya.');
        }
        return $this->panel('owner/firewall', [
            'title'   => '🧯 Firewall',
            'bans'    => $bans,
            'threats' => $threats,
            'stats'   => $stats,
            'chart7'  => $chart7,
        ]);
    }

    public function firewallBan(Request $req): Response
    {
        $this->guardCsrf();
        $ip      = trim($req->str('ip', '', 45));
        $reason  = $req->str('reason', '', 190);
        $dur     = $req->str('duration', '600', 10);
        $seconds = $dur === 'perm' ? 0 : max(60, (int) $dur);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            flash('error', 'Format IP tidak valid.');
            return redirect('/owner/firewall');
        }
        \ChiperX\Services\Firewall::banManual($ip, $reason !== '' ? $reason : 'Diblokir manual oleh Owner', $seconds);
        AuditLogger::record('owner.firewall_ban', ['ip' => $ip, 'dur' => $dur, 'reason' => $reason], 'critical', (int) auth_user()['id'], $req->ip());
        flash('success', "IP {$ip} diblokir " . ($dur === 'perm' ? 'PERMANEN ⛔' : 'selama ' . $dur . ' detik') . '.');
        return redirect('/owner/firewall');
    }

    public function firewallUnban(Request $req): Response
    {
        $this->guardCsrf();
        $ip = trim($req->str('ip', '', 45));
        if ($ip === '') {
            flash('error', 'IP kosong.');
            return redirect('/owner/firewall');
        }
        \ChiperX\Services\Firewall::unban($ip);
        AuditLogger::record('owner.firewall_unban', ['ip' => $ip], 'warning', (int) auth_user()['id'], $req->ip());
        flash('success', "Ban IP {$ip} dicabut ✅");
        return redirect('/owner/firewall');
    }

    // =================== 🤖 AI SENTINEL (petugas keamanan 24/7) ===================

    public function sentinel(Request $req): string
    {
        $stats   = ['active' => 0, 'permanent' => 0, 'threats24h' => 0, 'critical24h' => 0];
        $threats = [];
        try {
            $stats   = \ChiperX\Services\Firewall::stats();
            $threats = \ChiperX\Services\Firewall::threats(15);
        } catch (\Throwable) {
        }
        return $this->panel('owner/sentinel', [
            'title'    => '🤖 AI Sentinel',
            'stats'    => $stats,
            'threats'  => $threats,
            'aiAnswer' => null,
        ]);
    }

    /** POST /owner/sentinel/analyze — kirim ringkasan ancaman ke Chat AI untuk dianalisis. */
    public function sentinelAnalyze(Request $req): Response|string
    {
        $this->guardCsrf();
        $stats   = ['active' => 0, 'permanent' => 0, 'threats24h' => 0, 'critical24h' => 0];
        $threats = [];
        try {
            $stats   = \ChiperX\Services\Firewall::stats();
            $threats = \ChiperX\Services\Firewall::threats(15);
        } catch (\Throwable) {
        }

        // Susun ringkasan untuk AI
        $ringkas = "STATISTIK 24 JAM: ancaman={$stats['threats24h']}, kritis={$stats['critical24h']}, ban-aktif={$stats['active']}, ban-permanen={$stats['permanent']}.\n";
        $ringkas .= "ANCAMAN TERAKHIR:\n";
        foreach (array_slice($threats, 0, 12) as $t) {
            $ringkas .= "- [{$t['level']}] {$t['ip']} → {$t['pattern']} ({$t['uri']})\n";
        }
        $prompt = "Kamu adalah analis keamanan siber senior yang menjaga situs komunitas PHP (ChiperX). "
            . "Berikut data firewall 24 jam terakhir:\n{$ringkas}\n"
            . "Tugasmu (Bahasa Indonesia, ringkas poin-poin): 1) nilai tingkat risiko (rendah/sedang/tinggi), "
            . "2) jenis serangan yang sedang dicoba, 3) 5 langkah konkret yang harus dilakukan owner, "
            . "4) IP yang paling berbahaya jika ada. Maks 200 kata.";

        $answer = null;
        try {
            $res = \ChiperX\Services\CodexApi::call('/api/ai/chatai', [
                'teks' => $prompt, 'model' => 'claude', 'session' => 'sentinel-owner', 'stream' => 'false',
            ], 'POST', 40);
            if ($res['ok'] && is_array($res['data'])) {
                $answer = $this->pluckText($res['data']);
            } else {
                flash('warning', 'AI sedang sibuk (' . ($res['error'] ?? 'HTTP ' . $res['status']) . ') — coba lagi sebentar.');
            }
        } catch (\Throwable) {
            flash('error', 'Tidak bisa menghubungi layanan AI.');
        }
        if ($answer !== null) {
            AuditLogger::record('owner.sentinel_analyze', [], 'info', (int) auth_user()['id'], $req->ip());
        }
        return $this->panel('owner/sentinel', [
            'title'    => '🤖 AI Sentinel',
            'stats'    => $stats,
            'threats'  => $threats,
            'aiAnswer' => $answer,
        ]);
    }

    /** Tarik teks jawaban dari berbagai bentuk respons API AI. */
    private function pluckText(array $data, int $depth = 0): ?string
    {
        if ($depth > 5) {
            return null;
        }
        foreach (['result', 'answer', 'response', 'message', 'text', 'data', 'output', 'content'] as $k) {
            if (!isset($data[$k])) {
                continue;
            }
            if (is_string($data[$k]) && strlen(trim($data[$k])) > 10) {
                return trim($data[$k]);
            }
            if (is_array($data[$k])) {
                $r = $this->pluckText($data[$k], $depth + 1);
                if ($r !== null) {
                    return $r;
                }
            }
        }
        return null;
    }

    // =================== 🩺 CEK SEMUA API (30 endpoint) ===================

    public function apiHealth(Request $req): string
    {
        @set_time_limit(280);
        $rows = [];
        foreach (array_keys(\ChiperX\Services\CodexApi::tools()) as $key) {
            $rows[$key] = \ChiperX\Services\CodexApi::test($key);
        }
        AuditLogger::record('owner.api_health', ['n' => count($rows)], 'info', (int) auth_user()['id'], $req->ip());
        return $this->panel('owner/api_health', [
            'title' => '🩺 Kesehatan 30 API',
            'tools' => \ChiperX\Services\CodexApi::tools(),
            'rows'  => $rows,
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
        'CODEX_API_KEY',
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
        // Panel "konfigurasi AKTIF saat ini" — beserta SUMBERNYA (web DB / .env / bawaan),
        // supaya tidak ada lagi kebingungan "kok masih pakai konfigurasi lama?".
        $live = [];
        foreach (['MAIL_DRIVER', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'SMTP_HOST', 'KIRIMEMAIL_DOMAIN', 'APP_URL', 'PAYMENT_DRIVER'] as $k) {
            try {
                $dbVal = Setting::get('cfg_' . $k);
            } catch (\Throwable) {
                $dbVal = null;
            }
            $envVal = \ChiperX\Core\Env::get($k, '');
            $live[$k] = [
                'value'  => (string) Config::secret($k, ''),
                'source' => ($dbVal !== null && $dbVal !== '') ? 'web (DB)' : (($envVal !== '') ? '.env' : 'bawaan'),
            ];
        }
        return $this->panel('owner/integrations', [
            'title'  => 'Integrasi & API Keys',
            'values' => $values,
            'bools'  => self::CFG_BOOL_KEYS,
            'live'   => $live,
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
        \ChiperX\Models\Notification::add(
            (int) $target['id'],
            $now ? '✅ Kamu mendapat Centang Biru!' : '▫️ Centang biru dicabut',
            $now ? 'Selamat — akunmu kini berstatus terverifikasi resmi ChiperX.' : 'Silakan hubungi owner bila ada pertanyaan.',
            '/profil',
            $now ? 'success' : 'warning'
        );
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
        $newCsv = trim((string) $req->str('badges', '', 190));
        \ChiperX\Models\Notification::add(
            (int) $target['id'],
            '🏷️ Tags profilmu diperbarui',
            $newCsv !== '' ? 'Tag barumu: ' . $newCsv : 'Semua tag kustom dihapus.',
            '/profil',
            'info'
        );
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

        // 🖼️ GAMBAR SITUS (logo + OG image): tempel LINK atau UPLOAD dari HP
        foreach (['site_logo' => 'Logo situs', 'site_og_image' => 'Gambar OG (preview share)'] as $imgKey => $label) {
            if ($req->input($imgKey . '_clear') !== null) {
                Setting::set($imgKey, '');
            } else {
                $link = trim($req->str($imgKey . '_link', '', 500));
                if ($link !== '') {
                    if (!preg_match('~^https?://~i', $link)) {
                        flash('error', $label . ': link harus diawali http(s)://');
                        return redirect('/owner/settings');
                    }
                    Setting::set($imgKey, $link);
                }
                $f = $_FILES[$imgKey . '_file'] ?? null;
                if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $nm = \ChiperX\Services\UploadService::saveImage($f, 'situs', 4);
                        Setting::set($imgKey, \ChiperX\Services\UploadService::publicUrl('situs', $nm));
                    } catch (\RuntimeException $e) {
                        flash('error', $label . ': ' . $e->getMessage());
                        return redirect('/owner/settings');
                    }
                }
            }
        }

        // 🚧 Mode pemeliharaan + ⚡ pengali koin event
        $wasMaint = Setting::get('maintenance_mode', '0') === '1';
        $nowMaint = $req->input('maintenance_mode') !== null;
        Setting::set('maintenance_mode', $nowMaint ? '1' : '0');
        if ($req->input('maintenance_note') !== null) {
            Setting::set('maintenance_note', $req->str('maintenance_note', '', 190));
        }
        if ($nowMaint !== $wasMaint) {
            DiscordWebhook::send(
                $nowMaint ? '🚧 Mode Pemeliharaan AKTIF' : '✅ Mode Pemeliharaan SELESAI',
                $nowMaint ? 'Situs ditutup sementara untuk umum — staf tetap bisa akses.' : 'Situs kembali online untuk semua pengunjung.',
                $nowMaint ? DiscordWebhook::COLOR_WARNING : DiscordWebhook::COLOR_SUCCESS,
                [['name' => 'Oleh', 'value' => (string) $actor['email'], 'inline' => true]]
            );
        }
        $mult = (float) str_replace(',', '.', $req->str('coin_multiplier', '1', 6));
        Setting::set('coin_multiplier', (string) max(0.5, min(10.0, $mult)));

        // 🤬 AutoMod (Discord): daftar kata terlarang — disensor 🌟 di kanal
        if ($req->input('banned_words') !== null) {
            Setting::set('banned_words', mb_substr(trim($req->str('banned_words', '', 300)), 0, 300));
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
