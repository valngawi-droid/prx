<?php /** CEK EMAIL — menunggu klik tautan masuk (polling status real-time) */ ?>
<section class="min-h-[80vh] grid place-items-center px-6 py-16">
    <div class="glass-card w-full max-w-md p-8 sm:p-10 text-center" data-anim="zoom">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center text-3xl shadow-[0_0_35px_rgba(139,92,246,.5)] animate-pulse">📬</div>
        <h1 class="mt-5 font-display text-2xl font-bold text-white">Cek Email Anda</h1>
        <p class="mt-2 text-sm text-slate-400">Tautan masuk telah dikirim ke<br><b class="text-neon-cyan"><?= e($email ?? '') ?></b></p>
        <p class="mt-3 text-xs text-slate-500">Berlaku <b class="text-neon-purple">10 menit</b> · sekali pakai · tanpa perlu mengetik kode.</p>

        <div class="mt-8 rounded-xl bg-white/[.03] border border-white/10 p-4 text-xs text-slate-400">
            <span id="statusIcon" class="inline-block animate-spin">⏳</span>
            <span id="statusText">Menunggu Anda mengklik tautan di email…</span>
        </div>
        <p class="mt-4 text-[11px] text-slate-600">Halaman ini <b>otomatis melanjutkan</b> begitu tautan diklik — biarkan terbuka. Buka email di tab/aplikasi lain.</p>

        <form method="post" action="/auth/login/resend" class="mt-6">
            <?= csrf_field() ?>
            <button id="resendBtn" <?= ($cooldown ?? 0) > 0 ? 'disabled' : '' ?>
                    class="text-sm font-semibold text-neon-purple hover:text-neon-cyan transition disabled:text-slate-600 disabled:cursor-not-allowed">
                Kirim ulang tautan <span id="cd"></span>
            </button>
        </form>
        <p class="mt-4 text-xs text-slate-600">Salah akun? <a href="/login" class="text-slate-400 hover:text-white">← Kembali login</a></p>
    </div>
</section>
<script>
    (function () {
        // Polling status: ketika tautan diklik (tab/aplikasi lain), sesi ini ikut login
        const poll = setInterval(async () => {
            try {
                const r = await fetch('/auth/login-status', { headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                if (j.logged_in) {
                    clearInterval(poll);
                    document.getElementById('statusIcon').textContent = '✅';
                    document.getElementById('statusText').textContent = 'Verifikasi diterima! Mengalihkan…';
                    window.location.href = j.redirect || '/dashboard';
                }
            } catch (e) { /* jaringan lambat — coba lagi siklus berikutnya */ }
        }, 3000);

        // Countdown kirim ulang
        let cd = <?= (int) ($cooldown ?? 0) ?>;
        const btn = document.getElementById('resendBtn');
        const lbl = document.getElementById('cd');
        if (cd > 0) {
            const t = setInterval(() => {
                cd--;
                lbl.textContent = cd > 0 ? `(${cd}s)` : '';
                if (cd <= 0) { clearInterval(t); btn.disabled = false; }
            }, 1000);
            lbl.textContent = `(${cd}s)`;
        }
    })();
</script>
