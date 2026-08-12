<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Config;
use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Notification;
use ChiperX\Models\Post;
use ChiperX\Services\AuditLogger;

/**
 * Feed Komunitas ala Instagram/Facebook:
 *  - GET  /komunitas                       → feed publik (interaksi butuh login)
 *  - POST /komunitas/post                  → buat postingan (teks + foto opsional)
 *  - POST /komunitas/{id}/like             → toggle ❤️
 *  - POST /komunitas/{id}/comment          → tambah komentar
 *  - POST /komunitas/{id}/delete           → hapus post (pemilik/admin/owner)
 *  - POST /komunitas/komentar/{id}/delete  → hapus komentar
 *  - GET  /media/social/{file}             → sajikan foto postingan
 */
final class SocialController extends Controller
{
    public function feed(Request $req): string
    {
        $me = auth_user();
        $posts = [];
        try {
            $posts = Post::feed(20, (int) ($me['id'] ?? 0));
        } catch (\Throwable) {
            $posts = []; // tabel sosial belum dimigrasi → feed kosong ramah
        }
        $comments = [];
        foreach ($posts as $p) {
            try {
                $comments[(int) $p['id']] = Post::comments((int) $p['id'], 30);
            } catch (\Throwable) {
                $comments[(int) $p['id']] = [];
            }
        }
        return $this->view('social/feed', [
            'title'    => 'Komunitas',
            'posts'    => $posts,
            'comments' => $comments,
            'me'       => $me,
        ]);
    }

    public function store(Request $req): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $uid  = (int) $user['id'];

        // Throttle lembut: 1 posting / 10 detik
        $last = Post::lastPostedAt($uid);
        if ($last !== null && (time() - strtotime($last)) < 10) {
            flash('warning', 'Pelan-pelan saja — 1 postingan tiap 10 detik. 😊');
            return redirect('/komunitas');
        }

        $body = trim($req->str('body', '', 500));
        $image = null;
        try {
            $image = $this->handleImageUpload();
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return redirect('/komunitas');
        }

        if ($body === '' && $image === null) {
            flash('error', 'Postingan tidak boleh kosong — tulis sesuatu atau unggah foto.');
            return redirect('/komunitas');
        }

        Post::create($uid, $body !== '' ? $body : '📷', $image);
        flash('success', 'Postingan terkirim ke komunitas! 🎉');
        return redirect('/komunitas');
    }

    public function like(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $id   = (int) ($params['id'] ?? 0);
        if (!Post::find($id)) {
            flash('error', 'Postingan tidak ditemukan.');
            return redirect('/komunitas');
        }
        $res = Post::toggleLike($id, (int) $user['id']);

        // Notif ke pemilik postingan (hanya saat menyukai, bukan batal)
        $post = Post::find($id);
        if ($res['liked'] && $post && (int) $post['user_id'] !== (int) $user['id']) {
            Notification::add((int) $post['user_id'], '❤️ ' . $user['name'] . ' menyukai postinganmu', null, '/komunitas#p' . $id);
        }
        return redirect('/komunitas#p' . $id);
    }

    public function comment(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $id   = (int) ($params['id'] ?? 0);
        $post = Post::find($id);
        if (!$post) {
            flash('error', 'Postingan tidak ditemukan.');
            return redirect('/komunitas');
        }
        $body = trim($req->str('body', '', 300));
        if ($body === '') {
            flash('error', 'Komentar tidak boleh kosong.');
            return redirect('/komunitas#p' . $id);
        }
        Post::addComment($id, (int) $user['id'], $body);
        if ((int) $post['user_id'] !== (int) $user['id']) {
            Notification::add((int) $post['user_id'], '💬 ' . $user['name'] . ' mengomentari postinganmu', mb_substr($body, 0, 80), '/komunitas#p' . $id);
        }
        return redirect('/komunitas#p' . $id);
    }

    public function deletePost(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $id   = (int) ($params['id'] ?? 0);
        $post = Post::find($id);
        $can  = $post && ((int) $post['user_id'] === (int) $user['id'] || in_array($user['role'], ['admin', 'owner'], true));
        if (!$can) {
            flash('error', 'Kamu tidak berhak menghapus postingan ini.');
            return redirect('/komunitas');
        }
        Post::delete($id);
        AuditLogger::record('post.delete', ['post' => $id, 'by' => $user['email']], 'warning', (int) $user['id'], $req->ip());
        flash('success', 'Postingan dihapus. 🗑️');
        return redirect('/komunitas');
    }

    public function deleteComment(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $id   = (int) ($params['id'] ?? 0);
        $c    = Post::findComment($id);
        $can  = $c && ((int) $c['user_id'] === (int) $user['id']
                    || (int) $c['post_owner'] === (int) $user['id']
                    || in_array($user['role'], ['admin', 'owner'], true));
        if (!$can) {
            flash('error', 'Kamu tidak berhak menghapus komentar ini.');
            return redirect('/komunitas');
        }
        Post::deleteComment($id);
        flash('success', 'Komentar dihapus.');
        return redirect('/komunitas#p' . (int) $c['post_id']);
    }

    /** Sajikan foto postingan dari storage/uploads/social (whitelist ketat). */
    public function media(Request $req, array $params): Response
    {
        $name = basename((string) ($params['file'] ?? ''));
        if (!preg_match('/^[a-f0-9]{24}\.(jpg|jpeg|png|webp|gif)$/', $name)) {
            return Response::html('Not found', 404);
        }
        $path = BASE_PATH . '/storage/uploads/social/' . $name;
        if (!is_file($path)) {
            return Response::html('Not found', 404);
        }
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][pathinfo($name, PATHINFO_EXTENSION)];
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=604800'); // cache 7 hari
        readfile($path);
        exit;
    }

    /** Upload foto postingan: jpg/png/webp/gif ≤ 4MB → storage/uploads/social. */
    private function handleImageUpload(): ?string
    {
        if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 4 * 1024 * 1024) {
            throw new \RuntimeException('Foto gagal diunggah / melebihi 4MB.');
        }
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            throw new \RuntimeException('Format foto harus JPG/PNG/WEBP/GIF.');
        }
        // Validasi konten benar-benar gambar
        if (@getimagesize((string) $f['tmp_name']) === false) {
            throw new \RuntimeException('File bukan gambar valid.');
        }
        $dir = BASE_PATH . '/storage/uploads/social';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext; // 24 hex → cocok regex media
        if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Gagal menyimpan foto.');
        }
        return $name;
    }
}
