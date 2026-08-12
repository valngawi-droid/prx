<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Message;
use ChiperX\Models\Notification;
use ChiperX\Models\User;
use ChiperX\Services\UploadService;

/**
 * ✉️ Pesan Pribadi (DM) gaya WhatsApp + Telegram:
 *  ✓✓ read receipts, typing indicator, foto dari HP, reply kutipan,
 *  hapus-untuk-semua, pin pesan, blokir user, forward, pesan self-destruct,
 *  saved messages (chat ke diri sendiri), ekspor riwayat .txt
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
            'title'   => 'Pesan',
            'convs'   => $convs,
            'blocked' => Message::blockedByMe((int) $user['id']),
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
        $isSelf = (int) $target['id'] === (int) $user['id']; // ⭐ Saved Messages ala Telegram
        Message::markRead((int) $user['id'], (int) $target['id']);
        return $this->panel('messages/thread', [
            'title'   => $isSelf ? '⭐ Pesan Tersimpan' : 'Chat — ' . $target['name'],
            'target'  => $target,
            'isSelf'  => $isSelf,
            'pinned'  => Message::pinned((int) $user['id'], (int) $target['id']),
            'blocked' => Message::isBlocked((int) $target['id'], (int) $user['id']),   // aku diblokir dia
            'iBlocked' => (bool) array_filter(Message::blockedByMe((int) $user['id']), static fn (array $b): bool => (int) $b['blocked_id'] === (int) $target['id']),
        ]);
    }

    public function send(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if (!$target) {
            flash('error', 'Tujuan pesan tidak valid.');
            return redirect('/pesan');
        }
        $selfChat = (int) $target['id'] === (int) $user['id'];
        $back = '/pesan/' . rawurlencode((string) $target['username']);
        if (($target['status'] ?? 'active') !== 'active' && !$selfChat) {
            flash('error', 'Akun tujuan sedang nonaktif.');
            return redirect($back);
        }
        // 📇 Blokir gaya WhatsApp
        if (!$selfChat && Message::isBlocked((int) $user['id'], (int) $target['id'])) {
            flash('error', 'Pesan tidak terkirim — kamu diblokir pengguna ini. 🚫');
            return redirect('/pesan');
        }

        // 🖼️ Lampiran foto dari HP (bucket dm)
        $image = null;
        if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $image = UploadService::saveImage($_FILES['image'], 'dm', 4);
            } catch (\RuntimeException $e) {
                flash('error', 'Foto: ' . $e->getMessage());
                return redirect($back);
            }
        }

        $replyTo = (int) $req->str('reply_to', '0', 10);
        $replyTo = $replyTo > 0 ? $replyTo : null;
        $ttl = $req->int('ttl', 0); // ⏳ self-destruct: 0/3600/86400
        $ttl = in_array($ttl, [3600, 86400, 604800], true) ? $ttl : null;

        $body = trim($req->str('body', '', 500));
        if ($body === '' && $image === null) {
            return redirect($back);
        }

        // Throttle lembut: 1 pesan / 2 detik (saved messages bebas)
        $last = Message::lastSentAt((int) $user['id'], (int) $target['id']);
        if (!$selfChat && $last !== null && (time() - strtotime($last)) < 2) {
            flash('warning', 'Sabar — pesanmu terkirim, tunggu sejenak. ⏱️');
            return redirect($back);
        }

        Message::send((int) $user['id'], (int) $target['id'], $body !== '' ? $body : '📷', $image, $replyTo, $ttl);

        // Notif lonceng hanya jika pesan terakhir > 30 menit (anti-spam)
        if (!$selfChat && ($last === null || (time() - strtotime($last)) > 1800)) {
            Notification::add(
                (int) $target['id'],
                '✉️ Pesan baru dari ' . $user['name'],
                $image !== null ? '📷 Foto' : mb_substr($body, 0, 80),
                '/pesan/' . rawurlencode((string) $user['username'])
            );
        }
        return redirect($back);
    }

    /** POST /pesan/{username}/typing — flag "sedang mengetik…" (dipanggil JS saat input). */
    public function typing(Request $req, array $params): Response
    {
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if ($target) {
            Message::setTyping((int) $user['id'], (int) $target['id']);
        }
        return $this->json(['ok' => true]);
    }

    /** POST /pesan/msg/{id}/delete — hapus untuk semua orang (hanya pengirim). */
    public function deleteMsg(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $msg  = \ChiperX\Core\Database::one('SELECT sender_id, recipient_id FROM messages WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        if ($msg && (int) $msg['sender_id'] === (int) $user['id']) {
            Message::deleteForAll((int) $params['id'], (int) $user['id']);
            flash('success', 'Pesan dihapus untuk semua orang. 🗑️');
            $peer = (int) $msg['recipient_id'] === (int) $user['id'] ? (int) $msg['sender_id'] : (int) $msg['recipient_id'];
            $u = User::find($peer);
            return redirect('/pesan/' . rawurlencode((string) ($u['username'] ?? '')));
        }
        flash('error', 'Hanya pesan milikmu yang bisa dihapus.');
        return redirect('/pesan');
    }

    /** POST /pesan/{username}/pin/{id} — pin/lepas pin pesan di percakapan. */
    public function pinMsg(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if ($target) {
            Message::togglePin((int) ($params['id'] ?? 0), (int) $user['id'], (int) $target['id']);
        }
        return redirect('/pesan/' . rawurlencode((string) ($target['username'] ?? '')));
    }

    /** POST /pesan/{username}/blokir — blokir / buka blokir (WhatsApp). */
    public function toggleBlock(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if ($target && (int) $target['id'] !== (int) $user['id']) {
            $mine = Message::blockedByMe((int) $user['id']);
            $has = (bool) array_filter($mine, static fn (array $b): bool => (int) $b['blocked_id'] === (int) $target['id']);
            Message::setBlocked((int) $user['id'], (int) $target['id'], !$has);
            flash('success', !$has ? '🚫 ' . $target['name'] . ' diblokir — dia tak bisa mengirimu pesan.' : '✅ Blokir ' . $target['name'] . ' dibuka.');
        }
        return redirect('/pesan/' . rawurlencode((string) ($target['username'] ?? '')));
    }

    /** POST /pesan/{username}/teruskan — forward kutipan ke chat lain (Telegram). */
    public function forward(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $ke   = User::findByUsername((string) $req->str('to', '', 30));
        $body = trim($req->str('body', '', 450));
        if ($ke && $body !== '' && (int) $ke['id'] !== (int) $user['id']) {
            if (!Message::isBlocked((int) $user['id'], (int) $ke['id'])) {
                Message::send((int) $user['id'], (int) $ke['id'], '↪️ Diteruskan:\n' . $body);
                Notification::add((int) $ke['id'], '↪️ Pesan diteruskan dari ' . $user['name'], mb_substr($body, 0, 80), '/pesan/' . rawurlencode((string) $user['username']));
                flash('success', 'Pesan diteruskan ke ' . $ke['name'] . ' ↪️');
                return redirect('/pesan/' . rawurlencode((string) $ke['username']));
            }
            flash('error', 'Tidak bisa — kamu diblokir pengguna itu.');
        }
        return redirect('/pesan');
    }

    /** POST /pesan/baca-semua — tandai semua percakapan dibaca. */
    public function markAll(Request $req): Response
    {
        $this->guardCsrf();
        Message::markAllRead((int) auth_user()['id']);
        flash('success', 'Semua pesan ditandai dibaca. ✅');
        return redirect('/pesan');
    }

    /** GET /pesan/{username}/ekspor — unduh riwayat chat sebagai .txt (Telegram export). */
    public function export(Request $req, array $params): Response
    {
        $user   = auth_user();
        $target = User::findByUsername((string) ($params['username'] ?? ''));
        if (!$target) {
            return redirect('/pesan');
        }
        $rows = Message::thread((int) $user['id'], (int) $target['id'], 500);
        $out  = "Riwayat Chat ChiperX — {$user['name']} ↔ {$target['name']}\nDiekspor: " . date('d M Y H:i') . " WIB\n" . str_repeat('─', 42) . "\n\n";
        foreach ($rows as $m) {
            if ($m['deleted_at'] !== null) {
                $isi = '[pesan dihapus]';
            } else {
                $isi = trim((string) $m['body']) . ($m['image'] ? ' [foto]' : '');
            }
            $siapa = (int) $m['sender_id'] === (int) $user['id'] ? $user['name'] : $target['name'];
            $out .= '[' . date('d/m H:i', strtotime((string) $m['created_at'])) . '] ' . $siapa . ': ' . $isi . "\n";
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="chat-' . preg_replace('/[^a-z0-9_.-]/i', '', (string) $target['username']) . '.txt"');
        echo $out;
        exit;
    }

    /** JSON pesan-pesan thread (polling) + flag typing lawan. */
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
            $deleted = $m['deleted_at'] !== null;
            return [
                'id'      => (int) $m['id'],
                'mine'    => (int) $m['sender_id'] === $myId,
                'html'    => $deleted ? '' : render_chat((string) $m['body']), // sudah di-escape + diformat di server (aman innerHTML)
                'plain'   => $deleted ? '' : mb_substr((string) $m['body'], 0, 400),
                'image'   => $deleted ? null : $m['image'],
                'deleted' => $deleted,
                'pinned'  => $m['pinned_at'] !== null,
                'ttl'     => $m['expire_at'] !== null ? max(0, strtotime((string) $m['expire_at']) - time()) : null,
                'reply'   => $m['reply_to'] ? [
                    'name' => (string) ($m['reply_name'] ?? ''),
                    'body' => $m['reply_deleted'] ? '🚫 dihapus' : mb_substr((string) ($m['reply_body'] ?? ''), 0, 80) . ($m['reply_image'] ? ' 📷' : ''),
                ] : null,
                'time'    => date('H:i', strtotime((string) $m['created_at'])),
                'read'    => $m['read_at'] !== null,
            ];
        }, Message::thread($myId, (int) $target['id'], 80));
        return $this->json([
            'ok'     => true,
            'items'  => $items,
            'typing' => Message::isTyping((int) $target['id'], $myId), // lawan sedang mengetik ke aku?
        ]);
    }
}
