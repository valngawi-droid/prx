<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Core\Session;
use ChiperX\Models\Transaction;
use ChiperX\Services\AuditLogger;

/**
 * Pengunduhan file produk — hanya untuk transaksi PAID milik user.
 * Dua mode: URL eksternal (direveal lewat halaman aman) atau
 * file internal di storage/uploads (distream dengan header aman).
 */
final class DownloadController extends Controller
{
    public function show(Request $req, array $params): Response|string
    {
        $user = auth_user();
        $tx   = Transaction::ownedPaid((int) $params['id'], (int) $user['id']);
        if (!$tx) {
            flash('error', 'Akses ditolak: transaksi tidak ditemukan atau belum lunas.');
            return redirect('/dashboard');
        }

        $source = (string) $tx['file_url'];
        AuditLogger::record('download.access', ['tx' => $tx['id'], 'product' => $tx['product_name']], 'info', (int) $user['id'], $req->ip());

        // Mode 1: URL eksternal → tampilkan halaman reveal (cegah bocor lewat view-source scraping)
        if (preg_match('#^https?://#i', $source)) {
            return $this->view('store/download', [
                'title'   => 'Akses File Anda',
                'tx'      => $tx,
                'fileUrl' => $source,
            ]);
        }

        // Mode 2: file lokal → stream langsung
        $abs = realpath(BASE_PATH . '/storage/uploads/' . ltrim($source, '/'));
        $base = realpath(BASE_PATH . '/storage/uploads');
        if ($abs === false || $base === false || !str_starts_with($abs, $base) || !is_file($abs)) {
            flash('error', 'File belum tersedia di server. Hubungi admin.');
            return redirect('/dashboard');
        }
        $this->streamFile($abs, basename($abs));
        return Response::html('');
    }

    public function streamFile(string $abs, string $name): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . filesize($abs));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($abs);
        exit;
    }
}
