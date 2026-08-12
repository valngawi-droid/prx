<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Database;

/**
 * 🛡️ ChiperX FIREWALL — anti-deface/hack ringan ala WAF.
 *
 * Dua tingkat hukuman (sesuai kebijakan situs):
 *  - WARNING  → halaman PERINGATAN + ban IP 10 MENIT  (scanning, honeypot, flood)
 *  - CRITICAL → ban IP PERMANEN                     (payload deface/hack: SQLi, XSS, RCE, LFI)
 *  - ≥3 warning dalam 24 jam → otomatis naik jadi PERMANEN.
 *
 * Dipanggil sangat awal dari public/index.php sebelum routing.
 * Fail-open: bila DB mati, situs tetap hidup (uptime diutamakan).
 */
final class Firewall
{
    private const TEMP_SECONDS  = 600;   // 10 menit
    private const RATE_LIMIT    = 150;   // request per menit per IP
    private const STRIKE_LIMIT  = 3;     // warning ke-3 → permanen
    private const THREAT_WINDOW = '1 DAY';

    /**
     * Pola CRITICAL — murni niat merusak → BAN PERMANEN.
     * Dicocokkan pada URI+query yang sudah di-urldecode (lowercase).
     */
    private const CRITICAL = [
        'union select'          => 'SQL Injection (UNION SELECT)',
        'union all select'      => 'SQL Injection (UNION ALL)',
        'information_schema'    => 'SQL Injection (information_schema)',
        'into outfile'          => 'SQL Injection (INTO OUTFILE)',
        'load_file('            => 'SQL Injection (LOAD_FILE)',
        'drop table'            => 'SQL Injection (DROP TABLE)',
        'select * from'         => 'SQL Injection (SELECT *)',
        '<script'               => 'XSS (<script>)',
        'javascript:'           => 'XSS (javascript:)',
        'onerror='              => 'XSS (onerror=)',
        '../'                   => 'Path Traversal (../)',
        '%2e%2e'                => 'Path Traversal terenkripsi',
        '/etc/passwd'           => 'LFI (/etc/passwd)',
        '/etc/shadow'           => 'LFI (/etc/shadow)',
        '/proc/'                => 'LFI (/proc)',
        'boot.ini'              => 'LFI (boot.ini Windows)',
        'php://'                => 'Wrapper berbahaya (php://)',
        'phar://'               => 'Wrapper berbahaya (phar://)',
        'data://'               => 'Wrapper berbahaya (data://)',
        'expect://'             => 'Wrapper berbahaya (expect://)',
        'file://'               => 'Wrapper berbahaya (file://)',
        '<?php'                 => 'Injeksi kode PHP',
        'base64_decode'         => 'Obfuscation base64_decode',
        'eval('                 => 'Eksekusi eval()',
        'assert('               => 'Eksekusi assert()',
        'system('               => 'Command Injection (system)',
        'shell_exec'            => 'Command Injection (shell_exec)',
        'passthru('             => 'Command Injection (passthru)',
        'proc_open'             => 'Command Injection (proc_open)',
        'popen('                => 'Command Injection (popen)',
        'wget http'             => 'Downloader jarak jauh (wget)',
        'curl http'             => 'Downloader jarak jauh (curl)',
        '/bin/sh'               => 'Shell langsung (/bin/sh)',
        '/bin/bash'             => 'Shell langsung (/bin/bash)',
        'nc -e'                 => 'Netcat backdoor',
        'bash -c'               => 'Shell command (bash -c)',
        'uname -'               => 'Recon server (uname)',
        'whoami'                => 'Recon server (whoami)',
        '${'                    => 'Injeksi template ${}',
        "\x00"                  => 'Null-byte injection',
        '`'                     => 'Backtick command execution',
    ];

    /** Pola WARNING — scanning / jalan rahasia → PERINGATAN + ban 10 menit. */
    private const WARNING = [
        'wp-admin'        => 'Scan jalur WordPress',
        'wp-login'        => 'Scan jalur WordPress',
        'wp-content'      => 'Scan jalur WordPress',
        'xmlrpc.php'      => 'Scan XML-RPC',
        '/.env'           => 'Mencoba membaca .env',
        '/.git'           => 'Mencoba membaca .git',
        '/vendor/'        => 'Mencoba membaca /vendor',
        '/storage/'       => 'Mencoba membaca /storage',
        'phpmyadmin'      => 'Scan phpMyAdmin',
        '/pma/'           => 'Scan phpMyAdmin',
        'composer.json'   => 'Scan composer.json',
        'composer.lock'   => 'Scan composer.lock',
        'eval-stdin'      => 'Scan shell eval-stdin',
        'boaform'         => 'Exploit router boaform',
        'webshell'        => 'Mencari webshell',
        'shell.'          => 'Webshell (shell.*)',
        'c99.'            => 'Webshell c99',
        'r57.'            => 'Webshell r57',
        'alfa.'           => 'Webshell ALFA',
        'wso.'            => 'Webshell WSO',
        'indoxploit'      => 'Webshell IndoXploit',
        'b374k'           => 'Webshell b374k',
        'marijuana'       => 'Webshell Marijuana',
        '/admin.php'      => 'Scan admin.php',
        '/installer'      => 'Scan installer',
        '/test.php'       => 'Scan test.php',
        '/info.php'       => 'Scan info.php',
        '/phpinfo'        => 'Scan phpinfo',
    ];

    /** Pola WARNING berbentuk REGEX pada path (lebih tajam, anti salah-tangkap). */
    private const WARNING_REGEX = [
        '#/\.(sql|bak|old|swp|zip|tar|tgz|rar|gz|7z)$#'          => 'Scan file dump/arsip',
        '#^/(backup|backups|dump|db|database|data)($|[./_])#'     => 'Scan jalur backup/database',
        '#^/(shell|cmd|command|exec)#'                            => 'Mencari webshell',
    ];

    /** User-Agent alat serangan → WARNING. */
    private const BAD_AGENTS = [
        'sqlmap'    => 'Alat SQLMap',
        'nikto'     => 'Scanner Nikto',
        'nuclei'    => 'Scanner Nuclei',
        'acunetix'  => 'Scanner Acunetix',
        'gobuster'  => 'Scanner Gobuster',
        'dirbuster' => 'Scanner DirBuster',
        'hydra'     => 'Brute-force Hydra',
        'masscan'   => 'Scanner Masscan',
        'nmap'      => 'Scanner Nmap',
        'wpscan'    => 'Scanner WPScan',
    ];

    /** Titik masuk utama — dipanggil dari front controller TIAP request. */
    public static function guard(): void
    {
        $ip = self::clientIp();
        if ($ip === '' || self::isLoopback($ip)) {
            return; // request lokal server sendiri — jangan pernah diblok
        }

        try {
            // 0) Sudah diblokir? (auto-lepas bila masa hukuman habis)
            $ban = Database::one('SELECT * FROM banned_ips WHERE ip = ? LIMIT 1', [$ip]);
            if ($ban) {
                $perm = (int) $ban['permanent'] === 1;
                $untilTs = $ban['banned_until'] ? strtotime((string) $ban['banned_until']) : null;
                if ($perm || ($untilTs !== null && $untilTs > time())) {
                    Database::run('UPDATE banned_ips SET last_seen_at = NOW() WHERE ip = ?', [$ip]);
                    self::blockPage($ip, (string) $ban['reason'], $perm, $untilTs, (int) $ban['strikes']);
                }
                // Hukuman habis → bebaskan otomatis
                Database::run('DELETE FROM banned_ips WHERE ip = ?', [$ip]);
            }

            // 1) Sink pola serangan
            // CRITICAL → seluruh URI+query (SQLi/XSS umumnya di query)
            // WARNING  → path saja (anti jebakan ?q=c99 dari teman iseng)
            // '+' dinormalisasi jadi spasi agar 'union+select' tertangkap
            $target   = str_replace('+', ' ', strtolower(urldecode((string) ($_SERVER['REQUEST_URI'] ?? '/'))));
            $pathOnly = strtolower((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
            $pathOnly = strtolower(urldecode($pathOnly));
            foreach (self::CRITICAL as $needle => $label) {
                if (str_contains($target, $needle)) {
                    self::punish($ip, 'critical', $label, $target);
                }
            }
            foreach (self::WARNING as $needle => $label) {
                if (str_contains($pathOnly, $needle)) {
                    self::punish($ip, 'warning', $label, $target);
                }
            }
            foreach (self::WARNING_REGEX as $rx => $label) {
                if (preg_match($rx, $pathOnly)) {
                    self::punish($ip, 'warning', $label, $target);
                }
            }
            $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            foreach (self::BAD_AGENTS as $needle => $label) {
                if ($needle !== '' && str_contains($agent, $needle)) {
                    self::punish($ip, 'warning', 'User-Agent terlarang: ' . $label, $target);
                }
            }

            // 2) Rate limit (anti flood/brute-force massal)
            $bucket = date('YmdHi');
            Database::run(
                'INSERT INTO fw_rate (ip, bucket, hits) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE hits = IF(bucket = VALUES(bucket), hits + 1, 1), bucket = VALUES(bucket)',
                [$ip, $bucket]
            );
            $hits = (int) (Database::value('SELECT hits FROM fw_rate WHERE ip = ?', [$ip]) ?? 0);
            if ($hits > self::RATE_LIMIT) {
                self::punish($ip, 'warning', 'Flood request (' . $hits . '/menit)', $target);
            }

            // 3) Bersih-bersih sesekali (~2% request)
            if (random_int(1, 100) <= 2) {
                Database::run('DELETE FROM threat_log WHERE created_at < NOW() - INTERVAL 7 DAY');
                Database::run("DELETE FROM fw_rate WHERE bucket <> ?", [$bucket]);
            }
        } catch (\Throwable) {
            // DB sibuk/mati → jangan ganggu situs (fail-open)
        }
    }

    /** Eksekusi hukuman: catat ancaman → ban → tampilkan halaman → stop. */
    private static function punish(string $ip, string $level, string $label, string $uri): void
    {
        // Jurnal ancaman
        Database::run(
            'INSERT INTO threat_log (ip, level, pattern, uri, agent) VALUES (?, ?, ?, ?, ?)',
            [$ip, $level, $label, mb_substr($uri, 0, 250), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 250)]
        );

        // Riwayat warning 24 jam (untuk eskalasi → permanen)
        $warns = (int) (Database::value(
            'SELECT COUNT(*) FROM threat_log WHERE ip = ? AND created_at > NOW() - INTERVAL ' . self::THREAT_WINDOW,
            [$ip]
        ) ?? 0);

        $permanent = $level === 'critical' || $warns >= self::STRIKE_LIMIT;

        Database::run(
            'INSERT INTO banned_ips (ip, reason, permanent, strikes, banned_until, last_seen_at)
             VALUES (?, ?, ?, 1, ' . ($permanent ? 'NULL' : 'DATE_ADD(NOW(), INTERVAL ' . self::TEMP_SECONDS . ' SECOND)') . ', NOW())
             ON DUPLICATE KEY UPDATE
                reason = VALUES(reason), permanent = GREATEST(permanent, VALUES(permanent)),
                strikes = strikes + 1,
                banned_until = IF(permanent = 1, NULL, ' . ($permanent ? 'NULL' : 'VALUES(banned_until)') . '),
                last_seen_at = NOW()',
            [$ip, $permanent ? ('DEFACE/HACK: ' . $label) : $label, $permanent ? 1 : 0]
        );

        $row = Database::one('SELECT strikes, banned_until FROM banned_ips WHERE ip = ?', [$ip]);
        $untilTs = $row && $row['banned_until'] ? strtotime((string) $row['banned_until']) : null;

        // Lapor ke Audit + Discord owner (real-time!)
        try {
            AuditLogger::record(
                $permanent ? 'firewall.ban_permanent' : 'firewall.ban_temp',
                ['ip' => $ip, 'pola' => $label, 'uri' => mb_substr($uri, 0, 120)],
                'critical'
            );
            DiscordWebhook::send('🛡️ Firewall ChiperX: ' . ($permanent ? 'BAN PERMANEN ⛔' : 'Peringatan + Ban 10 Menit ⚠️'), '', DiscordWebhook::COLOR_DANGER, [
                ['name' => 'IP', 'value' => '`' . $ip . '`', 'inline' => true],
                ['name' => 'Serangan', 'value' => $label, 'inline' => true],
                ['name' => 'Target', 'value' => '`' . mb_substr($uri, 0, 90) . '`'],
            ]);
        } catch (\Throwable) {
        }

        self::blockPage($ip, ($permanent ? 'DEFACE/HACK: ' : '') . $label, $permanent, $untilTs, (int) ($row['strikes'] ?? 1));
    }

    // ------------------------------ utilitas ------------------------------

    public static function clientIp(): string
    {
        // Cloudflare Tunnel menitipkan IP asli di CF-Connecting-IP
        $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return substr($cf, 0, 45);
        }
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private static function isLoopback(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true);
    }

    /** Halaman blokir KECE (standalone, inline CSS — tidak butuh aset situs). */
    private static function blockPage(string $ip, string $reason, bool $permanent, ?int $untilTs, int $strikes): never
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $mode = $permanent ? 'perm' : 'temp';
        require BASE_PATH . '/src/Views/security/banned.php';
        exit;
    }

    // ------------------------- untuk Owner > Firewall -------------------------

    public static function bannedList(): array
    {
        return Database::all('SELECT * FROM banned_ips ORDER BY permanent DESC, id DESC LIMIT 100');
    }

    public static function threats(int $limit = 50): array
    {
        return Database::all('SELECT * FROM threat_log ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
    }

    public static function stats(): array
    {
        return [
            'active'     => (int) (Database::value('SELECT COUNT(*) FROM banned_ips WHERE permanent = 1 OR banned_until > NOW()', []) ?? 0),
            'permanent'  => (int) (Database::value('SELECT COUNT(*) FROM banned_ips WHERE permanent = 1', []) ?? 0),
            'threats24h' => (int) (Database::value('SELECT COUNT(*) FROM threat_log WHERE created_at > NOW() - INTERVAL 1 DAY', []) ?? 0),
            'critical24h' => (int) (Database::value("SELECT COUNT(*) FROM threat_log WHERE level = 'critical' AND created_at > NOW() - INTERVAL 1 DAY", []) ?? 0),
        ];
    }

    public static function unban(string $ip): void
    {
        Database::run('DELETE FROM banned_ips WHERE ip = ?', [substr($ip, 0, 45)]);
    }

    /** Ban manual dari dashboard owner. */
    public static function banManual(string $ip, string $reason, int $seconds): void
    {
        $perm = $seconds <= 0;
        Database::run(
            'INSERT INTO banned_ips (ip, reason, permanent, strikes, banned_until, last_seen_at)
             VALUES (?, ?, ?, 1, ' . ($perm ? 'NULL' : 'DATE_ADD(NOW(), INTERVAL ' . (int) $seconds . ' SECOND)') . ', NOW())
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), permanent = GREATEST(permanent, VALUES(permanent)),
             banned_until = IF(permanent = 1, NULL, ' . ($perm ? 'NULL' : 'VALUES(banned_until)') . '), last_seen_at = NOW()',
            [substr(trim($ip), 0, 45), mb_substr($reason, 0, 190) ?: 'Diblokir manual oleh Owner', $perm ? 1 : 0]
        );
    }
}
