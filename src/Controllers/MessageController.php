<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Message;
use ChiperX\Models\Notification;
use ChiperX\Models\User;

/**
 * Pesan Pribadi (DM) ala WhatsApp/Telegram:
 *  - GET  /pesan                    → kotak masuk (daftar percakapan)
 *  - GET  /pesan/{username}         → ruang chat
 *  - POST /pesan/{username}         → kirim pesan
 *  - GET  /pesan/{username}/json    → feed JSON untuk polling live
 */
final class MessageController extends Controller
{
    public function index(Request $req): string
    {
        $user = auth_user();
        $convs = [];
        try {
            $convs = Message::conversations((int) $user['id']);
        } catch (\Throwable) {
            $convs = [];
        }
        return $this->panel('messages/index', [
            'title' => 'Pesan',
            'convs' => $convs,
        ]);
    }

    public function thread(Request $req, array $params): Response|string
    {
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if (!$target) {
            flash('error', 'Pengguna tidak ditemukan.');
            return redirect('/pesan');
        }
        if ((int) $target['id'] === (int) $user['id']) {
            flash('warning', 'Itu akunmu sendiri 😄 — pilih member lain untuk diajak chat.');
            return redirect('/pesan');
        }
        Message::markRead((int) $user['id'], (int) $target['id']);
        return $this->panel('messages/thread', [
            'title'  => 'Chat — ' . $target['name'],
            'target' => $target,
        ]);
    }

    public function send(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if (!$target || (int) $target['id'] === (int) $user['id']) {
            flash('error', 'Tujuan pesan tidak valid.');
            return redirect('/pesan');
        }
        if (($target['status'] ?? 'active') !== 'active') {
            flash('error', 'Akun tujuan sedang nonaktif.');
            return redirect('/pesan/' . rawurlencode((string) $target['username']));
        }
        $body = trim($req->str('body', '', 500));
        if ($body === '') {
            return redirect('/pesan/' . rawurlencode((string) $target['username']));
        }

        // Throttle lembut: 1 pesan / 2 detik
        $last = Message::lastSentAt((int) $user['id'], (int) $target['id']);
        if ($last !== null && (time() - strtotime($last)) < 2) {
            flash('warning', 'Sabar — pesanmu terkirim, tunggu sejenak. ⏱️');
            return redirect('/pesan/' . rawurlencode((string) $target['username']));
        }

        Message::send((int) $user['id'], (int) $target['id'], $body);

        // Notif lonceng hanya jika pesan terakhir sebelumnya > 30 menit lalu (anti-spam)
        if ($last === null || (time() - strtotime($last)) > 1800) {
            Notification::add(
                (int) $target['id'],
                '✉️ Pesan baru dari ' . $user['name'],
                mb_substr($body, 0, 80),
                '/pesan/' . rawurlencode((string) $user['username'])
            );
        }
        return redirect('/pesan/' . rawurlencode((string) $target['username']));
    }

    /** JSON pesan-pesan thread (polling 8 detik dari view). */
    public function poll(Request $req, array $params): Response
    {
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if (!$target) {
            return $this->json(['ok' => false, 'items' => []], 404);
        }
        Message::markRead((int) $user['id'], (int) $target['id']);
        $myId = (int) $user['id'];
        $items = array_map(static function (array $m) use ($myId): array {
            return [
                'id'   => (int) $m['id'],
                'mine' => (int) $m['sender_id'] === $myId,
                'body' => (string) $m['body'],
                'time' => date('H:i', strtotime((string) $m['created_at'])),
                'read' => $m['read_at'] !== null,
            ];
        }, Message::thread((int) $user['id'], (int) $target['id'], 80));
        return $this->json(['ok' => true, 'items' => $items]);
    }
}
