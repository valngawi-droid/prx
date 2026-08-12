<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Services\UploadService;

/**
 * GET /media/{bucket}/{file} — sajikan file unggahan dari storage/uploads.
 * Whitelist ketat: bucket sah + nama 24-hex + ekstensi gambar/video.
 * TIDAK ADA eksekusi skrip dari folder upload (lihat UploadService::dir).
 */
final class MediaController
{
    private const MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
    ];

    public function serve(Request $req, array $params): Response
    {
        $bucket = (string) ($params['bucket'] ?? '');
        $name   = basename((string) ($params['file'] ?? ''));

        if (!in_array($bucket, UploadService::BUCKETS, true)
            || !preg_match('/^[a-f0-9]{24}\.(jpg|jpeg|png|webp|gif|mp4|webm)$/', $name)) {
            return Response::html('Not found', 404);
        }
        // Video hanya disajikan dari bucket sementara (perantara Tools API)
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, ['mp4', 'webm'], true) && $bucket !== 'tmp') {
            return Response::html('Not found', 404);
        }

        $path = BASE_PATH . '/storage/uploads/' . $bucket . '/' . $name;
        if (!is_file($path)) {
            return Response::html('Not found', 404);
        }

        header('Content-Type: ' . self::MIME[$ext]);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . ($bucket === 'tmp' ? 'public, max-age=300' : 'public, max-age=604800'));
        readfile($path);
        exit;
    }
}
