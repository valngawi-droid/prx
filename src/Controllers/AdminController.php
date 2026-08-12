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
        $db = \ChiperX\Core\Database::class;
        return $this->panel('admin/index', [
            'title'         => 'Panel Admin',
            'totalUsers'    => User::totalCount(),
            'totalTx'       => Transaction::totalPaidCount(),
            'totalRevenue'  => Transaction::totalRevenue(),
            'totalPlays'    => GameHistory::totalPlays(),
            'openTickets'   => Ticket::openCount(),
            'pendingFeedback' => Feedback::pendingCount(),
            'recentTx'      => Transaction::recent(10),
            // 📊 grafik & ekonomi koin (v2.7)
            'chartUsers'    => $db::all('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)'),
            'chartTx'       => $db::all("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM transactions WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)"),
            'coinSupply'    => (int) ($db::value('SELECT COALESCE(SUM(coin_balance),0) FROM users') ?? 0),
            'redeemsWeek'   => (int) ($db::value('SELECT COUNT(*) FROM redeem_code_claims WHERE created_at >= CURDATE() - INTERVAL 6 DAY') ?? 0),
            'postsWeek'     => (int) ($db::value('SELECT COUNT(*) FROM posts WHERE created_at >= CURDATE() - INTERVAL 6 DAY') ?? 0),
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
            \ChiperX\Models\Notification::add(
                (int) $ticket['user_id'],
                '💬 Tiketmu dibalas: ' . (string) $ticket['subject'],
                mb_substr($message, 0, 140),
                '/dashboard',
                'success'
            );
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

    // ---------------- KODE REDEEM KUSTOM ----------------

    /** GET /admin/codes */
    public function codes(Request $req): string
    {
        $bulk = $_SESSION['bulk_codes'] ?? null;
        unset($_SESSION['bulk_codes']); // tampil SEKALI saja (post-redirect)
        return $this->panel('admin/codes', [
            'title' => 'Kode Redeem',
            'codes' => \ChiperX\Models\RedeemCode::all(),
            'bulk'  => is_array($bulk) ? $bulk : [],
        ]);
    }

    /** POST /admin/codes/bulk — 🎟️ generator massal: N kode acak sekaligus. */
    public function codesBulk(Request $req): Response
    {
        $this->guardCsrf();
        $me     = auth_user();
        $count  = max(1, min(50, (int) $req->str('count', '10', 4)));
        $prefix = strtoupper((string) (preg_replace('/[^A-Z0-9]/', '', $req->str('prefix', 'EVENT', 12)) ?: 'EVENT'));
        $coins  = max(1, min(100000, (int) $req->str('coins', '50', 8)));
        $quota  = max(1, min(100000, (int) $req->str('quota', '1', 8)));

        $made = [];
        for ($i = 0; $i < $count; $i++) {
            $code = $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $res  = \ChiperX\Models\RedeemCode::create($code, $coins, $quota, null, (int) $me['id']);
            if ($res['ok']) {
                $made[] = $code;
            }
        }
        $_SESSION['bulk_codes'] = $made;
        AuditLogger::record('redeem_code.bulk', ['count' => count($made), 'coins' => $coins, 'prefix' => $prefix], 'warning', (int) $me['id'], $req->ip());
        flash($made !== [] ? 'success' : 'error', $made !== []
            ? count($made) . ' kode massal berhasil dibuat! Salin daftarnya di bawah. 🎟️'
            : 'Gagal membuat kode massal.');
        return redirect('/admin/codes');
    }

    /** POST /admin/users/{id}/coins — 💰 tambah/kurangi koin user (+ notifikasi). */
    public function adjustCoins(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $actor  = auth_user();
        $target = User::find((int) ($params['id'] ?? 0));
        if (!$target) {
            flash('error', 'Pengguna tidak ditemukan.');
            return redirect('/admin/users');
        }
        // Admin biasa hanya boleh menyentuh akun 'user' (admin/owner = wilayah owner)
        if (($target['role'] ?? 'user') !== 'user' && ($actor['role'] ?? '') !== 'owner') {
            flash('error', 'Koin sesama admin/owner hanya bisa diatur oleh Owner.');
            return redirect('/admin/users');
        }
        $delta  = (int) $req->str('delta', '0', 9);
        $reason = mb_substr(trim($req->str('reason', '', 120)), 0, 120);
        if ($delta === 0 || abs($delta) > 100000) {
            flash('error', 'Delta koin tidak valid (maks ±100.000, tidak boleh 0).');
            return redirect('/admin/users');
        }
        if (!User::addCoins((int) $target['id'], $delta)) {
            flash('error', 'Gagal — saldo user tidak boleh minus.');
            return redirect('/admin/users');
        }
        $tanda = $delta > 0 ? '+' . number_format($delta) : '-' . number_format(abs($delta));
        \ChiperX\Models\Notification::add(
            (int) $target['id'],
            ($delta > 0 ? '💰 Kamu menerima ' . $tanda . ' koin!' : '⚠️ Saldo koinmu dikurangi ' . $tanda),
            $reason !== '' ? 'Catatan admin: ' . $reason : null,
            '/dashboard',
            $delta > 0 ? 'success' : 'warning'
        );
        AuditLogger::record('admin.coins_adjust', ['target' => $target['email'], 'delta' => $delta, 'reason' => $reason], 'warning', (int) $actor['id'], $req->ip());
        flash('success', 'Koin ' . $target['name'] . ' berhasil diubah (' . $tanda . '). 💰');
        return redirect('/admin/users');
    }

    /** POST /admin/products/{id}/toggle — 🟢 aktif/nonaktif produk 1-klik. */
    public function productToggle(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $id = (int) ($params['id'] ?? 0);
        \ChiperX\Core\Database::run('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$id]);
        AuditLogger::record('admin.product_toggle', ['product' => $id], 'info', (int) auth_user()['id'], $req->ip());
        flash('success', 'Status produk diubah. 🔄');
        return redirect('/admin/products');
    }

    /** POST /admin/products/{id}/stock — 📦 atur stok cepat (kosong = tak terbatas). */
    public function productStock(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $id    = (int) ($params['id'] ?? 0);
        $raw   = trim($req->str('stock', '', 8));
        $stock = $raw === '' ? null : max(0, min(100000, (int) $raw));
        \ChiperX\Core\Database::run('UPDATE products SET stock = ? WHERE id = ?', [$stock, $id]);
        flash('success', 'Stok produk diperbarui → ' . ($stock === null ? '∞' : (string) $stock) . ' 📦');
        return redirect('/admin/products');
    }

    /** GET /admin/komunitas — 🧹 meja moderasi postingan terbaru. */
    public function community(Request $req): string
    {
        $posts = \ChiperX\Core\Database::all(
            'SELECT p.id, p.body, p.image, p.likes_count, p.comments_count, p.created_at,
                    u.name, u.username, u.role
             FROM posts p JOIN users u ON u.id = p.user_id
             ORDER BY p.id DESC LIMIT 40'
        );
        return $this->panel('admin/community', [
            'title' => 'Moderasi Komunitas',
            'posts' => $posts,
        ]);
    }

    /** POST /admin/broadcast — 📣 kirim notifikasi lonceng ke SEMUA member aktif. */
    public function broadcastBell(Request $req): Response
    {
        $this->guardCsrf();
        $actor = auth_user();
        $title = mb_substr(trim($req->str('title', '', 120)), 0, 120);
        $body  = mb_substr(trim($req->str('body', '', 300)), 0, 300);
        if ($title === '') {
            flash('error', 'Judul broadcast wajib diisi.');
            return redirect('/admin/announcements');
        }
        $sent = 0;
        foreach (\ChiperX\Core\Database::all("SELECT id FROM users WHERE status = 'active'") as $row) {
            \ChiperX\Models\Notification::add((int) $row['id'], '📣 ' . $title, $body !== '' ? $body : null, '/dashboard', 'info');
            $sent++;
        }
        AuditLogger::record('admin.broadcast_bell', ['title' => $title, 'sent' => $sent], 'warning', (int) $actor['id'], $req->ip());
        \ChiperX\Services\DiscordWebhook::send('📣 Broadcast Lonceng', $title, \ChiperX\Services\DiscordWebhook::COLOR_INFO, [
            ['name' => 'Oleh', 'value' => (string) $actor['email'], 'inline' => true],
            ['name' => 'Terkirim', 'value' => $sent . ' member', 'inline' => true],
        ]);
        flash('success', "Broadcast terkirim ke lonceng {$sent} member! 📣🔔");
        return redirect('/admin/announcements');
    }

    /** GET /admin/export/{what}.csv — ⬇️ unduh data sebagai CSV (users/transactions/codes). */
    public function exportCsv(Request $req, array $params): Response
    {
        $what = (string) ($params['what'] ?? '');
        switch ($what) {
            case 'users':
                $head = ['ID', 'Nama', 'Username', 'Email', 'Role', 'Status', 'Koin', 'Saldo', 'Bergabung'];
                $rows = \ChiperX\Core\Database::all('SELECT id, name, username, email, role, status, coin_balance, balance, created_at FROM users ORDER BY id ASC');
                break;
            case 'transactions':
                $head = ['ID', 'Ref', 'Email', 'Produk', 'Metode', 'Jumlah', 'Status', 'Waktu'];
                $rows = \ChiperX\Core\Database::all(
                    "SELECT t.id, t.gateway_ref, u.email, t.product_name, t.payment_method, t.amount, t.status, t.created_at
                     FROM transactions t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.id DESC LIMIT 5000"
                );
                break;
            case 'codes':
                $head = ['Kode', 'Koin', 'Kuota', 'Terpakai', 'Aktif', 'Kedaluwarsa', 'Dibuat'];
                $rows = \ChiperX\Core\Database::all('SELECT code, coins, quota, used, is_active, expires_at, created_at FROM redeem_codes ORDER BY id DESC');
                break;
            default:
                flash('error', 'Jenis ekspor tidak dikenal.');
                return redirect('/admin');
        }
        AuditLogger::record('admin.export_csv', ['what' => $what], 'warning', (int) auth_user()['id'], $req->ip());

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="chiperx-' . $what . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM — ramah Excel
        fputcsv($out, $head);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    /** POST /admin/codes — buat kode baru. */
    public function saveCode(Request $req): Response
    {
        $this->guardCsrf();
        $quota  = $req->str('quota', '', 8);
        $res = \ChiperX\Models\RedeemCode::create(
            $req->str('code', '', 40),
            (int) $req->str('coins', '0', 8),
            $quota === '' ? null : max(1, (int) $quota),
            $req->str('expires_at', '', 25),
            (int) auth_user()['id']
        );
        if ($res['ok']) {
            AuditLogger::record('redeem_code.create', $res, 'warning', (int) auth_user()['id'], $req->ip());
        }
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        return redirect('/admin/codes');
    }

    /** POST /admin/codes/{id}/toggle — aktif/nonaktifkan kode. */
    public function toggleCode(Request $req, array $params): Response
    {
        $this->guardCsrf();
        $id  = (int) $params['id'];
        $on  = \ChiperX\Core\Database::value('SELECT is_active FROM redeem_codes WHERE id = ?', [$id]);
        if ($on !== null) {
            \ChiperX\Models\RedeemCode::setActive($id, !((int) $on === 1));
            flash('success', 'Status kode diubah.');
        }
        return redirect('/admin/codes');
    }

    /** POST /admin/shouts/{id}/delete — moderasi shoutbox. */
    public function shoutDelete(Request $req, array $params): Response
    {
        $this->guardCsrf();
        \ChiperX\Models\Shout::delete((int) $params['id']);
        AuditLogger::record('shout.delete', ['id' => (int) $params['id']], 'warning', (int) auth_user()['id'], $req->ip());
        flash('success', 'Pesan shoutbox dihapus. 🧹');
        return redirect('/dashboard#chat');
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
