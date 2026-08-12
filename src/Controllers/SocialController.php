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
        $storiesBar = [];
        try {
            $storiesBar = \ChiperX\Models\Story::activeBar(12);
        } catch (\Throwable) {
        }
        // 📢 Banner pengumuman terbaru + reaksinya (Telegram channel vibes)
        $banner = null;
        $bannerReacts = [];
        try {
            $acts = \ChiperX\Models\Announcement::active(1);
            if ($acts !== []) {
                $banner = $acts[0];
                $bannerReacts = \ChiperX\Models\Announcement::reactions((int) $banner['id'], (int) ($me['id'] ?? 0));
            }
        } catch (\Throwable) {
        }
        return $this->view('social/feed', [
            'title'    => 'Komunitas',
            'posts'    => $posts,
            'comments' => $comments,
            'me'       => $me,
            'stories'  => $storiesBar,
            'banner'   => $banner,
            'bannerReacts' => $bannerReacts,
        ]);
    }

    /** POST /komunitas/story — unggah Story 24 jam (foto wajib + caption opsional). */
    public function storyStore(Request $req): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $uid  = (int) $user['id'];

        $last = \ChiperX\Models\Story::lastPostedAt($uid);
        if ($last !== null && (time() - strtotime($last)) < 30) {
            flash('warning', 'Story baru tiap 30 detik ya. ⏱️');
            return redirect('/komunitas');
        }
        try {
            $image = $this->handleImageUpload();
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return redirect('/komunitas');
        }
        if ($image === null) {
            flash('error', 'Story butuh foto — pilih gambarmu dulu. 📷');
            return redirect('/komunitas');
        }
        \ChiperX\Models\Story::create($uid, $image, $req->str('caption', '', 120));
        if (random_int(1, 100) <= 10) {
            \ChiperX\Models\Story::prune();
        }
        flash('success', 'Story terpasang 24 jam! ✨');
        return redirect('/komunitas');
    }

    /** GET /story/{username} — penonton story slide (publik). */
    public function storyShow(Request $req, array $params): Response|string
    {
        $target = \ChiperX\Models\User::findByUsername((string) ($params['username'] ?? ''));
        if (!$target) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => 'Pengguna tidak ditemukan']);
        }
        $stories = [];
        try {
            $stories = \ChiperX\Models\Story::byUser((int) $target['id']);
        } catch (\Throwable) {
        }
        if (!$stories) {
            flash('warning', 'Story-nya sudah kedaluwarsa (berlaku 24 jam). ⏰');
            return redirect('/komunitas');
        }
        // 👀 Catat view (IG story insight) — kecuali pemilik story sendiri
        $me = auth_user();
        $viewers = [];
        if ($me) {
            foreach ($stories as $s) {
                if ((int) $target['id'] !== (int) $me['id']) {
                    \ChiperX\Models\Story::recordView((int) $s['id'], (int) $me['id']);
                }
            }
        }
        if ($me && (int) $target['id'] === (int) $me['id']) {
            $viewers = \ChiperX\Models\Story::viewers((int) $stories[0]['id']);
        }
        return $this->view('social/story', [
            'title'   => 'Story — ' . $target['name'],
            'target'  => $target,
            'stories' => $stories,
            'me'      => $me,
            'viewers' => $viewers,
        ]);
    }

    /** POST /story/{id}/delete — hapus story sendiri (admin/owner boleh). */
    public function storyDelete(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user  = auth_user();
        $id    = (int) ($params['id'] ?? 0);
        $story = \ChiperX\Models\Story::find($id);
        $can   = $story && ((int) $story['user_id'] === (int) $user['id'] || in_array($user['role'], ['admin', 'owner'], true));
        if (!$can) {
            flash('error', 'Kamu tidak berhak menghapus story ini.');
            return redirect('/komunitas');
        }
        \ChiperX\Models\Story::delete($id);
        flash('success', 'Story dihapus. 🗑️');
        return redirect('/komunitas');
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
            $image = $this->handleMediaUpload(); // 🎬 foto ATAU video (IG)
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return redirect('/komunitas');
        }

        if ($body === '' && $image === null) {
            flash('error', 'Postingan tidak boleh kosong — tulis sesuatu atau unggah foto/video.');
            return redirect('/komunitas');
        }

        Post::create($uid, $body !== '' ? $body : '📷', $image);
        // 🏷️ Mention di postingan → notif
        send_mentions($body, '/komunitas', (string) $user['name'], [$uid]);
        flash('success', 'Postingan terkirim ke komunitas! 🎉');
        return redirect('/komunitas');
    }

    /** 🧭 GET /jelajahi — grid semua postingan bergambar (Explore gaya Instagram). */
    public function explore(Request $req): string
    {
        $posts = [];
        try {
            $posts = Post::explore(60);
        } catch (\Throwable) {
        }
        return $this->view('social/explore', [
            'title' => 'Jelajahi',
            'posts' => $posts,
            'me'    => auth_user(),
        ]);
    }

    /** ❤️ GET /komunitas/{id}/suka — daftar penyuka postingan. */
    public function likers(Request $req, array $params): Response|string
    {
        $id   = (int) ($params['id'] ?? 0);
        $post = \ChiperX\Core\Database::one(
            'SELECT p.id, p.body, u.name FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?',
            [$id]
        );
        if (!$post) {
            flash('error', 'Postingan tidak ditemukan.');
            return redirect('/komunitas');
        }
        return $this->view('social/likers', [
            'title'  => 'Disukai oleh',
            'post'   => $post,
            'likers' => Post::likers($id),
        ]);
    }

    /** 📥 POST /komunitas/{id}/arsip — arsipkan/pulihkan postingan sendiri. */
    public function toggleArchive(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $user = auth_user();
        $archived = Post::toggleArchive((int) ($params['id'] ?? 0), (int) $user['id']);
        flash('success', $archived ? 'Postingan diarsipkan — hanya kamu yang bisa melihatnya di feed. 📥' : 'Postingan dipulihkan ke publik! 📤');
        $ref = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) ?: '';
        return redirect(str_starts_with($ref, '/profil') ? $ref : '/komunitas');
    }

    /** 😀 POST /pengumuman/{id}/reaksi — toggle reaksi banner pengumuman. */
    public function announceReact(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        \ChiperX\Models\Announcement::toggleReaction((int) ($params['id'] ?? 0), (int) $me['id'], $req->str('emoji', '', 8));
        return redirect('/komunitas#pengumuman');
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
        // ↩️ Balasan komentar (IG)
        $parentId = (int) $req->str('parent_id', '0', 10);
        $parent   = null;
        if ($parentId > 0) {
            $parent = Post::findComment($parentId);
        }
        Post::addComment($id, (int) $user['id'], $body, $parent ? $parentId : null);
        if ((int) $post['user_id'] !== (int) $user['id']) {
            Notification::add((int) $post['user_id'], '💬 ' . $user['name'] . ' mengomentari postinganmu', mb_substr($body, 0, 80), '/komunitas#p' . $id);
        }
        // Notif ke pemilik komentar yang dibalas
        if ($parent && (int) $parent['user_id'] !== (int) $user['id'] && (int) $parent['user_id'] !== (int) $post['user_id']) {
            Notification::add((int) $parent['user_id'], '↩️ ' . $user['name'] . ' membalas komentarmu', mb_substr($body, 0, 80), '/komunitas#p' . $id);
        }
        // 🏷️ Mention @user di komentar → notif (IG)
        send_mentions($body, '/komunitas#p' . $id, (string) $user['name'], [(int) $user['id'], (int) $post['user_id']]);
        return redirect('/komunitas#p' . $id);
    }

    /** POST /komunitas/{id}/simpan — 🔖 bookmark postingan (toggle). */
    public function bookmark(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $me = auth_user();
        $id = (int) ($params['id'] ?? 0);
        $saved = Post::toggleBookmark($id, (int) $me['id']);
        flash('success', $saved ? 'Postingan disimpan! Cek di menu Tersimpan 🔖✨' : 'Dihapus dari Tersimpan.');
        $ref  = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path = parse_url($ref, PHP_URL_PATH) ?: '';
        return redirect($path === '/tersimpan' ? '/tersimpan' : '/komunitas#p' . $id);
    }

    /** GET /tersimpan — galeri postingan yang di-bookmark member. */
    public function bookmarks(Request $req): string
    {
        $me = auth_user();
        return $this->panel('social/bookmarks', [
            'title' => 'Postingan Tersimpan',
            'posts' => Post::bookmarkFeed((int) $me['id'], 30),
        ]);
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
        // Kembali ke halaman moderasi bila dihapus dari sana (admin/owner)
        $refPath = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) ?: '';
        return redirect($refPath === '/admin/komunitas' ? '/admin/komunitas' : '/komunitas');
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

    /** Sajikan foto/VIDEO postingan dari storage/uploads/social (whitelist ketat). */
    public function media(Request $req, array $params): Response
    {
        $name = basename((string) ($params['file'] ?? ''));
        if (!preg_match('/^[a-f0-9]{24}\.(jpg|jpeg|png|webp|gif|mp4|webm)$/', $name)) {
            return Response::html('Not found', 404);
        }
        $path = BASE_PATH . '/storage/uploads/social/' . $name;
        if (!is_file($path)) {
            return Response::html('Not found', 404);
        }
        $mime = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'webp' => 'image/webp', 'gif' => 'image/gif',
            'mp4' => 'video/mp4', 'webm' => 'video/webm',
        ][pathinfo($name, PATHINFO_EXTENSION)];
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=604800'); // cache 7 hari
        readfile($path);
        exit;
    }

    /** Upload media postingan: foto ≤4MB ATAU video mp4/webm ≤25MB → storage/uploads/social. */
    private function handleMediaUpload(): ?string
    {
        if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $f    = $_FILES['image'];
        $ext  = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        $isVid = in_array($ext, ['mp4', 'webm'], true);
        $maxMb = $isVid ? 25 : 4;
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > $maxMb * 1024 * 1024) {
            throw new \RuntimeException(($isVid ? 'Video' : 'Foto') . " gagal diunggah / melebihi {$maxMb}MB.");
        }
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm'], true)) {
            throw new \RuntimeException('Format harus JPG/PNG/WEBP/GIF (foto) atau MP4/WEBM (video).');
        }
        // Validasi konten: foto dicek sosok gambarnya; video dicek signature ftyp/EBML
        if ($isVid) {
            $fh = @fopen((string) $f['tmp_name'], 'rb');
            $head = $fh ? (string) fread($fh, 16) : '';
            if ($fh) {
                fclose($fh);
            }
            if (!str_contains($head, 'ftyp') && !str_starts_with($head, "\x1A\x45\xDF\xA3")) {
                throw new \RuntimeException('File bukan video valid.');
            }
        } elseif (@getimagesize((string) $f['tmp_name']) === false) {
            throw new \RuntimeException('File bukan gambar valid.');
        }
        $dir = BASE_PATH . '/storage/uploads/social';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext; // 24 hex → cocok regex media
        if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Gagal menyimpan media.');
        }
        return $name;
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
