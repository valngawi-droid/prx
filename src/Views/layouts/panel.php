<?php
/** Layout panel (Dashboard/Admin/Owner) — sidebar glass + konten. */
$u = auth_user();
$role = $u['role'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$menuUser = [
    ['/dashboard', '◈', 'Dashboard'],
    ['/games', '🎮', 'Mini Games'],
    ['/redeem', '🎁', 'Redeem Center'],
    ['/store', '🛒', 'Store'],
];
$menuAdmin = [
    ['/admin', '📊', 'Ringkasan'],
    ['/admin/products', '📦', 'Produk & Upload'],
    ['/admin/users', '👥', 'Pengguna'],
    ['/admin/transactions', '🧾', 'Transaksi'],
    ['/admin/tickets', '🎫', 'Tiket Bantuan'],
    ['/admin/announcements', '📢', 'Pengumuman'],
    ['/admin/feedback', '💬', 'Moderasi Ulasan'],
];
$menuOwner = [
    ['/owner', '👑', 'Owner Control'],
    ['/owner/users', '🛡️', 'Admin & User'],
    ['/owner/filemanager', '🗂️', 'File Manager'],
    ['/owner/links', '🔗', 'Atur Link'],
    ['/owner/settings', '⚙️', 'Setting & RTP'],
    ['/owner/integrations', '🧩', 'Integrasi & API Keys'],
    ['/owner/api-tokens', '🔑', 'API Tokens'],
    ['/owner/logs', '📜', 'Audit Logs'],
];
$menus = $menuUser;
if ($role === 'admin') { $menus = $menuAdmin; }
if ($role === 'owner') { $menus = array_merge($menuOwner, $menuAdmin); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <script>document.documentElement.classList.add('js');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(\ChiperX\Core\Csrf::token()) ?>">
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
            CHIPER<span class="text-neon-cyan drop-shadow-[0_0_10px_rgba(34,211,238,.8)]">X</span>
            <span class="block text-[10px] tracking-normal text-slate-500 font-normal mt-1 uppercase"><?= e($role) ?> panel</span>
        </a>
        <?php foreach ($menus as [$href, $icon, $label]): ?>
            <a href="<?= e($href) ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition
               <?= $path === $href ? 'bg-gradient-to-r from-violet-600/40 to-cyan-500/20 text-white border border-violet-500/30' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                <span><?= $icon ?></span> <?= e($label) ?>
            </a>
        <?php endforeach; ?>
        <div class="mt-auto border-t border-white/10 pt-4 px-1">
            <div class="flex items-center gap-3 px-2 pb-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900">
                    <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
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
            <a href="/" class="text-xs text-slate-400">← Situs</a>
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

<!-- Bottom nav mobile -->
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-slate-900/90 backdrop-blur-xl border-t border-white/10 flex overflow-x-auto">
    <?php foreach (array_slice($menus, 0, 5) as [$href, $icon, $label]): ?>
        <a href="<?= e($href) ?>" class="flex-1 min-w-[4.5rem] py-2.5 flex flex-col items-center gap-0.5 text-[10px] <?= $path === $href ? 'text-neon-cyan' : 'text-slate-500' ?>">
            <span class="text-base"><?= $icon ?></span><?= e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
<div class="h-16 lg:hidden"></div>

<script>
    setTimeout(() => document.querySelectorAll('.flash').forEach(el => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }), 4500);
</script>
<script src="<?= asset('js/panel.js') ?>" defer></script>
</body>
</html>
