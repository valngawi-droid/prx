<?php /** GERBANG RAHASIA — verifikasi lapis kedua panel admin/owner */ ?>
<section class="min-h-[80vh] grid place-items-center px-6 py-16">
    <div class="glass-card w-full max-w-md p-8 sm:p-10" data-anim="zoom">
        <div class="text-center">
            <div class="text-4xl mb-3">🛡️</div>
            <h1 class="font-display text-2xl font-bold text-white">Area Terbatas</h1>
            <p class="mt-2 text-sm text-slate-400">Verifikasi lapis kedua untuk membuka panel.<br>Seluruh percobaan <b class="text-neon-purple">dicatat & diawasi</b>.</p>
            <?php if (($lockedFor ?? 0) > 0): ?>
                <p class="mt-3 inline-block rounded-lg bg-red-500/15 border border-red-500/40 px-4 py-2 text-sm text-red-300">
                    🔒 Terkunci — coba lagi dalam <b id="lockCd" data-sisa="<?= (int) $lockedFor ?>"><?= (int) $lockedFor ?></b> detik
                </p>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= \ChiperX\Services\GateService::PATH ?>" class="mt-8 space-y-4" <?= (($lockedFor ?? 0) > 0) ? 'inert' : '' ?>>
            <?= csrf_field() ?>
            <div>
                <label class="text-xs text-slate-400 uppercase tracking-wider">Username</label>
                <input name="username" autocomplete="off" required maxlength="60"
                       class="form-input w-full text-sm mt-1" placeholder="••••••">
            </div>
            <div>
                <label class="text-xs text-slate-400 uppercase tracking-wider">Password</label>
                <input name="password" type="password" autocomplete="off" required maxlength="200"
                       class="form-input w-full text-sm mt-1" placeholder="••••••••">
            </div>
            <button class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">
                Buka Akses 🔓
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-600">
            <span class="text-red-400">⚠</span> 5x salah = IP dikunci 10 menit · semua log masuk audit & Discord
        </p>
    </div>
</section>

<script>
    // Hitung mundur status kunci (real-time, detik)
    (function () {
        const el = document.getElementById('lockCd');
        if (!el) return;
        let sisa = parseInt(el.dataset.sisa || '0', 10);
        const t = setInterval(() => {
            sisa--;
            if (sisa <= 0) { clearInterval(t); window.location.reload(); return; }
            el.textContent = sisa;
        }, 1000);
    })();
</script>
