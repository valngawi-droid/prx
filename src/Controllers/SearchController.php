<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Database;
use ChiperX\Core\Request;
use ChiperX\Core\Response;

/**
 * 🔎 PENCARIAN GLOBAL gaya Telegram — /cari?q=
 * Cari postingan komunitas, pesan DM milikmu, dan pesan kanal sekaligus.
 */
final class SearchController extends Controller
{
    public function index(Request $req): string
    {
        $me = auth_user();
        $q  = trim($req->str('q', '', 60));
        $posts = $dm = $kanal = [];
        if ($q !== '' && mb_strlen($q) >= 2) {
            $like = '%' . $q . '%';
            $myId = (int) ($me['id'] ?? 0);
            try {
                $posts = Database::all(
                    'SELECT p.id, p.body, p.image, p.created_at, u.name, u.username, u.avatar
                     FROM posts p JOIN users u ON u.id = p.user_id
                     WHERE p.body LIKE ? AND p.is_archived = 0
                     ORDER BY p.id DESC LIMIT 15',
                    [$like]
                );
                $kanal = Database::all(
                    'SELECT m.id, m.body, m.created_at, c.slug, c.name AS channel_name, u.name, u.username
                     FROM channel_messages m
                     JOIN channels c ON c.id = m.channel_id
                     JOIN users u ON u.id = m.user_id
                     WHERE m.body LIKE ? ORDER BY m.id DESC LIMIT 15',
                    [$like]
                );
                if ($me) {
                    $dm = Database::all(
                        'SELECT m.id, m.body, m.created_at, m.deleted_at,
                                u.name, u.username,
                                IF(m.sender_id = ?, "out", "in") AS arah
                         FROM messages m
                         JOIN users u ON u.id = IF(m.sender_id = ?, m.recipient_id, m.sender_id)
                         WHERE (m.sender_id = ? OR m.recipient_id = ?)
                           AND m.body LIKE ? AND m.deleted_at IS NULL
                           AND (m.expire_at IS NULL OR m.expire_at > NOW())
                         ORDER BY m.id DESC LIMIT 15',
                        [$myId, $myId, $myId, $myId, $like]
                    );
                }
            } catch (\Throwable) {
                // tabel sosial belum ada → hasil kosong ramah
            }
        }
        return $this->view('search', [
            'title' => 'Pencarian',
            'q'     => $q,
            'posts' => $posts,
            'dm'    => $dm,
            'kanal' => $kanal,
            'me'    => $me,
        ]);
    }
}
