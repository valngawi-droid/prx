<?php /** BERANDA — hero 3D interaktif + hero glitch + statistik + testimoni */ ?>

<!-- ============ HERO dengan CANVAS 3D ============ -->
<section class="relative min-h-[92vh] flex items-center overflow-hidden">
    <canvas id="hero3d" class="absolute inset-0 w-full h-full"></canvas>
    <div class="relative z-10 max-w-7xl mx-auto px-6 py-24 grid lg:grid-cols-2 gap-10 items-center w-full">
        <div>
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs text-neon-cyan backdrop-blur-xl" data-anim="fade-up">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                SISTEM ONLINE — <?= e(date('d F Y')) ?>
            </span>
            <h1 class="mt-6 font-display font-bold text-5xl sm:text-6xl xl:text-7xl leading-[1.05] text-white" data-anim="fade-up">
                Selamat Datang di
                <span class="block glitch neon-text" data-text="CHIPERX">CHIPERX</span>
            </h1>
            <p class="mt-6 text-lg text-slate-300 max-w-xl" data-anim="fade-up" id="heroGreeting">
                <?= e($tagline) ?>
            </p>
            <p class="mt-2 text-sm text-slate-500" data-anim="fade-up">
                <?= e($heroDesc ?? '') ?>
            </p>
            <div class="mt-8 flex flex-wrap gap-4" data-anim="fade-up">
                <a href="<?= is_logged_in() ? '/games' : '/login' ?>"
                   class="px-7 py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white shadow-lg shadow-violet-600/40 hover:scale-105 hover:shadow-cyan-500/40 transition transform">
                    🎮 <?= is_logged_in() ? 'Main Sekarang' : 'Mulai Gratis' ?>
                </a>
                <a href="/links" class="px-7 py-3.5 rounded-xl bg-white/5 border border-white/15 backdrop-blur-xl font-semibold hover:bg-white/10 hover:scale-105 transition transform">
                    🔗 Semua Link Kami
                </a>
            </div>
        </div>
        <div class="hidden lg:block h-[420px]"><!-- ruang untuk objek 3D melayang --></div>
    </div>
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-slate-500 text-xs tracking-widest animate-bounce">SCROLL ▾</div>
</section>

<!-- ============ STATISTIK ============ -->
<section class="max-w-7xl mx-auto px-6 -mt-6">
    <div class="grid sm:grid-cols-3 gap-5">
        <?php
        $stats = [
            ['Total Pengguna', $totalUsers, '👥', 'from-violet-600/30 to-violet-600/5 border-violet-500/30'],
            ['Total Transaksi', $totalTx, '🧾', 'from-cyan-500/30 to-cyan-500/5 border-cyan-500/30'],
            ['Total Project', $totalProducts, '📦', 'from-emerald-500/30 to-emerald-500/5 border-emerald-500/30'],
        ];
        foreach ($stats as [$label, $value, $icon, $style]): ?>
            <div class="glass-card bg-gradient-to-br <?= e($style) ?> p-6 text-center" data-anim="fade-up">
                <div class="text-3xl"><?= $icon ?></div>
                <div class="mt-2 font-display text-4xl font-bold text-white counter" data-target="<?= (int) $value ?>">0</div>
                <div class="mt-1 text-sm text-slate-400"><?= e($label) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ LEADERBOARD MINGGUAN ============ -->
<?php if (!empty($weeklyTop)): ?>
<section class="max-w-7xl mx-auto px-6 mt-14">
    <div class="glass-card p-7 border-cyan-500/20 bg-gradient-to-r from-cyan-500/5 to-transparent" data-anim="fade-up">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <h2 class="font-display text-xl font-bold text-white">⚡ Top Pemain Minggu Ini</h2>
            <span class="text-[11px] text-slate-500">Top 3 tiap Senin 00:00 WIB menerima bonus mingguan otomatis 🎖️</span>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <?php foreach ($weeklyTop as $i => $lb): ?>
                <div class="flex items-center gap-4 p-4 rounded-xl bg-white/[.03] border border-white/5">
                    <span class="text-3xl"><?= ['🥇', '🥈', '🥉'][$i] ?? '🎮' ?></span>
                    <div class="min-w-0">
                        <p class="font-semibold text-white truncate"><?= e($lb['name']) ?></p>
                        <p class="text-xs text-neon-cyan">+<?= e(singkat((int) $lb['earned'])) ?> koin minggu ini</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ PENGUMUMAN ============ -->
<?php if (!empty($announcements)): ?>
<section class="max-w-7xl mx-auto px-6 mt-14 space-y-4">
    <?php foreach ($announcements as $a): ?>
        <div class="glass-card p-5 border-l-4 <?= $a['level'] === 'success' ? 'border-l-emerald-400' : ($a['level'] === 'warning' ? 'border-l-amber-400' : 'border-l-cyan-400') ?>" data-anim="fade-up">
            <h3 class="font-semibold text-white">📢 <?= e($a['title']) ?></h3>
            <p class="mt-1 text-sm text-slate-300"><?= e($a['body']) ?></p>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ============ FITUR UNGGULAN ============ -->
<section class="max-w-7xl mx-auto px-6 mt-20">
    <h2 class="font-display text-3xl sm:text-4xl font-bold text-center text-white" data-anim="fade-up">
        Kenapa <span class="text-neon-purple">ChiperX</span>?
    </h2>
    <div class="grid md:grid-cols-3 gap-6 mt-10">
        <?php
        $features = [
            ['🎰', 'Mini Games Harian', 'Tiga game seru — Gacha Spin, Mystery Box, dan Card Flip. 3 tiket gratis setiap hari, reset jam 00:00 WIB.', '/games'],
            ['🎁', 'Redeem Center', 'Koin hasil main bisa ditukar source code & project premium. Tanpa uang asli, murni hasil bermain.', '/redeem'],
            ['🛒', 'Store + Auto Payment', 'Produk premium dengan pembayaran otomatis QRIS & E-Wallet. Lunas → file langsung bisa diunduh.', '/store'],
        ];
        foreach ($features as [$icon, $title, $desc, $href]): ?>
            <a href="<?= e($href) ?>" class="glass-card p-7 group hover:border-violet-500/50 transition hover:-translate-y-1.5 duration-300" data-anim="fade-up">
                <div class="text-4xl group-hover:scale-125 transition duration-300 origin-left"><?= $icon ?></div>
                <h3 class="mt-4 font-display text-xl font-bold text-white"><?= e($title) ?></h3>
                <p class="mt-2 text-sm text-slate-400 leading-relaxed"><?= e($desc) ?></p>
                <span class="mt-4 inline-block text-sm text-neon-cyan group-hover:translate-x-1.5 transition">Jelajahi →</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ TESTIMONI ============ -->
<section id="testimoni" class="max-w-7xl mx-auto px-6 mt-20">
    <h2 class="font-display text-3xl font-bold text-center text-white" data-anim="fade-up">Kata <span class="text-neon-cyan">Komunitas</span></h2>
    <?php if (!empty($feedbacks)): ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-10">
            <?php foreach ($feedbacks as $fb): ?>
                <div class="glass-card p-6" data-anim="fade-up">
                    <div class="text-amber-300 text-sm tracking-wider"><?= str_repeat('★', (int) $fb['rating']) . str_repeat('☆', 5 - (int) $fb['rating']) ?></div>
                    <p class="mt-3 text-sm text-slate-300">“<?= e($fb['message']) ?>”</p>
                    <p class="mt-3 text-xs text-slate-500">— <?= e($fb['name']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="glass-card p-6 mt-8 max-w-xl mx-auto" data-anim="fade-up">
        <form method="post" action="/feedback" class="space-y-3">
            <?= csrf_field() ?>
            <h3 class="font-semibold text-white text-sm">Tinggalkan Ulasan ⭐</h3>
            <div class="grid grid-cols-2 gap-3">
                <input name="name" required maxlength="80" placeholder="Nama kamu" class="form-input">
                <select name="rating" class="form-input">
                    <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> Bintang</option><?php endfor; ?>
                </select>
            </div>
            <textarea name="message" required minlength="5" maxlength="1000" rows="2" placeholder="Ceritakan pengalamanmu..." class="form-input w-full"></textarea>
            <button class="w-full py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold hover:opacity-90 transition">Kirim Ulasan</button>
        </form>
    </div>
</section>

<!-- ============ CTA ============ -->
<section class="max-w-4xl mx-auto px-6 mt-24 text-center">
    <div class="glass-card p-10 bg-gradient-to-br from-violet-600/20 to-cyan-500/10" data-anim="zoom">
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-white">Siap Masuk ke <span class="glitch neon-text" data-text="CHIPERX">CHIPERX</span>?</h2>
        <p class="mt-3 text-slate-300">Login tanpa password — cukup email & kode OTP. Aman, cepat, gratis.</p>
        <a href="<?= is_logged_in() ? '/dashboard' : '/login' ?>" class="inline-block mt-6 px-8 py-3.5 rounded-xl bg-white text-slate-900 font-bold hover:scale-105 transition shadow-xl shadow-violet-600/30">
            <?= is_logged_in() ? 'Buka Dashboard →' : 'Masuk dengan Email OTP →' ?>
        </a>
    </div>
</section>

<!-- Three.js + GSAP + skrip landing -->
<script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="<?= asset('js/three-hero.js') ?>" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
