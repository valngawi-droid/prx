<?php /** Layout publik — dark theme, glassmorphism, Tailwind CDN */ ?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <script>document.documentElement.classList.add('js');</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(\ChiperX\Core\Csrf::token()) ?>">
    <?php
    $siteDesc  = (string) \ChiperX\Models\Setting::get('hero_desc', 'ChiperX — platform komunitas digital: mini games, redeem center & store premium.');
    $canonical = \ChiperX\Core\Config::appUrl() . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    ?>
    <meta name="description" content="<?= e($siteDesc) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <!-- Open Graph — preview cakep saat link dishare ke WA/Discord/Telegram -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ChiperX">
    <meta property="og:title" content="<?= e(($title ?? 'ChiperX') . ' — ChiperX') ?>">
    <meta property="og:description" content="<?= e($siteDesc) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e(\ChiperX\Core\Config::appUrl() . '/assets/icons/icon-512.png') ?>">
    <meta name="twitter:card" content="summary">
    <!-- PWA + ikon -->
    <link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <title><?= e($title ?? 'ChiperX') ?> — ChiperX</title>
    <!-- Optimasi mobile & desktop -->
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <?php $theme = theme_colors(); /* warna aksen dari Owner > Setting */ ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { ink: '#0f172a', neon: { purple: '<?= e($theme['purple']) ?>', cyan: '<?= e($theme['cyan']) ?>', green: '<?= e($theme['green']) ?>' } },
                    fontFamily: { display: ['Space Grotesk', 'sans-serif'] }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="bg-ink text-slate-200 font-[Inter] antialiased min-h-screen selection:bg-neon-purple/40">

<!-- Latar gradasi ambient -->
<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
    <div class="absolute -top-40 -left-40 w-[36rem] h-[36rem] rounded-full bg-violet-700/20 blur-[140px]"></div>
    <div class="absolute top-1/3 -right-40 w-[32rem] h-[32rem] rounded-full bg-cyan-500/10 blur-[140px]"></div>
    <div class="absolute bottom-0 left-1/3 w-[28rem] h-[28rem] rounded-full bg-emerald-500/10 blur-[160px]"></div>
</div>

<!-- NAVBAR glassmorphism -->
<nav class="fixed top-0 inset-x-0 z-40 backdrop-blur-xl bg-slate-900/60 border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <a href="/" class="font-display font-bold text-xl tracking-widest text-white">
            CHIPER<span class="text-neon-cyan drop-shadow-[0_0_10px_rgba(34,211,238,.8)]">X</span>
        </a>
        <div class="hidden md:flex items-center gap-7 text-sm font-medium">
            <a href="/" class="hover:text-neon-cyan transition">Beranda</a>
            <a href="/info" class="hover:text-neon-cyan transition">Informasi</a>
            <a href="/links" class="hover:text-neon-cyan transition">All Link</a>
            <a href="/redeem" class="hover:text-neon-cyan transition">Redeem Center</a>
            <a href="/store" class="hover:text-neon-cyan transition">Store</a>
            <a href="/games" class="hover:text-neon-purple transition">🎮 Mini Games</a>
            <a href="/komunitas" class="hover:text-neon-cyan transition">💬 Komunitas</a>
            <a href="/members" class="hover:text-neon-cyan transition">👥 Members</a>
        </div>
        <div class="flex items-center gap-3">
            <?php if (is_logged_in()): $u = auth_user(); $bellCount = \ChiperX\Models\Notification::unreadCount((int) $u['id']); $dmCount = 0; try { $dmCount = \ChiperX\Models\Message::unreadCount((int) $u['id']); } catch (\Throwable) {} ?>
                <span class="hidden sm:flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-white/5 border border-white/10">
                    🪙 <b class="text-amber-300"><?= e(number_format((int) $u['coin_balance'])) ?></b>
                </span>
                <a href="/pesan" title="Pesan" class="relative text-slate-300 hover:text-neon-cyan transition text-base leading-none">
                    ✉️
                    <span class="<?= $dmCount > 0 ? '' : 'hidden' ?> absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-bold grid place-items-center shadow-[0_0_10px_rgba(244,63,94,.7)]"><?= $dmCount > 99 ? '99+' : $dmCount ?></span>
                </a>
                <a href="/notifikasi" title="Notifikasi" class="relative text-slate-300 hover:text-neon-cyan transition text-base leading-none">
                    🔔
                    <span class="bell-badge <?= $bellCount > 0 ? '' : 'hidden' ?> absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold grid place-items-center shadow-[0_0_10px_rgba(239,68,68,.7)]"><?= $bellCount > 99 ? '99+' : $bellCount ?></span>
                </a>
                <a href="/profil" title="Profil saya" class="text-slate-300 hover:text-neon-cyan transition text-sm">👤</a>
                <a href="<?= $u['role'] === 'owner' ? '/owner' : ($u['role'] === 'admin' ? '/admin' : '/dashboard') ?>"
                   class="text-sm px-4 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">Dashboard</a>
                <form method="post" action="/logout" class="hidden sm:block"><?= csrf_field() ?>
                    <button class="text-xs text-slate-400 hover:text-red-400 transition">Keluar</button>
                </form>
            <?php else: ?>
                <a href="/login" class="text-sm px-5 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">Masuk OTP</a>
            <?php endif; ?>
            <button id="navToggle" class="md:hidden p-2 rounded-lg border border-white/10">☰</button>
        </div>
    </div>
    <div id="navMobile" class="md:hidden hidden border-t border-white/10 bg-slate-900/90 backdrop-blur-xl px-6 py-4 space-y-3 text-sm" style="padding-bottom:calc(1rem + env(safe-area-inset-bottom));">
        <a href="/" class="block hover:text-neon-cyan py-1">Beranda</a>
        <a href="/info" class="block hover:text-neon-cyan py-1">Informasi</a>
        <a href="/links" class="block hover:text-neon-cyan py-1">All Link</a>
        <a href="/komunitas" class="block hover:text-neon-cyan py-1">💬 Komunitas</a>
        <a href="/members" class="block hover:text-neon-cyan py-1">👥 Members</a>
        <a href="/redeem" class="block hover:text-neon-cyan py-1">Redeem Center</a>
        <a href="/store" class="block hover:text-neon-cyan py-1">Store</a>
        <a href="/games" class="block hover:text-neon-cyan py-1">Mini Games</a>
    </div>
</nav>

<!-- FLASH MESSAGES -->
<div class="fixed top-20 right-4 z-50 space-y-2 w-80 max-w-[calc(100vw-2rem)]">
    <?php foreach (flashes() as $f): ?>
        <div class="flash px-4 py-3 rounded-xl border backdrop-blur-xl text-sm shadow-xl
            <?= $f['type'] === 'success' ? 'bg-emerald-500/15 border-emerald-400/30 text-emerald-300'
                : ($f['type'] === 'warning' ? 'bg-amber-500/15 border-amber-400/30 text-amber-300'
                : 'bg-red-500/15 border-red-400/30 text-red-300') ?>">
            <?= e($f['message']) ?>
        </div>
    <?php endforeach; ?>
</div>

<main class="pt-16 min-h-screen">
    <?= $content ?>
</main>

<footer class="border-t border-white/10 mt-20">
    <div class="max-w-7xl mx-auto px-6 py-10 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-slate-400">
        <p>© <?= date('Y') ?> <span class="text-white font-display font-bold">CHIPER<span class="text-neon-cyan">X</span></span> — dibuat dengan 💜 untuk komunitas.</p>
        <div class="flex gap-5">
            <a href="/info" class="hover:text-neon-cyan transition">Informasi</a>
            <a href="/links" class="hover:text-neon-purple transition">All Link</a>
            <a href="/store" class="hover:text-neon-cyan transition">Store</a>
        </div>
    </div>
</footer>

<script>
    // Navbar mobile + auto-dismiss flash
    document.getElementById('navToggle')?.addEventListener('click', () => document.getElementById('navMobile').classList.toggle('hidden'));
    setTimeout(() => document.querySelectorAll('.flash').forEach(el => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }), 4500);
    <?php if (is_logged_in()): ?>
    // 🔔 Polling badge notifikasi tiap 25 detik (ringan: hanya angka)
    (() => {
        const badge = document.querySelector('.bell-badge');
        if (!badge) return;
        const tick = () => fetch('/api/notifikasi', { headers: { 'X-Requested-With': 'fetch' } })
            .then(r => r.ok ? r.json() : null)
            .then(d => {
                if (!d) return;
                if (d.count > 0) { badge.textContent = d.count > 99 ? '99+' : d.count; badge.classList.remove('hidden'); }
                else { badge.classList.add('hidden'); }
            })
            .catch(() => {});
        setInterval(tick, 25000);
    })();
    <?php endif; ?>
</script>
<!-- Animasi reveal [data-anim] — landing.js punya fallback non-GSAP, dan tanpa JS pun konten tetap tampil (CSS: html.js gate) -->
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
<script>
    // PWA — daftarkan service worker (aktif di https/localhost)
    if ('serviceWorker' in navigator) {
        addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>
</body>
</html>
