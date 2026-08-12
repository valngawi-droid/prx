<?php

declare(strict_types=1);

namespace ChiperX\Services;

use ChiperX\Core\Config;

/**
 * 🚀 CodexApi — jembatan ke 30+ API alwayscodex.my.id
 * (Chat AI, canvas/maker, downloader, stalker, tools, HD enhancer).
 *
 * CODEX_API_KEY opsional (dikirim sebagai Bearer bila diisi di Owner > Integrasi).
 */
final class CodexApi
{
    public const BASE = 'https://api.alwayscodex.my.id';

    /**
     * Registry 30 tools.
     * field: [name, label, type(text|textarea|number|select|file|url), required, placeholder|options]
     */
    public static function tools(): array
    {
        return [
            // ─────────────────────────── 🤖 AI ───────────────────────────
            'chatai' => [
                'name' => 'Chat AI (Claude/GPT/Gemini)', 'icon' => '🤖', 'cat' => 'AI',
                'endpoint' => '/api/ai/chatai', 'method' => 'POST',
                'payload' => ['model' => 'claude', 'session' => '{user}', 'stream' => 'false'],
                'fields' => [
                    ['teks', 'Pertanyaan untuk AI', 'textarea', true, 'Contoh: jelaskan apa itu black hole secara singkat'],
                    ['model', 'Model AI', 'select', true, '', ['claude', 'gpt', 'gemini', 'llama']],
                ],
            ],
            // ─────────────────────── 🎨 CANVAS & MAKER ───────────────────────
            'brat-anime' => [
                'name' => 'Brat Anime', 'icon' => '🌸', 'cat' => 'Canvas/Maker',
                'endpoint' => '/api/canvas/brat-anime', 'method' => 'POST', 'payload' => [],
                'fields' => [['text', 'Teks', 'text', true, 'Hello World']],
            ],
            'brat' => [
                'name' => 'Brat (teks estetik)', 'icon' => '🟩', 'cat' => 'Canvas/Maker',
                'endpoint' => '/api/canvas/brat', 'method' => 'POST',
                'payload' => ['theme' => 'white', 'blur' => '0', 'mode' => 'left'],
                'fields' => [
                    ['text', 'Teks', 'text', true, 'Hello World 🎨'],
                    ['theme', 'Tema', 'select', true, '', ['white', 'green']],
                    ['mode', 'Posisi teks', 'select', true, '', ['left', 'center', 'right']],
                    ['blur', 'Efek blur (0-10)', 'text', false, '3'],
                ],
            ],
            'createlogo' => [
                'name' => 'Buat Logo AI', 'icon' => '🎨', 'cat' => 'Canvas/Maker',
                'endpoint' => '/api/canvas/createlogo', 'method' => 'POST', 'payload' => [],
                'fields' => [
                    ['title', 'Nama brand', 'text', true, 'ChiperX'],
                    ['idea', 'Konsep logo', 'text', true, 'Logo komunitas gaming futuristik neon'],
                    ['slogan', 'Slogan', 'text', true, 'Level Up Your Game'],
                ],
            ],
            // ─────────────────────── ⬇️ DOWNLOADER ───────────────────────
            'capcut' => [
                'name' => 'CapCut Downloader', 'icon' => '✂️', 'cat' => 'Downloader',
                'endpoint' => '/api/downloader/capcut', 'method' => 'GET', 'payload' => [],
                'fields' => [['url', 'Link CapCut', 'url', true, 'https://www.capcut.com/watch/…']],
            ],
            'spotify' => [
                'name' => 'Spotify Downloader', 'icon' => '🎵', 'cat' => 'Downloader',
                'endpoint' => '/api/downloader/spotify', 'method' => 'POST', 'payload' => [],
                'fields' => [['url', 'Link lagu Spotify', 'url', true, 'https://open.spotify.com/track/…']],
            ],
            'tiktok' => [
                'name' => 'TikTok Downloader v1', 'icon' => '📱', 'cat' => 'Downloader',
                'endpoint' => '/api/downloader/tiktok', 'method' => 'POST', 'payload' => [],
                'fields' => [['url', 'Link TikTok', 'url', true, 'https://vt.tiktok.com/…']],
            ],
            'tiktokv2' => [
                'name' => 'TikTok Downloader v2', 'icon' => '📲', 'cat' => 'Downloader',
                'endpoint' => '/api/downloader/tiktokv2', 'method' => 'POST', 'payload' => [],
                'fields' => [['url', 'Link TikTok', 'url', true, 'https://vt.tiktok.com/…']],
            ],
            'youtube' => [
                'name' => 'YouTube Downloader', 'icon' => '▶️', 'cat' => 'Downloader',
                'endpoint' => '/api/downloader/youtube2', 'method' => 'POST',
                'payload' => ['quality' => '720p'],
                'fields' => [
                    ['url', 'Link YouTube', 'url', true, 'https://youtube.com/watch?v=…'],
                    ['quality', 'Kualitas', 'select', true, '', ['1080p', '720p', '480p', '360p']],
                ],
            ],
            'savefrom' => [
                'name' => 'TikTok/YT Downloader v3', 'icon' => '💾', 'cat' => 'Downloader',
                'endpoint' => '/api/downloader/savefrom', 'method' => 'POST',
                'payload' => ['type' => 'vidio'],
                'fields' => [['url', 'Link video (TikTok/YouTube)', 'url', true, 'https://…']],
            ],
            // ─────────────────────── 🖼️ HD ENHANCER ───────────────────────
            'hdvidio' => [
                'name' => 'HD-in Video (AI Upscale)', 'icon' => '📹', 'cat' => 'HD Enhancer',
                'endpoint' => '/api/hdvidio/ai-upscale-vidio', 'method' => 'GET',
                'payload' => ['resolution' => '1080p'],
                'fields' => [
                    ['url', 'Video (mp4)', 'url', true, 'https://…/video.mp4 — atau upload dari HP 👇', null, 'video'],
                    ['resolution', 'Resolusi', 'select', true, '', ['1080p', '720p']],
                ],
            ],
            'winkhd' => [
                'name' => 'Wink HD Video', 'icon' => '✨', 'cat' => 'HD Enhancer',
                'endpoint' => '/api/hdvidio/wink-hd-video', 'method' => 'POST', 'payload' => [],
                'fields' => [['url', 'Video (mp4)', 'url', true, 'https://…/video.mp4 — atau upload dari HP 👇', null, 'video']],
            ],
            'hdphoto' => [
                'name' => 'HD-in Foto (AI Enhance)', 'icon' => '🖼️', 'cat' => 'HD Enhancer',
                'endpoint' => '/api/imagehd/ai-enhance', 'method' => 'GET', 'payload' => [],
                'fields' => [['url', 'Foto yang mau di-HD-kan', 'url', true, 'https://i.pinimg.com/….jpg — atau upload dari HP 👇', null, 'image']],
            ],
            // ─────────────────────── 🎭 FA MAKER ───────────────────────
            'afinitas-ml' => [
                'name' => 'Fake Afinitas ML', 'icon' => '⚔️', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/fake-afinitas-ml', 'method' => 'POST', 'payload' => [],
                'fields' => [['ppurl', 'Foto profil', 'url', true, 'https://….jpg — atau upload dari HP 👇', null, 'image']],
            ],
            'profile-ff' => [
                'name' => 'Fake Profil FF', 'icon' => '🔫', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/fake-profile-ff', 'method' => 'POST', 'payload' => [],
                'fields' => [
                    ['nickname', 'Nickname FF', 'text', true, 'ChiperX'],
                    ['uid', 'UID FF', 'text', true, '75813269'],
                ],
            ],
            'fakecall-andro' => [
                'name' => 'Fake Call WA (Android)', 'icon' => '📞', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/fakecall-andro', 'method' => 'POST',
                'payload' => ['duration' => '00:00'],
                'fields' => [
                    ['name', 'Nama kontak', 'text', true, 'Sayangku ❤️'],
                    ['duration', 'Durasi panggilan (menit:detik)', 'text', false, '02:30'],
                    ['avatar', 'Foto avatar', 'url', false, 'https://…/avatar.jpg — atau upload dari HP 👇', null, 'image'],
                ],
            ],
            'fakecall-ios' => [
                'name' => 'Fake Call (iPhone)', 'icon' => '📱', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/fakecall-ios', 'method' => 'POST',
                'payload' => ['duration' => '00:00'],
                'fields' => [
                    ['name', 'Nama kontak', 'text', true, 'Sayangku ❤️'],
                    ['duration', 'Durasi panggilan (menit:detik)', 'text', false, '02:30'],
                    ['avatar', 'Foto avatar', 'url', false, 'https://…/avatar.jpg — atau upload dari HP 👇', null, 'image'],
                ],
            ],
            'fakeig' => [
                'name' => 'Fake Postingan IG', 'icon' => '📸', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/fakeig', 'method' => 'GET', 'payload' => [],
                'fields' => [['pp', 'Foto profil', 'url', true, 'https://….jpg — atau upload dari HP 👇', null, 'image']],
            ],
            'fakenotifwa' => [
                'name' => 'Fake Notif WA', 'icon' => '💬', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/fakenotifwa', 'method' => 'POST', 'payload' => [],
                'fields' => [
                    ['username', 'Nama pengirim', 'text', true, 'ChiperX'],
                    ['chat', 'Isi pesan', 'text', true, 'Hai! Ada kode redeem baru 🎁'],
                    ['ppurl', 'Foto profil', 'url', true, 'https://….jpg — atau upload dari HP 👇', null, 'image'],
                    ['tanggal', 'Tanggal', 'text', true, 'Rabu, 12 Agustus'],
                    ['jam', 'Jam', 'text', true, '21.00'],
                ],
            ],
            'iqc-dark' => [
                'name' => 'Quotes iPhone Dark', 'icon' => '🌙', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/iqc-dark', 'method' => 'GET',
                'payload' => ['time' => '21.00'],
                'fields' => [
                    ['text', 'Teks quotes', 'textarea', true, 'Tetap rendah hati 🌙'],
                    ['time', 'Jam di status bar', 'text', false, '21.00'],
                    ['image', 'Foto latar', 'url', false, 'https://….jpg — atau upload dari HP 👇', null, 'image'],
                ],
            ],
            'iqc-pink' => [
                'name' => 'Quotes iPhone Pink', 'icon' => '🌷', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/iqc-pink', 'method' => 'POST',
                'payload' => ['time' => '22.54'],
                'fields' => [
                    ['text', 'Teks quotes', 'textarea', true, 'Kesendirian adalah teman terbaik 😌'],
                    ['time', 'Jam di status bar', 'text', false, '22.54'],
                ],
            ],
            'iqc' => [
                'name' => 'Quotes iPhone Normal', 'icon' => '🤍', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/iqc', 'method' => 'POST', 'payload' => [],
                'fields' => [
                    ['text', 'Teks quotes', 'textarea', true, 'Fokus, disiplin, konsisten.'],
                    ['author', 'Nama author', 'text', true, 'ChiperX'],
                ],
            ],
            'saldo-dana' => [
                'name' => 'Fake Saldo DANA', 'icon' => '💰', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/saldo-dana', 'method' => 'GET', 'payload' => [],
                'fields' => [],
            ],
            'sertif-nasa' => [
                'name' => 'Sertifikat NASA', 'icon' => '📜', 'cat' => 'Fake Maker',
                'endpoint' => '/api/maker/sertifikat-nasa', 'method' => 'POST', 'payload' => [],
                'fields' => [['nama', 'Nama di sertifikat', 'text', true, 'Valentino']],
            ],
            // ─────────────────────── 🕵️ STALKER ───────────────────────
            'stalk-ff' => [
                'name' => 'Stalk Akun FF', 'icon' => '🎯', 'cat' => 'Stalker',
                'endpoint' => '/api/stalker/freefire', 'method' => 'POST', 'payload' => [],
                'fields' => [['user_id', 'UID Free Fire', 'text', true, '1026476683']],
            ],
            'stalk-ig' => [
                'name' => 'Stalk Instagram', 'icon' => '🔍', 'cat' => 'Stalker',
                'endpoint' => '/api/stalker/instagram', 'method' => 'POST',
                'payload' => ['action' => 'stalk', 'pages' => '2'],
                'fields' => [['query', 'Username IG (tanpa @)', 'text', true, 'chiperx']],
            ],
            // ─────────────────────── 🛠️ TOOLS ───────────────────────
            'tempmail' => [
                'name' => 'Temp Mail Sementara', 'icon' => '📮', 'cat' => 'Tools',
                'endpoint' => '/api/tempmail/1timetech', 'method' => 'POST',
                'payload' => ['action' => 'create'],
                'fields' => [['email', 'Email kustom (opsional)', 'text', false, 'nama@voewo.com']],
            ],
            'catbox' => [
                'name' => 'Catbox Upload (file → link)', 'icon' => '📦', 'cat' => 'Tools',
                'endpoint' => '/api/tools/catbox', 'method' => 'UPLOAD', 'payload' => [],
                'fields' => [['file', 'Pilih file (gambar/video/dll.)', 'file', true, '']],
            ],
            'encrypt' => [
                'name' => 'Encrypt / Decrypt', 'icon' => '🔐', 'cat' => 'Tools',
                'endpoint' => '/api/tools/encrypt', 'method' => 'POST',
                'payload' => ['method' => 'base64'],
                'fields' => [
                    ['text', 'Teks', 'textarea', true, 'Rahasia ChiperX 🤫'],
                    ['mode', 'Mode', 'select', true, '', ['encrypt', 'decrypt']],
                    ['method', 'Metode', 'select', true, '', ['base64', 'hex', 'url']],
                ],
            ],
            'scanweb' => [
                'name' => 'Scan Keamanan Web', 'icon' => '🛰️', 'cat' => 'Tools',
                'endpoint' => '/api/tools/scanweb', 'method' => 'GET', 'payload' => [],
                'fields' => [['url', 'Situs target (opsional)', 'url', false, 'https://chiperx.cyou']],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::tools()[$key] ?? null;
    }

    /**
     * Bangun payload dari input form sesuai registry tool.
     * @return array{0: array<int,array>, 1: array<string,string>, 2: ?array}
     *         [fields, payload, fileArray('field','path','name','mime')]
     */
    public static function buildPayload(array $tool, callable $input): array
    {
        $payload = [];
        foreach (($tool['payload'] ?? []) as $k => $v) {
            $payload[$k] = $v; // nilai bawaan
        }
        foreach ($tool['fields'] as $f) {
            [$name,, $type, $required] = $f;
            if ($type === 'file') {
                continue; // file ditangani terpisah
            }
            $val = trim((string) $input($name));
            if ($val === '') {
                if ($required) {
                    throw new \RuntimeException('Kolom "' . $f[1] . '" wajib diisi.');
                }
                continue; // opsional kosong → jangan dikirim
            }
            $payload[$name] = $val;
        }
        // Placeholder {user} → identitas pemakai (session AI personal)
        $me = auth_user();
        foreach ($payload as $k => $v) {
            if ($v === '{user}') {
                $payload[$k] = 'chiperx-' . (int) ($me['id'] ?? 0);
            }
        }
        return $payload;
    }

    /**
     * Panggil endpoint Codex.
     * @return array{ok:bool, status:int, data:?array, error:?string}
     */
    public static function call(string $endpoint, array $payload, string $method = 'POST', int $timeout = 30): array
    {
        $url = self::BASE . $endpoint;
        $ch  = curl_init();
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $key = (string) Config::secret('CODEX_API_KEY', '');
        if (trim($key) !== '') {
            $headers[] = 'Authorization: Bearer ' . trim($key);
        }

        if ($method === 'GET') {
            if ($payload !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
            }
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } elseif ($method === 'UPLOAD') {
            // multipart dengan file lokal (key 'file' berisi path sementara)
            $post = [];
            foreach ($payload as $k => $v) {
                if (is_array($v) && isset($v['tmp'])) {
                    $post[$k] = new \CURLFile($v['tmp'], $v['mime'] ?? 'application/octet-stream', $v['name'] ?? 'file');
                } else {
                    $post[$k] = $v;
                }
            }
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $headers = ['Accept: application/json']; // biarkan curl set multipart boundary
            if (trim($key) !== '') {
                $headers[] = 'Authorization: Bearer ' . trim($key);
            }
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'ChiperX-Tools/2.4 (+ChiPerX.CYOU)',
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        unset($ch); // PHP 8.5: curl_close() deprecated

        if ($body === false || $body === '') {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $err !== '' ? $err : 'Respons kosong (timeout/server sibuk)'];
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            // Mungkin respons BUKAN JSON (mis. langsung gambar/biner)
            return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => ['_raw' => base64_encode(substr((string) $body, 0, 3_000_000)), '_binary' => true], 'error' => null];
        }
        $apiErr = ($data['status'] ?? true) === false ? ($data['message'] ?? $data['msg'] ?? 'API menolak permintaan') : null;
        return ['ok' => $status >= 200 && $status < 300 && $apiErr === null, 'status' => $status, 'data' => $data, 'error' => $apiErr];
    }

    /** Tes hidup/mati satu tool (dipakai Owner > Cek API). */
    public static function test(string $key): array
    {
        $tool = self::get($key);
        if (!$tool) {
            return ['ok' => false, 'ms' => 0, 'note' => 'tool tak dikenal'];
        }
        // payload sampel ramah (isi minimal agar tidak berat)
        $samples = [
            'chatai' => ['teks' => 'hai', 'model' => 'claude', 'session' => 'health', 'stream' => 'false'],
            'brat-anime' => ['text' => 'Hi'], 'brat' => ['text' => 'Hi', 'theme' => 'white', 'blur' => '0', 'mode' => 'left'],
            'createlogo' => ['title' => 'CX', 'idea' => 'tech', 'slogan' => 'go'],
            'capcut' => ['url' => 'https://www.capcut.com/'], 'spotify' => ['url' => 'https://open.spotify.com/track/3y8RcMPYG22fRnrOi4oFJ1'],
            'tiktok' => ['url' => 'https://vt.tiktok.com/ZSXV9mB48/'], 'tiktokv2' => ['url' => 'https://vt.tiktok.com/ZSXV9mB48/'],
            'youtube' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'quality' => '720p'], 'savefrom' => ['url' => 'https://vt.tiktok.com/ZSXV9mB48/', 'type' => 'vidio'],
            'hdvidio' => ['url' => 'https://example.com/v.mp4', 'resolution' => '1080p'], 'winkhd' => ['url' => 'https://example.com/v.mp4'],
            'hdphoto' => ['url' => 'https://i.pinimg.com/736x/21/3a/90/213a900af021a47d7caad219318d200c.jpg'],
            'afinitas-ml' => ['ppurl' => 'https://i.pinimg.com/736x/21/3a/90/213a900af021a47d7caad219318d200c.jpg'],
            'profile-ff' => ['nickname' => 'CX', 'uid' => '75813269'],
            'fakecall-andro' => ['name' => 'CX', 'duration' => '00:00', 'avatar' => 'https://example.com/a.jpg'],
            'fakecall-ios' => ['name' => 'CX', 'duration' => '00:00', 'avatar' => 'https://example.com/a.jpg'],
            'fakeig' => ['pp' => 'https://i.pinimg.com/736x/21/3a/90/213a900af021a47d7caad219318d200c.jpg'],
            'fakenotifwa' => ['username' => 'CX', 'chat' => 'hai', 'ppurl' => 'https://example.com/a.jpg', 'tanggal' => 'Senin', 'jam' => '6.39'],
            'iqc-dark' => ['text' => 'hai', 'time' => '12.00', 'image' => 'https://example.com/a.jpg'],
            'iqc-pink' => ['text' => 'hai', 'time' => '22.54'], 'iqc' => ['text' => 'hai', 'author' => 'CX'],
            'saldo-dana' => [], 'sertif-nasa' => ['nama' => 'CX'],
            'stalk-ff' => ['user_id' => '1026476683'], 'stalk-ig' => ['action' => 'stalk', 'query' => 'kopi', 'pages' => '2'],
            'tempmail' => ['action' => 'create', 'email' => 'cx@voewo.com'],
            'catbox' => null, // upload — lewati health check (butuh file)
            'encrypt' => ['mode' => 'encrypt', 'method' => 'base64', 'text' => 'hai'],
            'scanweb' => ['url' => 'https://chiperx.cyou'],
        ];
        if (!array_key_exists($key, $samples) || $samples[$key] === null) {
            return ['ok' => true, 'ms' => 0, 'note' => 'dilewati (butuh upload file)'];
        }
        $method = $tool['method'] === 'UPLOAD' ? 'POST' : $tool['method'];
        $t0 = microtime(true);
        try {
            $res = self::call($tool['endpoint'], $samples[$key], $method, 10);
        } catch (\Throwable $e) {
            return ['ok' => false, 'ms' => 0, 'note' => mb_substr($e->getMessage(), 0, 80)];
        }
        $ms = (int) ((microtime(true) - $t0) * 1000);
        return ['ok' => $res['ok'], 'ms' => $ms, 'note' => $res['ok'] ? 'HTTP ' . $res['status'] : ($res['error'] ?? 'HTTP ' . $res['status'])];
    }
}
