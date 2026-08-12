<?php
/**
 * ============================================================
 * 🧭 Bottom Navbar PREMIUM 3D — tampil di SEMUA halaman (mobile)
 * ============================================================
 * Dipakai oleh layout main.php & panel.php; jadi navigasi utama
 * SELALU tersedia di bawah — tak perlu lagi menekan tombol
 * "Dashboard" di atas.
 *
 * Variabel opsional dari layout pemanggil:
 *   $bnavSheet = true  → item terakhir = tombol ☰ "Menu"
 *                        (membuka #menuSheet milik layout panel)
 *   $bnavSheet = false → item terakhir = 👤 Profil / ✨ Masuk
 * ============================================================
 */

$bnavUser  = auth_user();
$bnavPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bnavSheet = $bnavSheet ?? false;

// Badge pesan belum dibaca (aman bila tabel belum ada — auto-migrasi menangani)
$bnavDm = 0;
if ($bnavUser !== null) {
    try {
        $bnavDm = (int) \ChiperX\Models\Message::unreadCount((int) $bnavUser['id']);
    } catch (\Throwable) {
    }
}

if ($bnavUser === null) {
    // ── Tamu: jelajah publik + ajakan masuk ──
    $bnavItems = [
        ['/', '🏠', 'Beranda'],
        ['/komunitas', '💬', 'Komunitas'],
        ['/games', '🎮', 'Games'],
        ['/store', '🛒', 'Store'],
    ];
    $bnavTail = ['/login', '✨', 'Masuk'];
} else {
    // ── Pintasan BERBEDA per peran (sama seperti menu panel) ──
    $bnavItems = match ((string) ($bnavUser['role'] ?? 'user')) {
        'owner' => [
            ['/owner', '👑', 'Owner'],
            ['/admin', '📊', 'Admin'],
            ['/komunitas', '💬', 'Komunitas'],
            ['/pesan', '✉️', 'Pesan'],
        ],
        'admin' => [
            ['/admin', '📊', 'Ringkasan'],
            ['/admin/products', '📦', 'Produk'],
            ['/komunitas', '💬', 'Komunitas'],
            ['/pesan', '✉️', 'Pesan'],
        ],
        default => [
            ['/dashboard', '◈', 'Dasbor'],
            ['/komunitas', '💬', 'Komunitas'],
            ['/tools', '🚀', 'Tools'],
            ['/pesan', '✉️', 'Pesan'],
        ],
    };
    $bnavTail = $bnavSheet ? null : ['/profil', '👤', 'Profil'];
}

// Pencocok status aktif: '/' hanya pas persis; lainnya boleh sub-halaman
$bnavIsActive = static function (string $href) use ($bnavPath): bool {
    if ($href === '/') {
        return $bnavPath === '/';
    }
    return $bnavPath === $href || str_starts_with($bnavPath, $href . '/');
};

// Untuk mode sheet: ☰ menyala bila halaman sekarang tak ada di pintasan
$bnavInQuick = false;
foreach ($bnavItems as $bnavIt) {
    if ($bnavIsActive((string) $bnavIt[0])) {
        $bnavInQuick = true;
        break;
    }
}
?>
<nav class="bnav lg:hidden" aria-label="Navigasi cepat bawah">
    <div class="bnav-bar">
        <?php foreach ($bnavItems as [$bnavHref, $bnavIcon, $bnavLabel]): ?>
            <?php $bnavOn = $bnavIsActive((string) $bnavHref); ?>
            <a href="<?= e((string) $bnavHref) ?>" class="bnav-item <?= $bnavOn ? 'active' : '' ?>" <?= $bnavOn ? 'aria-current="page"' : '' ?>>
                <span class="bnav-ic"><?= $bnavIcon ?>
                    <?php if ($bnavHref === '/pesan' && $bnavDm > 0): ?>
                        <span class="bnav-badge"><?= $bnavDm > 99 ? '99+' : $bnavDm ?></span>
                    <?php endif; ?>
                </span>
                <span class="bnav-tx"><?= e((string) $bnavLabel) ?></span>
                <span class="bnav-dot"></span>
            </a>
        <?php endforeach; ?>
        <?php if ($bnavUser !== null && $bnavSheet): ?>
            <button type="button" id="menuMoreBtn" class="bnav-item <?= $bnavInQuick ? '' : 'active' ?>" aria-label="Buka semua menu">
                <span class="bnav-ic">☰</span>
                <span class="bnav-tx">Menu</span>
                <span class="bnav-dot"></span>
            </button>
        <?php elseif ($bnavTail !== null): ?>
            <?php $bnavTailOn = $bnavIsActive((string) $bnavTail[0]); ?>
            <a href="<?= e((string) $bnavTail[0]) ?>" class="bnav-item <?= $bnavTailOn ? 'active' : '' ?>" <?= $bnavTailOn ? 'aria-current="page"' : '' ?>>
                <span class="bnav-ic"><?= $bnavTail[1] ?></span>
                <span class="bnav-tx"><?= e((string) $bnavTail[2]) ?></span>
                <span class="bnav-dot"></span>
            </a>
        <?php endif; ?>
    </div>
</nav>
<div class="bnav-spacer lg:hidden" aria-hidden="true"></div>
