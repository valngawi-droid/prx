<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Announcement;
use ChiperX\Models\Feedback;
use ChiperX\Models\GameHistory;
use ChiperX\Models\Product;
use ChiperX\Models\Ticket;
use ChiperX\Models\Transaction;
use ChiperX\Models\User;
use ChiperX\Services\AuditLogger;

/**
 * Panel Admin (role: admin & owner).
 * Fitur manajemen komunitas:
 *  1. Kelola Produk (upload file project ke Redeem Center / Store)
 *  2. Kelola User standar (tambah/edit)
 *  3. Moderasi Ulasan (feedback approve/hapus)
 *  4. Sistem Tiket Bantuan (balas/tutup)
 *  5. Pengumuman Komunitas (buat/aktif-nonaktif)
 *  6. Pantau transaksi & logs (read-only)
 */
final class AdminController extends Controller
{
    public function index(Request $req): string
    {
        return $this->panel('admin/index', [
            'title'         => 'Panel Admin',
            'totalUsers'    => User::totalCount(),
            'totalTx'       => Transaction::totalPaidCount(),
            'totalRevenue'  => Transaction::totalRevenue(),
            'totalPlays'    => GameHistory::totalPlays(),
            'openTickets'   => Ticket::openCount(),
            'pendingFeedback' => Feedback::pendingCount(),
            'recentTx'      => Transaction::recent(10),
        ]);
    }

    // ---------------- PRODUK (Upload File & Project) ----------------

    public function products(Request $req): string
    {
        return $this->panel('admin/products', [
            'title'    => 'Kelola Produk',
            'products' => Product::allAdmin(),
        ]);
    }

    public function storeProduct(Request $req): Response
    {
        $this->guardCsrf();
        $data = $this->validateProduct($req);
        if (isset($data['error'])) {
            flash('error', $data['error']);
            return redirect('/admin/products');
        }
        $data['created_by'] = auth_user()['id'];
        Product::create($data);
        AuditLogger::record('admin.product_create', ['name' => $data['name']], 'info', auth_user()['id'], $req->ip());
        flash('success', 'Produk berhasil ditambahkan.');
        return redirect('/admin/products');
    }

    public function updateProduct(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $product = Product::find((int) $params['id']);
        if (!$product) {
            flash('error', 'Produk tidak ditemukan.');
            return redirect('/admin/products');
        }
        $data = $this->validateProduct($req, fallbackFileUrl: $product['file_url']);
        if (isset($data['error'])) {
            flash('error', $data['error']);
            return redirect('/admin/products');
        }
        Product::updateById((int) $product['id'], $data);
        AuditLogger::record('admin.product_update', ['name' => $data['name']], 'info', auth_user()['id'], $req->ip());
        flash('success', 'Produk diperbarui.');
        return redirect('/admin/products');
    }

    public function deleteProduct(Request $req, array $params): Response
    {
        $this->guardCsrf();
        Product::deleteById((int) $params['id']);
        AuditLogger::record('admin.product_delete', ['id' => (int) $params['id']], 'warning', auth_user()['id'], $req->ip());
        flash('success', 'Produk dihapus.');
        return redirect('/admin/products');
    }

    /** @return array<string, mixed> */
    private function validateProduct(Request $req, ?string $fallbackFileUrl = null): array
    {
        $name  = $req->str('name', '', 160);
        $type  = $req->str('type', '', 20);
        $price = max(0, $req->int('price'));
        $stock = $req->input('stock') === '' || $req->input('stock') === null ? null : max(0, $req->int('stock'));
        $desc  = $req->str('description', '', 5000);

        if ($name === '') {
            return ['error' => 'Nama produk wajib diisi.'];
        }
        if (!in_array($type, ['coin_redeem', 'premium_store'], true)) {
            return ['error' => 'Tipe produk tidak valid.'];
        }

        // file_url: URL eksternal ATAU hasil upload ke storage/uploads
        $fileUrl = $req->str('file_url', '', 500);
        try {
            $uploaded = $this->handleUpload();
        } catch (\Throwable $ex) {
            return ['error' => 'Upload gagal: ' . $ex->getMessage()];
        }
        if ($uploaded !== null) {
            $fileUrl = $uploaded;
        } elseif ($fileUrl === '' && $fallbackFileUrl !== null) {
            $fileUrl = $fallbackFileUrl;
        }
        if ($fileUrl === '') {
            return ['error' => 'Isi URL file eksternal atau upload file project.'];
        }

        return [
            'name'        => $name,
            'type'        => $type,
            'price'       => $price,
            'stock'       => $stock,
            'file_url'    => $fileUrl,
            'description' => $desc,
            'is_featured' => $req->input('is_featured') ? 1 : 0,
            'is_active'   => $req->input('is_active') ? 1 : 0,
        ];
    }

    /** Upload aman: whitelist ekstensi + rename UUID + simpan di luar web root. */
    private function handleUpload(): ?string
    {
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 50 * 1024 * 1024) {
            throw new \RuntimeException('Upload gagal / file melebihi 50MB.');
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['zip', 'rar', '7z', 'pdf', 'txt', 'md', 'png', 'jpg', 'jpeg', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException('Ekstensi file tidak diizinkan.');
        }
        $dir = BASE_PATH . '/storage/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('Gagal menyimpan file upload.');
        }
        return $name; // path relatif terhadap storage/uploads
    }

    // ---------------- USER STANDAR (Add/Edit) ----------------

    public function users(Request $req): string
    {
        $page  = max(1, $req->int('page', 1));
        $search = $req->str('q', '', 60);
        return $this->panel('admin/users', [
            'title'   => 'Kelola Pengguna',
            'users'   => User::paginate($page, 15, $search, 'user'),
            'search'  => $search,
            'page'    => $page,
            'total'   => User::countFiltered($search, 'user'),
            'perPage' => 15,
        ]);
    }

    public function storeUser(Request $req): Response
    {
        $this->guardCsrf();
        $email = $req->email('email');
        if (!$email || User::findByEmail($email)) {
            flash('error', 'Email tidak valid atau sudah terdaftar.');
            return redirect('/admin/users');
        }
        User::createFromEmail($email, 'user');
        AuditLogger::record('admin.user_create', ['email' => $email], 'info', auth_user()['id'], $req->ip());
        flash('success', 'Pengguna baru dibuat. Mereka bisa login via OTP.');
        return redirect('/admin/users');
    }

    public function updateUser(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $actor  = auth_user();
        $target = User::find((int) $params['id']);
        // Admin tidak boleh menyentuh owner/admin lain — hanya owner yang boleh
        if (!$target || ($target['role'] !== 'user' && $actor['role'] !== 'owner')) {
            flash('error', 'Tidak diizinkan mengubah akun ini.');
            return redirect('/admin/users');
        }
        $name  = $req->str('name', '', 80) ?: $target['name'];
        $coins = max(0, $req->int('coin_balance', (int) $target['coin_balance']));
        User::adminUpdate((int) $target['id'], [
            'name'         => $name,
            'role'         => $target['role'], // admin tidak mengubah role
            'status'       => $target['status'],
            'coin_balance' => $coins,
        ]);
        AuditLogger::record('admin.user_update', ['target' => $target['email'], 'coins' => $coins], 'info', $actor['id'], $req->ip());
        flash('success', 'Pengguna diperbarui.');
        return redirect('/admin/users');
    }

    // ---------------- TRANSAKSI & LOGS (read-only) ----------------

    public function transactions(Request $req): string
    {
        return $this->panel('admin/transactions', [
            'title'        => 'Log Transaksi',
            'transactions' => Transaction::recent(50),
        ]);
    }

    // ---------------- TIKET BANTUAN ----------------

    public function tickets(Request $req): string
    {
        return $this->panel('admin/tickets', [
            'title'   => 'Tiket Bantuan',
            'tickets' => Ticket::allAdmin(),
            'active'  => ($tid = $req->int('open', 0)) ? Ticket::find($tid) : null,
            'replies' => ($tid = $req->int('open', 0)) ? Ticket::replies($tid) : [],
        ]);
    }

    public function replyTicket(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $ticket  = Ticket::find((int) $params['id']);
        $message = $req->str('message', '', 2000);
        if ($ticket && mb_strlen($message) >= 2) {
            Ticket::reply((int) $ticket['id'], (int) auth_user()['id'], $message, byStaff: true);
            flash('success', 'Balasan terkirim ke pengguna.');
        }
        return redirect('/admin/tickets?open=' . (int) $params['id']);
    }

    public function closeTicket(Request $req, array $params): Response
    {
        $this->guardCsrf();
        Ticket::setStatus((int) $params['id'], $req->str('status', 'closed', 10));
        flash('success', 'Status tiket diperbarui.');
        return redirect('/admin/tickets');
    }

    // ---------------- PENGUMUMAN ----------------

    public function announcements(Request $req): string
    {
        return $this->panel('admin/announcements', [
            'title'         => 'Pengumuman',
            'announcements' => Announcement::allAdmin(),
        ]);
    }

    public function storeAnnouncement(Request $req): Response
    {
        $this->guardCsrf();
        $title = $req->str('title', '', 160);
        $body  = $req->str('body', '', 3000);
        if ($title === '' || $body === '') {
            flash('error', 'Judul dan isi pengumuman wajib diisi.');
            return redirect('/admin/announcements');
        }
        Announcement::create($title, $body, $req->str('level', 'info', 10), auth_user()['id']);
        flash('success', 'Pengumuman diterbitkan.');
        return redirect('/admin/announcements');
    }

    public function toggleAnnouncement(Request $req, array $params): Response
    {
        $this->guardCsrf();
        Announcement::toggle((int) $params['id']);
        return redirect('/admin/announcements');
    }

    public function deleteAnnouncement(Request $req, array $params): Response
    {
        $this->guardCsrf();
        Announcement::deleteById((int) $params['id']);
        flash('success', 'Pengumuman dihapus.');
        return redirect('/admin/announcements');
    }

    // ---------------- MODERASI ULASAN ----------------

    public function feedback(Request $req): string
    {
        return $this->panel('admin/feedback', [
            'title'    => 'Moderasi Ulasan',
            'pending'  => Feedback::pending(),
            'approved' => Feedback::approved(20),
        ]);
    }

    public function approveFeedback(Request $req, array $params): Response
    {
        $this->guardCsrf();
        Feedback::approve((int) $params['id']);
        flash('success', 'Ulasan disetujui & tampil di beranda.');
        return redirect('/admin/feedback');
    }

    public function deleteFeedback(Request $req, array $params): Response
    {
        $this->guardCsrf();
        Feedback::deleteById((int) $params['id']);
        flash('success', 'Ulasan dihapus.');
        return redirect('/admin/feedback');
    }
}
