<?php /** CHIPERX STORE — produk premium + pilihan metode pembayaran */ ?>
<section class="max-w-7xl mx-auto px-6 py-16">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-4xl sm:text-5xl font-bold text-white">🛒 ChiperX <span class="text-neon-purple">Store</span></h1>
        <p class="mt-3 text-slate-400">Produk premium dengan <b class="text-neon-cyan">auto-payment</b>: QRIS & E-Wallet. Lunas → file langsung terbuka.</p>
    </header>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-12">
        <?php if (empty($products)): ?>
            <p class="sm:col-span-2 lg:col-span-3 glass-card p-10 text-center text-slate-500">Produk premium segera hadir 🚀</p>
        <?php endif; ?>
        <?php foreach ($products as $p): ?>
            <article class="glass-card p-6 flex flex-col group hover:border-cyan-500/50 hover:-translate-y-1.5 transition duration-300 relative overflow-hidden" data-anim="fade-up">
                <?php if ($p['is_featured']): ?>
                    <span class="absolute top-4 -right-9 rotate-45 bg-gradient-to-r from-amber-400 to-orange-500 text-[10px] font-bold text-slate-900 px-10 py-1 shadow-lg">PREMIUM</span>
                <?php endif; ?>
                <div class="h-36 rounded-xl bg-gradient-to-br from-cyan-800/40 to-violet-800/30 grid place-items-center text-5xl group-hover:scale-[1.02] transition">💠</div>
                <h3 class="mt-4 font-display text-lg font-bold text-white"><?= e($p['name']) ?></h3>
                <p class="mt-2 text-sm text-slate-400 flex-1"><?= e($p['description'] ?? '') ?></p>
                <div class="mt-4 flex items-center justify-between">
                    <span class="font-display text-2xl font-bold text-neon-cyan"><?= e(rupiah((int) $p['price'])) ?></span>
                    <span class="text-xs <?= $p['stock'] === null ? 'text-slate-600' : ((int) $p['stock'] > 0 ? 'text-emerald-400' : 'text-red-400') ?>">
                        <?= $p['stock'] === null ? 'Stok ∞' : 'Sisa: ' . (int) $p['stock'] ?>
                    </span>
                </div>
                <form method="post" action="/store/buy/<?= (int) $p['id'] ?>" class="mt-4 space-y-3">
                    <?= csrf_field() ?>
                    <select name="method" class="form-input w-full text-sm" <?= ((int) ($p['stock'] ?? 1) === 0) ? 'disabled' : '' ?>>
                        <?php foreach ($methods as $code => $label): ?>
                            <option value="<?= e($code) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button <?= ((int) ($p['stock'] ?? 1) === 0) ? 'disabled' : '' ?>
                            class="w-full py-3 rounded-xl font-semibold text-sm bg-gradient-to-r from-cyan-500 to-violet-600 text-white hover:opacity-90 transition shadow-lg shadow-cyan-600/25 disabled:opacity-40 disabled:cursor-not-allowed">
                        <?= ((int) ($p['stock'] ?? 1) === 0) ? 'Stok Habis' : 'Beli Sekarang 💳' ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="glass-card max-w-2xl mx-auto mt-14 p-6 text-sm text-slate-400" data-anim="fade-up">
        <h3 class="font-semibold text-white mb-2">⚡ Cara Kerja Auto-Payment</h3>
        <ol class="list-decimal list-inside space-y-1.5">
            <li>Klik <b>Beli</b> → pilih metode (QRIS / E-Wallet).</li>
            <li>Bayar sesuai instruksi sebelum batas waktu.</li>
            <li>Payment gateway mengirim <b>webhook</b> ke server kami.</li>
            <li>Status otomatis <b class="text-emerald-300">PAID</b> → unduh file kapan saja di Dashboard.</li>
        </ol>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
