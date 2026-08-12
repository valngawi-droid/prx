<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Services\AuditLogger;
use ChiperX\Services\CodexApi;

/**
 * 🚀 Tools 30+ AI & Downloader (member):
 *  - GET  /tools           → katalog semua alat
 *  - GET  /tools/{key}     → form alat
 *  - POST /tools/{key}     → jalankan alat, tampilkan hasil
 */
final class ToolsController extends Controller
{
    public function index(Request $req): string
    {
        return $this->panel('tools/index', [
            'title' => 'Tools AI & Downloader',
            'tools' => CodexApi::tools(),
        ]);
    }

    public function show(Request $req, array $params): Response|string
    {
        $tool = CodexApi::get((string) ($params['key'] ?? ''));
        if (!$tool) {
            flash('error', 'Alat tidak ditemukan.');
            return redirect('/tools');
        }
        return $this->panel('tools/tool', [
            'title'  => $tool['name'],
            'key'    => (string) $params['key'],
            'tool'   => $tool,
            'res'    => null,
            'media'  => [],
            'teks'   => null,
        ]);
    }

    public function run(Request $req, array $params): Response|string
    {
        $this->guardCsrf();
        $key  = (string) ($params['key'] ?? '');
        $tool = CodexApi::get($key);
        if (!$tool) {
            flash('error', 'Alat tidak ditemukan.');
            return redirect('/tools');
        }

        try {
            // 📱 Upload dari HP: kolom berspesifikasi 'up' (index ke-6) bisa diisi
            // file — kita host sementara (bucket tmp, auto-prune 2 jam) lalu URL
            // publiknyalah yang dikirim ke API Codex.
            $uploaded = [];
            foreach ($tool['fields'] as $f) {
                $up = $f[6] ?? null;
                if ($up === null) {
                    continue;
                }
                $fkey = (string) $f[0];
                $file = $_FILES[$fkey . '_file'] ?? null;
                if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $name = $up === 'video'
                        ? \ChiperX\Services\UploadService::saveMedia($file, 'tmp', 30)
                        : \ChiperX\Services\UploadService::saveImage($file, 'tmp', 6);
                    $uploaded[$fkey] = \ChiperX\Services\UploadService::publicUrl('tmp', $name);
                }
            }

            $payload = CodexApi::buildPayload($tool, fn(string $k): string => $uploaded[$k] ?? $req->str($k, '', 2000));

            // Upload file (Catbox) — batasi 25MB, simpan sementara
            if (($tool['method'] ?? 'POST') === 'UPLOAD') {
                $f = $_FILES['file'] ?? null;
                if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('Pilih file dulu ya (maks 25MB).');
                }
                if ($f['size'] > 25 * 1024 * 1024) {
                    throw new \RuntimeException('File melebihi 25MB.');
                }
                $mime = mime_content_type((string) $f['tmp_name']) ?: 'application/octet-stream';
                $payload['file'] = ['tmp' => (string) $f['tmp_name'], 'mime' => $mime, 'name' => basename((string) $f['name'])];
            }

            $res = CodexApi::call($tool['endpoint'], $payload, (string) $tool['method']);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return redirect('/tools/' . rawurlencode($key));
        } catch (\Throwable) {
            flash('error', 'API sedang sibuk / tidak terjangkau. Coba beberapa saat lagi. 🙏');
            return redirect('/tools/' . rawurlencode($key));
        }

        if (!$res['ok']) {
            flash('error', 'API gagal: ' . ($res['error'] ?? 'HTTP ' . $res['status']) . ' 😥');
            return redirect('/tools/' . rawurlencode($key));
        }

        AuditLogger::record('tools.run', ['tool' => $key], 'info', (int) auth_user()['id'], $req->ip());

        [$media, $teks] = $this->extractResult($res['data']);
        return $this->panel('tools/tool', [
            'title'  => $tool['name'],
            'key'    => $key,
            'tool'   => $tool,
            'res'    => $res['data'],
            'media'  => $media,
            'teks'   => $teks,
        ]);
    }

    /**
     * Telusuri JSON hasil → pisahkan URL media (gambar/video/audio) & teks jawaban.
     * @return array{0: array<int, array{type:string,url:string}>, 1: ?string}
     */
    private function extractResult(?array $data): array
    {
        $media = [];
        $teks  = null;
        if ($data === null) {
            return [$media, $teks];
        }
        // Respons biner mentah (gambar langsung dari API)
        if (!empty($data['_binary']) && !empty($data['_raw'])) {
            $media[] = ['type' => 'image', 'url' => 'data:image/png;base64,' . $data['_raw']];
            return [$media, null];
        }
        $walker = static function ($node) use (&$walker, &$media, &$teks): void {
            if (is_array($node)) {
                foreach ($node as $v) {
                    $walker($v);
                }
                return;
            }
            if (!is_string($node) || $node === '') {
                return;
            }
            $s = trim($node);
            if (preg_match('~^https?://\S+~i', $s)) {
                if (preg_match('~\.(png|jpe?g|webp|gif)(\?\S*)?$~i', $s)) {
                    $media[] = ['type' => 'image', 'url' => $s];
                } elseif (preg_match('~\.(mp4|webm|mov)(\?\S*)?$~i', $s)) {
                    $media[] = ['type' => 'video', 'url' => $s];
                } elseif (preg_match('~\.(mp3|ogg|m4a|wav)(\?\S*)?$~i', $s)) {
                    $media[] = ['type' => 'audio', 'url' => $s];
                } elseif (strlen($s) < 600) {
                    $media[] = ['type' => 'link', 'url' => $s]; // tautan unduhan umum
                }
                return;
            }
            // kandidat teks jawaban (ambil yang terpanjang bermakna)
            if (strlen($s) > 20 && ($teks === null || strlen($s) > strlen($teks)) && strlen($s) < 8000) {
                $teks = $s;
            }
        };
        $walker($data);
        // unik & batasi
        $seen = [];
        $media = array_values(array_filter($media, static function ($m) use (&$seen): bool {
            if (isset($seen[$m['url']])) {
                return false;
            }
            return $seen[$m['url']] = true;
        }));
        return [array_slice($media, 0, 6), $teks];
    }
}
