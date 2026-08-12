<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Channel;
use ChiperX\Models\Notification;
use ChiperX\Services\AuditLogger;

/**
 * 📢 CHANNELS gaya Discord — /kanal
 * Ruang chat publik: slowmode, pin, reaksi, reply, poll, AutoMod kata terlarang.
 */
final class ChannelController extends Controller
{
    /** GET /kanal — daftar semua channel + siapa yang online. */
    public function index(Request $req): string
    {
        $me = auth_user();
        return $this->view('channels/index', [
            'title'    => 'Kanal',
            'channels' => Channel::all(),
            'online'   => Channel::onlineNow(14),
            'me'       => $me,
        ]);
    }

    /** GET /kanal/{slug} — ruang chat channel. */
    public function show(Request $req, array $params): Response|string
    {
        $ch = Channel::findBySlug((string) ($params['slug'] ?? ''));
        if (!$ch) {
            flash('error', 'Kanal tidak ditemukan.');
            return redirect('/kanal');
        }
        $me = auth_user();
        $cid = (int) $ch['id'];
        return $this->view('channels/show', [
            'title'    => $ch['name'],
            'ch'       => $ch,
            'messages' => Channel::messages($cid, 60, (int) ($me['id'] ?? 0)),
            'pinned'   => Channel::pinnedMessage($cid),
            'me'       => $me,
            'canPost'  => $me !== null && ($ch['kind'] !== 'announce' || in_array($me['role'], ['admin', 'owner'], true)),
            'canMod'   => $me !== null && in_array($me['role'], ['admin', 'owner'], true),
            'emojis'   => Channel::EMOJIS,
        ]);
    }

    /** POST /kanal/{slug}/post — kirim pesan (text/poll) + reply + slowmode + AutoMod. */
    public function post(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        $ch = Channel::findBySlug((string) ($params['slug'] ?? ''));
        if (!$ch) {
            flash('error', 'Kanal tidak ditemukan.');
            return redirect('/kanal');
        }
        $slug = (string) $ch['slug'];
        if ($ch['kind'] === 'announce' && !in_array($me['role'], ['admin', 'owner'], true)) {
            flash('error', 'Kanal ini khusus pengumuman — hanya admin/owner yang bisa memosting. 📢');
            return redirect('/kanal/' . $slug);
        }
        $cid = (int) $ch['id'];
        // 🐌 Slowmode
        $slow = (int) ($ch['slowmode'] ?? 0);
        if ($slow > 0 && !in_array($me['role'], ['admin', 'owner'], true)) {
            $last = Channel::lastMessageAt($cid, (int) $me['id']);
            if ($last !== null && (time() - strtotime($last)) < $slow) {
                flash('warning', "Slowmode aktif di kanal ini — 1 pesan tiap {$slow} detik. 🐌");
                return redirect('/kanal/' . $slug . '#kirim');
            }
        }

        $replyTo = (int) $req->str('reply_to', '0', 10);
        if ($replyTo <= 0) {
            $replyTo = null;
        }

        // 📊 MODE POLL: pertanyaan + 2-4 opsi
        if ($req->str('mode', 'text', 10) === 'poll') {
            $q  = trim((string) $req->str('question', '', 160));
            $ops = [];
            foreach (['opt1', 'opt2', 'opt3', 'opt4'] as $ok) {
                $o = trim((string) $req->str($ok, '', 60));
                if ($o !== '') {
                    $ops[] = mb_substr($o, 0, 60);
                }
            }
            if ($q === '' || count($ops) < 2) {
                flash('error', 'Polling butuh pertanyaan + minimal 2 opsi. 📊');
                return redirect('/kanal/' . $slug . '#kirim');
            }
            Channel::post($cid, (int) $me['id'], mb_substr($q, 0, 160), null, 'poll', json_encode($ops, JSON_UNESCAPED_UNICODE));
            flash('success', 'Polling terpasang! Gas voting 🗳️🔥');
            return redirect('/kanal/' . $slug);
        }

        $body = trim($req->str('body', '', 1000));
        if ($body === '') {
            return redirect('/kanal/' . $slug . '#kirim');
        }
        // 🤬 AutoMod: sensor kata terlarang milik owner
        $body = sensor_kata($body);
        $id = Channel::post($cid, (int) $me['id'], $body, $replyTo);
        // 🏷️ Mention → notif lonceng
        send_mentions($body, '/kanal/' . $slug . '#m' . $id, (string) $me['name'], [(int) $me['id']]);
        return redirect('/kanal/' . $slug . '#m' . $id);
    }

    /** POST /kanal/{slug}/reaksi/{id} — toggle reaksi emoji. */
    public function react(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        Channel::toggleReaction((int) ($params['id'] ?? 0), (int) $me['id'], $req->str('emoji', '', 8));
        return $this->backToChannel($params);
    }

    /** POST /kanal/{slug}/vote/{id} — pilih opsi polling. */
    public function vote(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        Channel::vote((int) ($params['id'] ?? 0), (int) $me['id'], (int) $req->str('opt', '0', 4));
        flash('success', 'Suaramu tercatat! 🗳️');
        return $this->backToChannel($params);
    }

    /** POST /kanal/{slug}/pin/{id} — PIN pesan (admin/owner). */
    public function pin(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        if (!in_array($me['role'], ['admin', 'owner'], true)) {
            flash('error', 'Hanya admin/owner yang bisa pin pesan.');
            return $this->backToChannel($params);
        }
        $ch = Channel::findBySlug((string) ($params['slug'] ?? ''));
        $mid = (int) ($params['id'] ?? 0);
        if ($ch) {
            Channel::pin((int) $ch['id'], (int) ($ch['pinned_id'] ?? 0) === $mid ? null : $mid);
            flash('success', 'Pin pesan diperbarui. 📌');
        }
        return $this->backToChannel($params);
    }

    /** POST /kanal/{slug}/edit/{id} — edit pesan sendiri (≤15 mnt). */
    public function edit(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        $body = trim($req->str('body', '', 1000));
        if ($body !== '') {
            $ok = Channel::editMessage((int) ($params['id'] ?? 0), (int) $me['id'], sensor_kata($body));
            flash($ok ? 'success' : 'error', $ok ? 'Pesan diedit. ✏️' : 'Tak bisa diedit — bukan milikmu / sudah lewat 15 menit.');
        }
        return $this->backToChannel($params);
    }

    /** POST /kanal/{slug}/hapus/{id} — hapus pesan (pemilik/staf). */
    public function deleteMessage(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        $msg = Channel::findMessage((int) ($params['id'] ?? 0));
        $can = $msg && ((int) $msg['user_id'] === (int) $me['id'] || in_array($me['role'], ['admin', 'owner'], true));
        if ($can) {
            Channel::deleteMessage((int) $msg['id']);
            flash('success', 'Pesan dihapus. 🗑️');
        }
        return $this->backToChannel($params);
    }

    /** POST /kanal/buat — buat channel baru (admin/owner). */
    public function create(Request $req): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        $name  = trim($req->str('name', '', 60));
        $topic = trim($req->str('topic', '', 120));
        $kind  = $req->str('kind', 'text', 10) === 'announce' ? 'announce' : 'text';
        if (Channel::create($name, $topic, $kind, (int) $me['id'])) {
            AuditLogger::record('channel.create', ['name' => $name, 'kind' => $kind], 'info', (int) $me['id'], $req->ip());
            flash('success', 'Kanal baru dibuat! 📢✨');
        } else {
            flash('error', 'Nama kanal terlalu pendek.');
        }
        return redirect('/kanal');
    }

    /** POST /kanal/{slug}/atur — slowmode + topik (admin/owner). */
    public function configure(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        if (!in_array($me['role'], ['admin', 'owner'], true)) {
            return redirect('/kanal');
        }
        $ch = Channel::findBySlug((string) ($params['slug'] ?? ''));
        if ($ch) {
            Channel::setSlowmode((int) $ch['id'], $req->int('slowmode', 0));
            Channel::setTopic((int) $ch['id'], (string) $req->str('topic', '', 120));
            AuditLogger::record('channel.configure', ['slug' => $ch['slug'], 'slowmode' => $req->int('slowmode', 0)], 'info', (int) $me['id'], $req->ip());
            flash('success', 'Pengaturan kanal disimpan. ⚙️');
        }
        return $this->backToChannel($params);
    }

    private function backToChannel(array $params): Response
    {
        return redirect('/kanal/' . preg_replace('/[^a-z0-9-]/', '', (string) ($params['slug'] ?? 'umum')));
    }
}
