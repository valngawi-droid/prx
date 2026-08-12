<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Pengiriman email via SMTP Gmail (Google App Password).
 * Memuat kelas PHPMailer secara eksplisit dari vendor agar tetap
 * berfungsi walau autoloader composer belum terdaftar.
 */
final class MailService
{
    /** Pesan kegagalan SMTP terakhir — untuk diagnosa langsung di UI. */
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Pra-uji SEBELUM kirim: kredensial terisi & server SMTP dapat dihubungi
     * dari perangkat ini (krusial di Termux — operator kadang memblokir port).
     * @return array{ok:bool,message:string}
     */
    public static function preflight(): array
    {
        $driver = strtolower(trim((string) Config::secret('MAIL_DRIVER', 'smtp')));
        if ($driver === 'log') {
            return ['ok' => false, 'message' => 'MAIL_DRIVER masih "log" — email hanya ditulis ke storage/logs/mail.log. Ubah ke "smtp" atau "kirimemail" lalu Simpan.'];
        }

        // ── Mode Kirim.Email API (HTTPS 443 — anti blokir port operator) ──
        if ($driver === 'kirimemail') {
            if (!function_exists('curl_init')) {
                return ['ok' => false, 'message' => 'Ekstensi curl belum aktif di PHP — wajib untuk mode API. Cek: php -m | grep curl'];
            }
            $ke = Config::kirimEmail();
            if ($ke['api_key'] === '' || $ke['api_secret'] === '') {
                return ['ok' => false, 'message' => 'KIRIMEMAIL_API_KEY / KIRIMEMAIL_API_SECRET masih kosong — isi di kartu "Kirim.Email API" lalu Simpan.'];
            }
            if ($ke['domain'] === '') {
                return ['ok' => false, 'message' => 'KIRIMEMAIL_DOMAIN masih kosong (contoh: chiperx.cyou) — isi lalu Simpan.'];
            }
            // Anti-ketuker: KIRIMEMAIL_DOMAIN harus domain PENGIRIM (bagian @ dari
            // MAIL_FROM_ADDRESS), BUKAN host SMTP seperti smtp.kirimemail.com.
            $fromDom = '';
            $fromAddr = (string) Config::smtp()['from'];
            if (str_contains($fromAddr, '@')) {
                $fromDom = substr($fromAddr, (int) strpos($fromAddr, '@') + 1);
            }
            if ($fromDom !== '' && mb_strtolower($ke['domain']) !== mb_strtolower($fromDom)) {
                return ['ok' => false, 'message' => "KIRIMEMAIL_DOMAIN salah isi (\"{$ke['domain']}\"). Yang benar = domain dari MAIL_FROM_ADDRESS-mu: \"{$fromDom}\" — bukan host smtp.*. Perbaiki lalu Simpan."];
            }
            $host = parse_url($ke['api_url'], PHP_URL_HOST);
            $host = is_string($host) && $host !== '' ? $host : 'smtp-app.kirim.email';
            $errno = 0;
            $errstr = '';
            $conn = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 7);
            if (!is_resource($conn)) {
                return ['ok' => false, 'message' => "Tidak bisa menghubungi {$host}:443 (" . trim($errstr !== '' ? $errstr : 'kode ' . $errno) . ') — cek koneksi internet HP.'];
            }
            fclose($conn);
            return ['ok' => true, 'message' => 'ok'];
        }

        // ── Mode SMTP klasik ──
        $cfg = Config::smtp();
        if ($cfg['username'] === '' || $cfg['password'] === '') {
            return ['ok' => false, 'message' => 'SMTP_USERNAME / SMTP_PASSWORD masih kosong — isi dulu lalu Simpan.'];
        }
        $scheme = $cfg['encryption'] === 'ssl' ? 'ssl://' : '';
        $errno  = 0;
        $errstr = '';
        $conn = @fsockopen($scheme . $cfg['host'], (int) $cfg['port'], $errno, $errstr, 7);
        if (!is_resource($conn)) {
            $why = trim($errstr !== '' ? $errstr : 'kode error ' . $errno);
            return ['ok' => false, 'message' => "HP tidak bisa menghubungi {$cfg['host']}:{$cfg['port']} ({$why}). Port kemungkinan diblokir jaringan — coba ganti port 587 ↔ 465 ↔ 2525 (Brevo mendukung ketiganya)."];
        }
        fclose($conn);
        return ['ok' => true, 'message' => 'ok'];
    }

    private static function boot(): void
    {
        if (!class_exists(PHPMailer::class)) {
            $base = BASE_PATH . '/vendor/phpmailer/phpmailer/src/';
            foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
                if (is_file($base . $file)) {
                    require_once $base . $file;
                }
            }
        }
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer belum terinstal. Jalankan: composer install');
        }
    }

    private static function makeMailer(): PHPMailer
    {
        self::boot();
        $cfg = Config::smtp();
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        // Enkripsi: ssl (465) / tls STARTTLS (587/2525) / none (tanpa enkripsi).
        if ($cfg['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($cfg['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure   = '';
            $mail->SMTPAutoTLS  = false;
        }
        $mail->Port       = $cfg['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;
        $mail->setFrom($cfg['from'], $cfg['from_name']);

        // SMTP_DEBUG=true → rekam seluruh percakapan SMTP mentah ke mail.log
        if (Config::secretBool('SMTP_DEBUG', false)) {
            $mail->SMTPDebug   = 2;
            $mail->Debugoutput = static function (string $str, int $level): void {
                self::writeLog('-', 'SMTP-DEBUG [level ' . $level . ']', trim($str));
            };
        }

        // ── Deliverability (anti-spam): hostname valid untuk HELO + Message-ID.
        //    Tanpa ini PHPMailer memakai "localhost" → mudah ditandai spam.
        $appHost = parse_url((string) \ChiperX\Core\Config::secret('APP_URL', ''), PHP_URL_HOST);
        $mail->Hostname = is_string($appHost) && $appHost !== '' ? $appHost : 'chiperx.app';
        $mail->XMailer  = 'ChiperX';
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');        // RFC 3834 — email transactional
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
        $mail->addCustomHeader('Importance', 'High');
        return $mail;
    }

    /**
     * Kirim kode OTP dengan template HTML estetis (dark + neon).
     * TIDAK PERNAH melempar exception — bila PHPMailer belum terinstal,
     * SMTP gagal/timeout, atau kredensial salah, email otomatis dialihkan
     * ke storage/logs/mail.log agar alur login TIDAK PERNAH berhenti (500).
     * @return bool true jika benar-benar terkirim via SMTP
     */
    public static function sendOtp(string $toEmail, string $otp, int $ttlMinutes = 5): bool
    {
        return self::deliver(
            $toEmail,
            'Kode OTP ChiperX: ' . $otp,
            self::otpTemplate($otp, $ttlMinutes),
            "OTP: {$otp} (berlaku {$ttlMinutes} menit). Jangan bagikan kepada siapa pun."
        );
    }

    /**
     * Email magic-link login (klik → masuk, tanpa mengetik OTP).
     * @return bool true jika terkirim via SMTP
     */
    public static function sendMagicLink(string $toEmail, string $url, int $ttlMinutes = 10): bool
    {
        $safeUrl = e($url);
        $inner = '<p style="color:#e2e8f0;font-size:15px;margin:0 0 20px;">Klik tombol di bawah untuk masuk ke akun ChiperX Anda — tanpa perlu mengetik kode apa pun:</p>'
            . '<p style="text-align:center;margin:26px 0;"><a href="' . $safeUrl . '" style="display:inline-block;padding:15px 42px;border-radius:12px;'
            . 'background:linear-gradient(90deg,#7c3aed,#06b6d4);color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;'
            . 'box-shadow:0 8px 28px rgba(124,58,237,.45);">🔓 Masuk ke ChiperX</a></p>'
            . '<p style="color:#94a3b8;font-size:13px;margin:0 0 10px;">Tautan berlaku <strong style="color:#a78bfa;">' . $ttlMinutes . ' menit</strong> dan hanya bisa dipakai sekali.</p>'
            . '<p style="color:#64748b;font-size:12px;word-break:break-all;">Jika tombol tidak berfungsi, salin tautan ini:<br>' . $safeUrl . '</p>'
            . '<p style="color:#64748b;font-size:12px;margin-top:14px;">Tidak merasa meminta login? Abaikan email ini — akun Anda tetap aman.</p>';
        return self::deliver($toEmail, 'Tautan Masuk ChiperX (berlaku ' . $ttlMinutes . ' menit)', self::wrapTemplate($inner), 'Masuk ke ChiperX: ' . $url);
    }

    /**
     * Email notifikasi umum (broadcast Admin & info pembayaran).
     * Sama seperti sendOtp: anti-gagal, fallback ke log. Return true bila terkirim.
     */
    public static function sendNotice(string $toEmail, string $subject, string $htmlBody): bool
    {
        return self::deliver($toEmail, $subject, self::wrapTemplate($htmlBody), strip_tags($htmlBody));
    }

    /**
     * Inti pengiriman. Mode log bila MAIL_DRIVER=log / SMTP kosong. Bila SMTP
     * aktif tapi gagal (PHPMailer absen, koneksi ditolak, auth salah, dsb.),
     * email TETAP ditulis ke storage/logs/mail.log dengan penanda FALLBACK.
     */
    private static function deliver(string $toEmail, string $subject, string $htmlBody, string $plainNote): bool
    {
        self::$lastError = null;
        $driver = strtolower(trim((string) Config::secret('MAIL_DRIVER', 'smtp')));
        if ($driver === 'kirimemail') {
            return self::deliverViaKirimEmail($toEmail, $subject, $htmlBody, $plainNote);
        }
        if (self::shouldLogOnly()) {
            self::writeLog($toEmail, $subject, $plainNote);
            return false;
        }
        try {
            $mail = self::makeMailer();
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject  = $subject;
            $mail->Body     = $htmlBody;
            $mail->AltBody  = $plainNote;
            $mail->Timeout  = 15;
            $mail->SMTPKeepAlive = false;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            // Fallback darurat — catat penyebabnya agar mudah didiagnosis
            $detail = trim($e->getMessage());
            if (isset($mail) && is_string($mail->ErrorInfo) && $mail->ErrorInfo !== '' && !str_contains($detail, $mail->ErrorInfo)) {
                $detail .= ' | ' . trim($mail->ErrorInfo);
            }
            self::$lastError = $detail;
            self::writeLog($toEmail, $subject . '  [FALLBACK — SMTP gagal: ' . $detail . ']', $plainNote);
            return false;
        }
    }

    /**
     * Kirim via Kirim.Email Transactional API v4 (HTTPS, port 443).
     * Penyelamat saat operator seluler memblokir port SMTP (587/465) —
     * port 443 hampir tidak pernah diblokir, jadi sangat cocok untuk Termux.
     * Auth: HTTP Basic (api_key : api_secret) + header "domain".
     */
    private static function deliverViaKirimEmail(string $toEmail, string $subject, string $htmlBody, string $plainNote): bool
    {
        $fail = static function (string $why) use ($toEmail, $subject, $plainNote): bool {
            self::$lastError = $why;
            self::writeLog($toEmail, $subject . '  [FALLBACK — Kirim.Email API: ' . $why . ']', $plainNote);
            return false;
        };

        if (!function_exists('curl_init')) {
            return $fail('Ekstensi curl tidak tersedia di PHP ini.');
        }
        $ke   = Config::kirimEmail();
        $smtp = Config::smtp();
        $from = $smtp['from'] !== '' ? $smtp['from'] : 'otp@' . $ke['domain'];
        $domain = $ke['domain'] !== '' ? $ke['domain']
            : (str_contains($from, '@') ? substr($from, (int) strpos($from, '@') + 1) : '');
        if ($ke['api_key'] === '' || $ke['api_secret'] === '' || $domain === '') {
            return $fail('KIRIMEMAIL_API_KEY / API_SECRET / DOMAIN belum lengkap — isi di Owner → Integrasi.');
        }

        $ch = curl_init($ke['api_url']);
        if ($ch === false) {
            return $fail('curl_init gagal.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $ke['api_key'] . ':' . $ke['api_secret'],
            CURLOPT_HTTPHEADER     => ['domain: ' . $domain],
            CURLOPT_POSTFIELDS     => [
                'from'    => $from,
                'to'      => $toEmail,
                'subject' => $subject,
                'text'    => $plainNote !== '' ? $plainNote : strip_tags($htmlBody),
                'html'    => $htmlBody,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch); // PHP 8.5: CurlHandle menutup otomatis; curl_close() deprecated.

        if ($resp === false) {
            return $fail('Koneksi API gagal: ' . ($err !== '' ? $err : 'tanpa pesan'));
        }
        if ($code >= 200 && $code < 300) {
            return true;
        }
        $hint = '';
        $respStr = trim((string) $resp);
        if ($code === 404 && stripos($respStr, 'Domain not found') !== false) {
            $fromDom = str_contains($from, '@') ? substr($from, (int) strpos($from, '@') + 1) : '?';
            $hint = " → Maksudnya: KIRIMEMAIL_DOMAIN (\"{$domain}\") tidak terdaftar di akun Kirim.Email-mu. Isi dengan domain pengirim: \"{$fromDom}\" (bukan host smtp.kirimemail.com).";
        } elseif ($code === 401 || $code === 403) {
            $hint = ' → API key/secret salah atau belum aktif — salin ulang dari dashboard app.kirim.email → Transactional → API.';
        }
        return $fail("API menolak (HTTP {$code}): " . substr($respStr, 0, 400) . $hint);
    }

    /** Mode log-aktif bila MAIL_DRIVER=log atau kredensial SMTP masih kosong. */
    private static function shouldLogOnly(): bool
    {
        $driver = \ChiperX\Core\Config::secret('MAIL_DRIVER', 'smtp');
        return $driver === 'log' || Config::smtp()['username'] === '';
    }

    /** Tulis "email" ke log pengembangan. */
    private static function writeLog(string $to, string $subject, string $body): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $entry = str_repeat('=', 70) . "\n"
            . '[' . date('Y-m-d H:i:s') . "] KEPADA: {$to}\nSUBJEK: {$subject}\n{$body}\n";
        file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }

    private static function wrapTemplate(string $innerHtml): string
    {
        return '<div style="margin:0;padding:32px;background:#0f172a;font-family:Segoe UI,Arial,sans-serif;">'
            . '<div style="max-width:520px;margin:0 auto;background:rgba(30,41,59,.95);border:1px solid rgba(139,92,246,.4);border-radius:16px;padding:32px;">'
            . '<h1 style="margin:0 0 8px;font-size:22px;color:#22d3ee;letter-spacing:2px;">ChiperX</h1>'
            . '<hr style="border:none;border-top:1px solid rgba(255,255,255,.08);margin:16px 0;">'
            . $innerHtml
            . '<p style="margin-top:28px;font-size:12px;color:#64748b;">&copy; ' . date('Y') . ' ChiperX — Jangan balas email ini.</p>'
            . '</div></div>';
    }

    private static function otpTemplate(string $otp, int $ttlMinutes): string
    {
        $digits = '';
        foreach (str_split($otp) as $d) {
            $digits .= '<td style="width:46px;height:56px;background:#0b1120;border:1px solid rgba(34,211,238,.5);border-radius:10px;'
                . 'color:#22d3ee;font-size:26px;font-weight:700;text-align:center;vertical-align:center;'
                . 'text-shadow:0 0 12px rgba(34,211,238,.9);">' . e($d) . '</td><td style="width:8px;"></td>';
        }
        return self::wrapTemplate(
            '<p style="color:#e2e8f0;font-size:15px;margin:0 0 16px;">Halo! Berikut kode verifikasi (OTP) untuk masuk ke akun ChiperX Anda:</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:8px auto 16px;"><tr>' . $digits . '</tr></table>'
            . '<p style="color:#94a3b8;font-size:13px;margin:0;">Kode berlaku <strong style="color:#a78bfa;">' . $ttlMinutes . ' menit</strong>. '
            . 'Jika Anda tidak meminta kode ini, abaikan email ini dan jangan bagikan kodenya kepada siapa pun.</p>'
        );
    }
}
