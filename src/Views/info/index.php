<?php /** HALAMAN INFORMASI: timeline sejarah + visi misi + changelog */ ?>
<section class="max-w-5xl mx-auto px-6 py-20">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-4xl sm:text-5xl font-bold text-white">
            Tentang <span class="glitch neon-text" data-text="CHIPERX">CHIPERX</span>
        </h1>
        <p class="mt-4 text-slate-400 max-w-2xl mx-auto">Komunitas digital yang lahir dari semangat berbagi — berevolusi menjadi platform all-in-one: games, reward, dan store.</p>
    </header>

    <!-- VISI MISI -->
    <div class="grid md:grid-cols-2 gap-6 mt-14">
        <div class="glass-card p-7 border-t-2 border-t-violet-500" data-anim="fade-up">
            <h2 class="font-display text-xl font-bold text-neon-purple">🎯 Visi</h2>
            <p class="mt-3 text-sm text-slate-300 leading-relaxed">Menjadi ekosistem digital komunitas #1 di Indonesia — tempat di mana setiap anggota bisa bermain, belajar, dan mendapatkan aset digital berkualitas tanpa penghalang biaya.</p>
        </div>
        <div class="glass-card p-7 border-t-2 border-t-cyan-400" data-anim="fade-up">
            <h2 class="font-display text-xl font-bold text-neon-cyan">🚀 Misi</h2>
            <ul class="mt-3 text-sm text-slate-300 space-y-2 list-disc list-inside">
                <li>Membagikan source code & project berkualitas lewat sistem reward koin.</li>
                <li>Menghadirkan pengalaman bermain yang adil dengan RTP transparan.</li>
                <li>Membangun komunitas yang suportif melalui Discord & Telegram.</li>
                <li>Terus merilis fitur baru setiap versi (lihat changelog di bawah).</li>
            </ul>
        </div>
    </div>

    <!-- TIMELINE SEJARAH -->
    <h2 class="font-display text-2xl font-bold text-white mt-20 text-center" data-anim="fade-up">📜 Timeline Perjalanan</h2>
    <div class="relative mt-12 pl-8 md:pl-0">
        <div class="absolute left-3 md:left-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-violet-500 via-cyan-400 to-transparent"></div>
        <?php
        $timeline = [
            ['2025 — Q1', 'Kelahiran ChiperX', 'Berawal dari grup sharing script kecil di Discord. Anggota pertama: 50 orang.'],
            ['2025 — Q2', 'Website v1 Rilis', 'Web resmi pertama dengan sistem login OTP dan halaman All Link.'],
            ['2025 — Q3', 'Era Mini Games', 'ChiperX Coin diperkenalkan. Member bisa mendapatkan project hanya dengan bermain.'],
            ['2026 — Kini', 'ChiperX 2.0 Enterprise', 'Store premium, auto-payment QRIS/E-Wallet, Redeem Center, dan Owner God Mode.'],
        ];
        foreach ($timeline as $i => [$era, $title, $desc]): ?>
            <div class="relative md:grid md:grid-cols-2 md:gap-12 mb-10" data-anim="fade-up">
                <div class="absolute -left-8 md:left-1/2 md:-translate-x-1/2 top-1 w-4 h-4 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 shadow-[0_0_15px_rgba(139,92,246,.8)]"></div>
                <div class="<?= $i % 2 === 0 ? 'md:text-right md:pr-12' : 'md:col-start-2 md:pl-12' ?> glass-card p-6">
                    <span class="text-xs font-bold tracking-widest text-neon-cyan"><?= e($era) ?></span>
                    <h3 class="mt-1 font-display text-lg font-bold text-white"><?= e($title) ?></h3>
                    <p class="mt-2 text-sm text-slate-400"><?= e($desc) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- CHANGELOG -->
    <h2 class="font-display text-2xl font-bold text-white mt-16 text-center" data-anim="fade-up">🧩 Changelog Website</h2>
    <div class="glass-card mt-8 overflow-hidden" data-anim="fade-up">
        <?php if (empty($changelogs)): ?>
            <p class="p-6 text-sm text-slate-500 text-center">Belum ada catatan rilis.</p>
        <?php endif; ?>
        <?php foreach ($changelogs as $c): ?>
            <div class="flex gap-4 p-5 border-b border-white/5 last:border-0 hover:bg-white/[.03] transition">
                <span class="shrink-0 h-fit px-3 py-1 rounded-lg bg-violet-600/20 border border-violet-500/30 text-neon-purple text-xs font-bold font-mono"><?= e($c['version']) ?></span>
                <div>
                    <h3 class="font-semibold text-white text-sm"><?= e($c['title']) ?></h3>
                    <p class="text-sm text-slate-400 mt-1"><?= e($c['body']) ?></p>
                    <p class="text-xs text-slate-600 mt-1.5">📅 <?= e(date('d M Y', strtotime($c['released_at']))) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
