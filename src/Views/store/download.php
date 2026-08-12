<?php /** AKSES FILE — reveal URL eksternal setelah transaksi PAID */ ?>
<section class="max-w-xl mx-auto px-6 py-20">
    <div class="glass-card p-10 text-center" data-anim="zoom">
        <div class="text-6xl">🔓</div>
        <h1 class="mt-5 font-display text-2xl font-bold text-white">Akses Terbuka!</h1>
        <p class="mt-2 text-sm text-slate-400">Anda memiliki akses penuh untuk:</p>
        <p class="mt-1 font-semibold text-neon-cyan"><?= e($tx['product_name']) ?></p>

        <div class="mt-8 space-y-3">
            <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener noreferrer nofollow"
               class="block w-full py-4 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 hover:scale-[1.01] transition shadow-lg shadow-violet-600/30">
                ⬇️ Buka / Unduh File
            </a>
            <button onclick="navigator.clipboard.writeText('<?= e($fileUrl) ?>').then(()=>{this.textContent='✅ Link tersalin!'})"
                    class="w-full py-3 rounded-xl bg-white/5 border border-white/10 text-sm hover:bg-white/10 transition">
                📋 Salin Link
            </button>
            <p class="text-[11px] text-slate-600 pt-2">⚠️ Jangan bagikan link ini — hak akses terikat pada akun Anda.</p>
        </div>
        <a href="/dashboard" class="inline-block mt-8 text-sm text-slate-400 hover:text-neon-cyan transition">← Kembali ke Dashboard</a>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
