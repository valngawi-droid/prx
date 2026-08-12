<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Models\OtpCode;

/**
 * Layanan OTP:
 *  - Generate 6 digit, TTL 10 menit (zona waktu WIB/Asia-Jakarta menyeluruh),
 *    disimpan sebagai hash Argon2id (fallback BCrypt pada build tanpa Argon2)
 *  - Cooldown kirim ulang 60 detik
 *  - Maksimal 5x percobaan verifikasi & 5x kirim per jam per email
 */
final class OtpService
{
    public const TTL_MINUTES       = 10;                    // masa berlaku kode (menit)
    private const TTL_SECONDS      = self::TTL_MINUTES * 60; // 600 detik
    private const RESEND_COOLDOWN  = 60;   // 60 detik
    private const MAX_VERIFY_TRIES = 5;
    private const MAX_SENDS_HOUR   = 5;

    /**
     * @return array{ok:bool, message:string, cooldown?:int, expires_in?:int}
     */
    public static function request(string $email, string $ip): array
    {
        $cooldown = OtpCode::resendCooldown($email, self::RESEND_COOLDOWN);
        if ($cooldown > 0) {
            return ['ok' => false, 'message' => "Tunggu {$cooldown} detik sebelum meminta kode baru.", 'cooldown' => $cooldown];
        }
        if (OtpCode::sendsThisHour($email) >= self::MAX_SENDS_HOUR) {
            return ['ok' => false, 'message' => 'Batas pengiriman OTP per jam tercapai. Coba lagi nanti.'];
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create($email, $otp, self::TTL_SECONDS, $ip);

        // sendOtp anti-gagal: bila SMTP bermasalah, kode tetap ditulis ke mail.log
        $mailed = MailService::sendOtp($email, $otp, (int) (self::TTL_SECONDS / 60));

        $message = $mailed
            ? 'Kode OTP telah dikirim ke email Anda.'
            : 'Kode OTP dibuat. (Email tidak terkirim — kode dicatat di storage/logs/mail.log di server)';
        return ['ok' => true, 'message' => $message, 'expires_in' => self::TTL_SECONDS, 'mailed' => $mailed];
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public static function verify(string $email, string $code): array
    {
        $record = OtpCode::latestActive($email);
        if (!$record) {
            return ['ok' => false, 'message' => 'Tidak ada kode OTP aktif. Silakan minta kode baru.'];
        }
        if ((int) $record['attempts'] >= self::MAX_VERIFY_TRIES) {
            OtpCode::consume((int) $record['id']);
            return ['ok' => false, 'message' => 'Terlalu banyak percobaan salah. Minta kode baru.'];
        }
        if (strtotime($record['expires_at']) < time()) {
            return ['ok' => false, 'message' => 'Kode OTP sudah kedaluwarsa (' . self::TTL_MINUTES . ' menit). Minta kode baru.'];
        }
        if (!password_verify($code, $record['otp_hash'])) {
            OtpCode::incrementAttempts((int) $record['id']);
            $sisa = self::MAX_VERIFY_TRIES - ((int) $record['attempts'] + 1);
            return ['ok' => false, 'message' => "Kode salah. Sisa percobaan: {$sisa}."];
        }
        OtpCode::consume((int) $record['id']);
        return ['ok' => true, 'message' => 'Verifikasi berhasil.'];
    }
}
