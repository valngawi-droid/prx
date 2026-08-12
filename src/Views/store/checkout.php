<?php /** HALAMAN CHECKOUT — instruksi bayar + auto-polling status */ ?>
<section class="max-w-2xl mx-auto px-6 py-16">
    <div class="glass-card p-8" data-anim="zoom">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-xl font-bold text-white">Checkout</h1>
            <span id="statusBadge" class="px-3 py-1.5 rounded-full text-xs font-bold
                <?= $tx['status'] === 'paid' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/40' : 'bg-amber-500/20 text-amber-300 border border-amber-400/40' ?>">
                <?= strtoupper(e($tx['status'])) ?>
            </span>
        </div>
        <p class="mt-1 text-xs text-slate-500 font-mono">REF: <?= e($tx['gateway_ref']) ?></p>

        <div class="mt-6 rounded-xl bg-white/[.03] border border-white/10 p-5 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-400">Produk</span><b class="text-white"><?= e($tx['product_name']) ?></b></div>
            <div class="flex justify-between"><span class="text-slate-400">Metode</span><b class="text-neon-purple"><?= e(strtoupper($tx['payment_method'])) ?></b></div>
            <div class="flex justify-between text-lg"><span class="text-slate-400">Total Bayar</span><b class="text-neon-cyan font-display"><?= e(rupiah((int) $tx['amount'])) ?></b></div>
            <?php if ($tx['expired_at']): ?>
                <div class="flex justify-between text-xs"><span class="text-slate-500">Bayar sebelum</span><span class="text-red-400"><?= e($tx['expired_at']) ?> WIB</span></div>
            <?php endif; ?>
        </div>

        <?php if ($tx['status'] === 'pending'): ?>
            <div class="mt-6 text-center space-y-4">
                <?php if (!empty($tx['qr_url'])): ?>
                    <div class="glass-card p-4 inline-block bg-white">
                        <img src="<?= e($tx['qr_url']) ?>" alt="QRIS" class="w-52 h-52 object-contain">
                    </div>
                    <p class="text-xs text-slate-500">Scan QR dengan aplikasi e-wallet / m-banking apa pun</p>
                <?php endif; ?>
                <?php if (!empty($tx['checkout_url'])): ?>
                    <a href="<?= e($tx['checkout_url']) ?>" target="_blank" rel="noopener"
                       class="block w-full py-3.5 rounded-xl bg-gradient-to-r from-cyan-500 to-violet-600 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-cyan-600/30">
                        💳 Buka Halaman Pembayaran
                    </a>
                <?php endif; ?>
                <p id="waitingText" class="text-sm text-slate-400 animate-pulse">⏳ Menunggu pembayaran… halaman ini diperbarui otomatis</p>
                <form method="post" action="/store/cancel/<?= e(rawurlencode($tx['gateway_ref'])) ?>" onsubmit="return confirm('Batalkan transaksi ini?')">
                    <?= csrf_field() ?>
                    <button class="text-xs text-red-400/70 hover:text-red-400">Batalkan transaksi</button>
                </form>
            </div>
        <?php elseif ($tx['status'] === 'paid'): ?>
            <div class="mt-6 text-center">
                <div class="text-5xl">✅</div>
                <p class="mt-3 font-semibold text-emerald-300">Pembayaran diterima!</p>
                <a href="/download/<?= (int) $tx['id'] ?>" class="inline-block mt-4 px-8 py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-emerald-600/30">
                    ⬇️ Unduh File Sekarang
                </a>
            </div>
        <?php else: ?>
            <div class="mt-6 text-center">
                <div class="text-4xl">❌</div>
                <p class="mt-2 text-sm text-slate-400">Transaksi <?= e($tx['status']) ?>. Silakan buat pesanan baru.</p>
                <a href="/store" class="inline-block mt-4 text-neon-cyan text-sm hover:underline">← Kembali ke Store</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($tx['status'] === 'pending'): ?>
<script>
    // Polling status setiap 5 detik — berhenti setelah PAID / 5 menit
    let tries = 0;
    const poll = setInterval(async () => {
        if (++tries > 60) { clearInterval(poll); return; }
        try {
            const res = await fetch('/store/status/<?= e(rawurlencode($tx['gateway_ref'])) ?>', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.ok && data.status !== 'pending') {
                clearInterval(poll);
                window.location.reload();
            }
        } catch (e) { /* abaikan, coba lagi */ }
    }, 5000);
</script>
<?php endif; ?>
