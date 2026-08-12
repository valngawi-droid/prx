<?php /** REDEEM CENTER — tukar koin dengan file/project */ ?>
<section class="max-w-7xl mx-auto px-6 py-16">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-4xl sm:text-5xl font-bold text-white">🎁 Redeem <span class="text-neon-cyan">Center</span></h1>
        <p class="mt-3 text-slate-400">Tukarkan ChiperX Coin hasil mainmu dengan project eksklusif.</p>
        <div class="mt-5 inline-flex items-center gap-2 glass-card px-5 py-2.5">
            🪙 <span class="text-slate-400 text-sm">Koin Anda:</span>
            <b class="text-amber-300 text-xl"><?= e(number_format((int) $user['coin_balance'])) ?></b>
        </div>

        <!-- 🎟️ KLAIM KODE KUSTOM -->
        <form method="post" action="/redeem-code" class="mt-6 glass-card max-w-md mx-auto p-4 flex gap-2 items-center" data-anim="fade-up">
            <?= csrf_field() ?>
            <span class="text-lg">🎟️</span>
            <input name="code" maxlength="40" required placeholder="Punya kode? Ketik di sini…" class="form-input flex-1 text-sm font-mono uppercase py-2.5">
            <button class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition">Klaim</button>
        </form>
    </header>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-12">
        <?php if (empty($products)): ?>
            <p class="sm:col-span-2 lg:col-span-3 glass-card p-10 text-center text-slate-500">Belum ada item redeem — admin sedang menyiapkannya ✨</p>
        <?php endif; ?>
        <?php foreach ($products as $p): ?>
            <article class="glass-card p-6 flex flex-col group hover:border-violet-500/50 hover:-translate-y-1.5 transition duration-300" data-anim="fade-up">
                <div class="h-36 rounded-xl bg-gradient-to-br from-violet-800/40 to-cyan-800/30 grid place-items-center text-5xl group-hover:scale-[1.02] transition">
                    <?= $p['is_featured'] ? '💎' : '📦' ?>
                </div>
                <?php if ($p['is_featured']): ?>
                    <span class="mt-3 w-fit text-[10px] font-bold tracking-widest px-2.5 py-1 rounded-full bg-amber-400/15 text-amber-300 border border-amber-400/30">FEATURED</span>
                <?php endif; ?>
                <h3 class="mt-3 font-display text-lg font-bold text-white"><?= e($p['name']) ?></h3>
                <p class="mt-2 text-sm text-slate-400 flex-1"><?= e($p['description'] ?? '') ?></p>
                <div class="mt-4 flex items-center justify-between">
                    <span class="font-display font-bold text-amber-300 flex items-center gap-1.5">🪙 <?= e(number_format((int) $p['price'])) ?></span>
                    <span class="text-xs <?= $p['stock'] === null ? 'text-slate-600' : ((int) $p['stock'] > 0 ? 'text-emerald-400' : 'text-red-400') ?>">
                        <?= $p['stock'] === null ? 'Stok ∞' : 'Stok: ' . (int) $p['stock'] ?>
                    </span>
                </div>
                <form method="post" action="/redeem/<?= (int) $p['id'] ?>" class="mt-4" onsubmit="return confirm('Tukar <?= (int) $p['price'] ?> koin untuk item ini?')">
                    <?= csrf_field() ?>
                    <?php $afford = (int) $user['coin_balance'] >= (int) $p['price'] && ((int) ($p['stock'] ?? 1) !== 0); ?>
                    <button <?= $afford ? '' : 'disabled' ?>
                            class="w-full py-3 rounded-xl font-semibold text-sm transition
                                <?= $afford ? 'bg-gradient-to-r from-violet-600 to-cyan-500 text-white hover:opacity-90 shadow-lg shadow-violet-600/25' : 'bg-white/5 text-slate-600 cursor-not-allowed' ?>">
                        <?= $afford ? 'Tukar Sekarang ⚡' : ((int) ($p['stock'] ?? 1) === 0 ? 'Stok Habis' : 'Koin Kurang 😢') ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
