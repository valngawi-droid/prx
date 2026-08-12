<?php

declare(strict_types=1);

use ChiperX\Core\Config;
use ChiperX\Core\Csrf;
use ChiperX\Core\Response;
use ChiperX\Core\Session;

/**
 * Kumpulan helper global yang dipakai di Controllers & Views.
 */

/**
 * Versi rilis aplikasi — dipakai sebagai ?v= pada URL aset statis.
 * Dinaikkan setiap rilis supaya cache browser/CDN (Cloudflare) OTOMATIS
 * basi dan pengguna selalu menerima CSS/JS terbaru (anti halaman blank
 * karena file .js lama yang ke-cache!).
 */
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.8.0');
}

/** Escape output HTML — satu-satunya cara aman menampilkan data user (anti-XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return Config::appUrl() . '/' . ltrim($path, '/');
}

/** URL aset statis publik — dengan versi anti-cache (?v=APP_VERSION). */
function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/') . '?v=' . rawurlencode((string) APP_VERSION);
}

function redirect(string $to): Response
{
    return Response::redirect($to);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/** Flash message sekali tampil. */
function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/** Ambil flash lalu hapus (dipanggil layout). @return array<int, array{type:string,message:string}> */
function flashes(): array
{
    return Session::pullFlash();
}

/** Format angka Rupiah: 75000 → "Rp 75.000" */
function rupiah(int|float $angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/** Format angka ringkas: 12500 → "12,5rb" */
function singkat(int $n): string
{
    if ($n >= 1_000_000) {
        return rtrim(rtrim(number_format($n / 1_000_000, 1, ',', ''), '0'), ',') . 'jt';
    }
    if ($n >= 1_000) {
        return rtrim(rtrim(number_format($n / 1_000, 1, ',', ''), '0'), ',') . 'rb';
    }
    return (string) $n;
}

/** Waktu relatif bahasa Indonesia. */
function waktu_lalu(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'baru saja';
    }
    $map = [
        31536000 => 'tahun',
        2592000  => 'bulan',
        604800   => 'minggu',
        86400    => 'hari',
        3600     => 'jam',
        60       => 'menit',
    ];
    foreach ($map as $sec => $label) {
        if ($diff >= $sec) {
            return floor($diff / $sec) . ' ' . $label . ' lalu';
        }
    }
    return 'baru saja';
}

/** User yang sedang login (array) atau null. */
function auth_user(): ?array
{
    static $checked = false;
    static $user = null;

    if (!$checked) {
        $checked = true;
        $id = Session::get('user_id');
        if ($id) {
            try {
                $found = \ChiperX\Models\User::find((int) $id);
                if ($found && ($found['status'] ?? 'active') === 'active') {
                    $user = $found;
                    // Ping status online — max 1x menit per sesi (hemat DB di HP)
                    if ((int) (Session::get('last_ping') ?? 0) < time() - 60) {
                        Session::set('last_ping', time());
                        \ChiperX\Models\User::touchActivity((int) $id);
                    }
                } else {
                    // Akun hilang/di-ban → paksa logout
                    Session::destroy();
                    $checked = false;
                }
            } catch (\Throwable) {
                // DB tidak terjangkau → anggap tamu (halaman publik tetap hidup)
                $user = null;
            }
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return auth_user() !== null;
}

/** Warna & label nama hadiah koin. */
function label_hadiah(string $resultKey, int $reward): string
{
    return $reward > 0 ? $reward . ' Coin' : 'Zonk';
}

/**
 * Render badges profil pengguna (HTML aman — semua konten via e()).
 * Urutan: tag role (Member/Admin/ChiperX Owner, beranimasi) → centang biru → tags kustom.
 */
function user_badges(array $user): string
{
    $role = $user['role'] ?? 'user';
    $out  = match ($role) {
        'owner' => '<span class="badge badge-owner"><span class="badge-shine"></span>👑 ChiperX Owner</span>',
        'admin' => '<span class="badge badge-admin">🛡️ Admin</span>',
        default => '<span class="badge badge-member">Member</span>',
    };
    if (!empty($user['is_verified'])) {
        $out .= '<span class="badge-verify" title="Akun Terverifikasi">✓</span>';
    }
    $tags = array_filter(array_map('trim', explode(',', (string) ($user['badges'] ?? ''))));
    foreach (array_slice($tags, 0, 5) as $tag) {
        $out .= '<span class="badge badge-custom">' . e($tag) . '</span>';
    }
    return $out;
}

/** Apakah user memegang tag 💎 VIP / ⭐ Premium (fitur premium). */
function user_is_vip(array $user): bool
{
    $tags = (string) ($user['badges'] ?? '');
    return (bool) preg_match('~vip|premium~i', $tags);
}

/** Label kehadiran: 🟢 Online (<3 mnt) / "Aktif Xm lalu" / null (belum pernah). */
function online_label(?string $lastActivity): ?string
{
    if (!$lastActivity) {
        return null;
    }
    $diff = time() - strtotime($lastActivity);
    if ($diff < 180) {
        return '🟢 Online';
    }
    return 'Aktif ' . waktu_lalu($lastActivity);
}

/**
 * Warna tema (dapat diubah Owner dari web). Tahan DB-down → fallback default.
 * @return array{purple:string, cyan:string, green:string}
 */
function theme_colors(): array
{
    $def = ['purple' => '#a78bfa', 'cyan' => '#22d3ee', 'green' => '#4ade80'];
    try {
        foreach ($def as $name => $hex) {
            $saved = \ChiperX\Models\Setting::get('theme_' . $name);
            if (is_string($saved) && preg_match('/^#[0-9a-fA-F]{6}$/', $saved)) {
                $def[$name] = $saved;
            }
        }
    } catch (\Throwable) {
        // DB tak terjangkau → pakai default
    }
    return $def;
}

/**
 * 📝 Render teks chat ala WhatsApp/Telegram (AMAN dari XSS):
 *  - *tebal*  _miring_  ~coret~
 *  - URL otomatis jadi link biru (buka tab baru)
 *  - @username otomatis jadi tautan profil
 * Pakai: echo render_chat($body) — body MENTAH dari DB (belum di-escape).
 */
function render_chat(string $text): string
{
    $safe = e($text);
    // 1) URL → link (jalan di teks yang sudah di-escape: http/https aman)
    $safe = preg_replace(
        '~(https?://[^\s<]+)~i',
        '<a href="$1" target="_blank" rel="noopener nofollow" class="text-cyan-300 underline decoration-cyan-500/50 hover:text-cyan-200 break-all">$1</a>',
        (string) $safe
    );
    // 2) @mention → tautan profil (huruf/angka/titik/underscore)
    $safe = preg_replace(
        '/(?<![\w&;])@([a-z0-9_.]{3,20})\b/i',
        '<a href="/profil/@$1" class="text-violet-300 font-semibold hover:underline">@$1</a>',
        (string) $safe
    );
    // 3) Gaya WhatsApp — urutan: bold → strike → italic (italic terakhir agar tak memakan *)
    $safe = preg_replace('/\*([^*\n]+)\*/', '<b class="font-bold">$1</b>', (string) $safe);
    $safe = preg_replace('/~([^~\n]+)~/', '<span class="line-through opacity-80">$1</span>', (string) $safe);
    $safe = preg_replace('/(?<![\w*])_([^_\n]+)_(?![\w*])/', '<i>$1</i>', (string) $safe);
    return (string) $safe;
}

/**
 * 🏷️ Kirim notifikasi mention @username — dipakai channel & komentar (Discord/IG style).
 * @param string $text   teks yang dipindai
 * @param string $url    tujuan saat notif diklik
 * @param string $dari   nama penulis (untuk judul notif)
 * @param int[]  $skipId user id yang dilewati (penulis sendiri)
 */
function send_mentions(string $text, string $url, string $dari, array $skipId = []): void
{
    if (!preg_match_all('/(?<![\w&;])@([a-z0-9_.]{3,20})\b/i', $text, $m)) {
        return;
    }
    foreach (array_unique(array_map('strtolower', $m[1])) as $uname) {
        try {
            $u = \ChiperX\Models\User::findByUsername($uname);
            if (!$u || in_array((int) $u['id'], $skipId, true)) {
                continue;
            }
            \ChiperX\Models\Notification::add(
                (int) $u['id'],
                '🏷️ ' . $dari . ' menyebut kamu',
                mb_substr(trim(preg_replace('/\s+/', ' ', $text) ?? $text), 0, 90),
                $url,
                'mention'
            );
        } catch (\Throwable) {
            // username valid tapi user tak ada / DB error → lanjut saja
        }
    }
}

/** 🤬 AutoMod gaya Discord: sensor kata terlarang dari Setting owner (koma-separated). */
function sensor_kata(string $text): string
{
    static $words = null;
    if ($words === null) {
        $words = [];
        try {
            $raw = (string) \ChiperX\Models\Setting::get('banned_words', '');
            foreach (explode(',', mb_strtolower($raw)) as $w) {
                $w = trim($w);
                if ($w !== '' && mb_strlen($w) >= 2) {
                    $words[] = $w;
                }
            }
        } catch (\Throwable) {
            $words = [];
        }
    }
    if ($words === []) {
        return $text;
    }
    foreach ($words as $w) {
        $len = mb_strlen($w);
        $text = (string) preg_replace(
            '/(?<![\p{L}\p{N}])' . preg_quote($w, '/') . '(?![\p{L}\p{N}])/iu',
            str_repeat('🌟', min(3, $len)),
            $text
        );
    }
    return $text;
}
