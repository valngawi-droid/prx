<?php
/** Layout panel (Dashboard/Admin/Owner) — sidebar glass + konten. */
$u = auth_user();
$role = $u['role'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$menuUser = [
    ['/dashboard', '◈', 'Dashboard'],
    ['/komunitas', '💬', 'Komunitas'],
    ['/kanal', '📢', 'Kanal'],
    ['/jelajahi', '🧭', 'Jelajahi'],
    ['/pesan', '✉️', 'Pesan'],
    ['/cari', '🔎', 'Pencarian'],
    ['/tools', '🚀', 'Tools AI'],
    ['/games', '🎮', 'Mini Games'],
    ['/redeem', '🎁', 'Redeem Center'],
    ['/store', '🛒', 'Store'],
    ['/profil', '👤', 'Profil'],
    ['/pencapaian', '🏆', 'Pencapaian'],
    ['/tersimpan', '🔖', 'Tersimpan'],
    ['/pengaturan', '⚙️', 'Pengaturan'],
];
$menuAdmin = [
    ['/admin', '📊', 'Ringkasan'],
    ['/admin/products', '📦', 'Produk & Upload'],
    ['/admin/codes', '🎟️', 'Kode Redeem'],
    ['/admin/users', '👥', 'Pengguna'],
    ['/admin/transactions', '🧾', 'Transaksi'],
    ['/admin/tickets', '🎫', 'Tiket Bantuan'],
    ['/admin/announcements', '📢', 'Pengumuman'],
    ['/admin/feedback', '💬', 'Moderasi Ulasan'],
    ['/admin/komunitas', '🧹', 'Moderasi Komunitas'],
];
$menuOwner = [
    ['/owner', '👑', 'Owner Control'],
    ['/owner/users', '🛡️', 'Admin & User'],
    ['/owner/firewall', '🧯', 'Firewall'],
    ['/owner/sentinel', '🤖', 'AI Sentinel'],
    ['/owner/filemanager', '🗂️', 'File Manager'],
    ['/owner/links', '🔗', 'Atur Link'],
    ['/owner/settings', '⚙️', 'Setting & RTP'],
    ['/owner/integrations', '🧩', 'Integrasi & API Keys'],
    ['/owner/backups', '💾', 'Backup DB'],
    ['/owner/api-tokens', '🔑', 'API Tokens'],
    ['/owner/logs', '📜', 'Audit Logs'],
];
$menus = $menuUser;
if ($role === 'admin') { $menus = $menuAdmin; }
if ($role === 'owner') { $menus = array_merge($menuOwner, $menuAdmin); }

// Badge pesan belum dibaca (aman bila tabel belum dimigrasi)
$dmUnread = 0;
try { $dmUnread = \ChiperX\Models\Message::unreadCount((int) $u['id']); } catch (\Throwable) {}

// 🖼️ Logo situs kustom (diunggah owner di Setting) — fallback teks CHIPER X
$siteLogo = '';
try { $siteLogo = trim((string) \ChiperX\Models\Setting::get('site_logo', '')); } catch (\Throwable) {}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <script>document.documentElement.classList.add('js');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(\ChiperX\Core\Csrf::token()) ?>">
    <link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <title><?= e($title ?? 'Panel') ?> — ChiperX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { ink: '#0f172a', neon: { purple: '#a78bfa', cyan: '#22d3ee' } }, fontFamily: { display: ['Space Grotesk', 'sans-serif'] } } } };</script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="bg-ink text-slate-200 font-[Inter] antialiased min-h-screen">

<div class="fixed inset-0 -z-10 pointer-events-none">
    <div class="absolute top-0 right-0 w-[30rem] h-[30rem] rounded-full bg-violet-700/15 blur-[160px]"></div>
    <div class="absolute bottom-0 left-0 w-[24rem] h-[24rem] rounded-full bg-cyan-500/10 blur-[160px]"></div>
</div>

<div class="flex min-h-screen">
    <!-- SIDEBAR -->
    <aside class="w-64 shrink-0 hidden lg:flex flex-col border-r border-white/10 bg-slate-900/50 backdrop-blur-xl p-5 gap-1 sticky top-0 h-screen overflow-y-auto">
        <a href="/" class="font-display font-bold text-lg tracking-widest text-white px-3 py-4">
            <span class="flex items-center gap-2">
                <?php if ($siteLogo !== ''): ?>
                    <img src="<?= e($siteLogo) ?>" alt="Logo" class="h-8 w-8 rounded-xl object-contain drop-shadow-[0_0_10px_rgba(34,211,238,.5)]">
                <?php endif; ?>
                <span>CHIPER<span class="text-neon-cyan drop-shadow-[0_0_10px_rgba(34,211,238,.8)]">X</span></span>
            </span>
            <span class="block text-[10px] tracking-normal text-slate-500 font-normal mt-1 uppercase"><?= e($role) ?> panel</span>
        </a>
        <?php foreach ($menus as [$href, $icon, $label]): ?>
            <a href="<?= e($href) ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition
               <?= $path === $href ? 'bg-gradient-to-r from-violet-600/40 to-cyan-500/20 text-white border border-violet-500/30' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                <span><?= $icon ?></span> <?= e($label) ?>
                <?php if ($href === '/pesan' && $dmUnread > 0): ?>
                    <span class="ml-auto min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[9px] font-bold grid place-items-center"><?= $dmUnread > 99 ? '99+' : $dmUnread ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (in_array($role, ['admin', 'owner'], true)): ?>
            <!-- AREA MEMBER — terpisah dari panel admin/owner -->
            <p class="mt-4 mb-1 px-3 text-[10px] uppercase tracking-widest text-slate-600 border-t border-white/10 pt-4">🎮 Area Member</p>
            <?php foreach ($menuUser as [$href, $icon, $label]): ?>
                <a href="<?= e($href) ?>"
                   class="flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] transition
                   <?= $path === $href ? 'bg-cyan-500/15 text-white border border-cyan-500/30' : 'text-slate-500 hover:bg-white/5 hover:text-white' ?>">
                    <span><?= $icon ?></span> <?= e($label) ?>
                    <?php if ($href === '/pesan' && $dmUnread > 0): ?>
                        <span class="ml-auto min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[9px] font-bold grid place-items-center"><?= $dmUnread > 99 ? '99+' : $dmUnread ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="mt-auto border-t border-white/10 pt-4 px-1">
            <div class="flex items-center gap-3 px-2 pb-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 overflow-hidden shrink-0">
                    <?php if (!empty($u['avatar'])): ?>
                        <img src="/media/avatar/<?= e((string) $u['avatar']) ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold truncate"><?= e($u['name']) ?></p>
                    <p class="text-xs text-slate-500 truncate"><?= e($u['email']) ?></p>
                </div>
            </div>
            <form method="post" action="/logout"><?= csrf_field() ?>
                <button class="w-full text-left text-xs text-red-400/80 hover:text-red-400 px-2 py-1">⏻ Keluar</button>
            </form>
        </div>
    </aside>

    <!-- KONTEN -->
    <div class="flex-1 min-w-0">
        <header class="lg:hidden flex items-center justify-between p-4 border-b border-white/10 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-30">
            <span class="font-display font-bold text-white">CHIPER<span class="text-neon-cyan">X</span></span>
            <div class="flex items-center gap-4">
                <?php $bellCount = \ChiperX\Models\Notification::unreadCount((int) $u['id']); ?>
                <a href="/pesan" title="Pesan" class="relative text-slate-300 text-base leading-none">
                    ✉️
                    <span class="<?= $dmUnread > 0 ? '' : 'hidden' ?> absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-bold grid place-items-center"><?= $dmUnread > 99 ? '99+' : $dmUnread ?></span>
                </a>
                <a href="/notifikasi" title="Notifikasi" class="relative text-slate-300 text-base leading-none">
                    🔔
                    <span class="bell-badge <?= $bellCount > 0 ? '' : 'hidden' ?> absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold grid place-items-center"><?= $bellCount > 99 ? '99+' : $bellCount ?></span>
                </a>
                <a href="/" class="text-xs text-slate-400">← Situs</a>
            </div>
        </header>
        <div class="p-5 sm:p-8 max-w-6xl mx-auto w-full">
            <div class="space-y-2 mb-6">
                <?php foreach (flashes() as $f): ?>
                    <div class="flash px-4 py-3 rounded-xl border text-sm
                        <?= $f['type'] === 'success' ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-300'
                            : ($f['type'] === 'warning' ? 'bg-amber-500/15 border-amber-400/30 text-amber-300'
                            : 'bg-red-500/15 border-red-400/30 text-red-300') ?>"><?= e($f['message']) ?></div>
                <?php endforeach; ?>
            </div>
            <?= $content ?>
        </div>
    </div>
</div>

<!-- Bottom nav PREMIUM 3D (v2.6) — semua halaman; ☰ membuka sheet semua menu -->
<?php $bnavSheet = true; require __DIR__ . '/../partials/bottom_nav.php'; ?>

<!-- Sheet SEMUA menu (mobile) — slide-up, tutup dengan ketuk area gelap -->
<div id="menuSheet" class="lg:hidden fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/70" data-close-sheet></div>
    <div class="absolute bottom-0 inset-x-0 rounded-t-3xl bg-slate-900 border-t border-violet-500/30 p-5 pb-8 max-h-[78vh] overflow-y-auto" style="animation:sheetUp .25s ease-out;">
        <div class="w-10 h-1 rounded-full bg-white/20 mx-auto mb-4"></div>
        <?php if (in_array($role, ['admin', 'owner'], true)): ?>
            <p class="text-[10px] text-slate-500 uppercase tracking-widest mb-3">🛠️ Panel <?= e($role) ?></p>
        <?php else: ?>
            <p class="text-[10px] text-slate-500 uppercase tracking-widest mb-3">Semua Menu</p>
        <?php endif; ?>
        <div class="grid grid-cols-3 gap-2">
            <?php foreach ($menus as [$href, $icon, $label]): ?>
                <a href="<?= e($href) ?>" class="relative rounded-xl border px-2 py-3 text-center text-[11px] leading-tight <?= $path === $href ? 'bg-gradient-to-br from-violet-600/40 to-cyan-500/20 border-violet-400/40 text-white' : 'bg-white/[0.04] border-white/10 text-slate-300 hover:bg-white/[0.08]' ?>">
                    <?php if ($href === '/pesan' && $dmUnread > 0): ?>
                        <span class="absolute top-1.5 right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold grid place-items-center"><?= $dmUnread > 99 ? '99+' : $dmUnread ?></span>
                    <?php endif; ?>
                    <span class="block text-lg mb-1"><?= $icon ?></span><?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (in_array($role, ['admin', 'owner'], true)): ?>
            <!-- AREA MEMBER — dipisah supaya panel tdk tercampur dgn dashboard utama -->
            <p class="text-[10px] text-slate-500 uppercase tracking-widest mt-5 mb-3">🎮 Area Member</p>
            <div class="grid grid-cols-3 gap-2">
                <?php foreach ($menuUser as [$href, $icon, $label]): ?>
                    <a href="<?= e($href) ?>" class="relative rounded-xl border px-2 py-3 text-center text-[11px] leading-tight <?= $path === $href ? 'bg-gradient-to-br from-cyan-500/25 to-emerald-500/10 border-cyan-400/40 text-white' : 'bg-white/[0.04] border-white/10 text-slate-300 hover:bg-white/[0.08]' ?>">
                        <?php if ($href === '/pesan' && $dmUnread > 0): ?>
                            <span class="absolute top-1.5 right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold grid place-items-center"><?= $dmUnread > 99 ? '99+' : $dmUnread ?></span>
                        <?php endif; ?>
                        <span class="block text-lg mb-1"><?= $icon ?></span><?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="/logout" class="mt-4"><?= csrf_field() ?>
            <button class="w-full py-3 rounded-xl bg-red-500/10 border border-red-400/30 text-red-300 text-sm font-semibold">⏻ Keluar</button>
        </form>
    </div>
</div>
<style>@keyframes sheetUp{from{transform:translateY(48px);opacity:.4}to{transform:translateY(0);opacity:1}}</style>

<script>
    setTimeout(() => document.querySelectorAll('.flash').forEach(el => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }), 4500);
    (() => {
        const btn = document.getElementById('menuMoreBtn');
        const sheet = document.getElementById('menuSheet');
        if (!btn || !sheet) return;
        btn.addEventListener('click', () => sheet.classList.remove('hidden'));
        sheet.addEventListener('click', (e) => { if (e.target.hasAttribute('data-close-sheet')) sheet.classList.add('hidden'); });
    })();
    // 🔔 Polling badge notifikasi tiap 25 detik
    (() => {
        const badge = document.querySelector('.bell-badge');
        if (!badge) return;
        const tick = () => fetch('/api/notifikasi')
            .then(r => r.ok ? r.json() : null)
            .then(d => {
                if (!d) return;
                if (d.count > 0) { badge.textContent = d.count > 99 ? '99+' : d.count; badge.classList.remove('hidden'); }
                else { badge.classList.add('hidden'); }
            })
            .catch(() => {});
        setInterval(tick, 25000);
    })();
</script>
<script src="<?= asset('js/panel.js') ?>" defer></script>
</body>
</html>
