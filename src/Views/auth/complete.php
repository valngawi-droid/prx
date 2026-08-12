<?php /** LENGKAPI AKUN — username + password (register final / akun lama tanpa password) */ ?>
<section class="min-h-[80vh] grid place-items-center px-6 py-16">
    <div class="glass-card w-full max-w-md p-8 sm:p-10" data-anim="zoom">
        <div class="text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center font-display text-2xl font-bold text-white shadow-[0_0_35px_rgba(139,92,246,.5)]">🔑</div>
            <h1 class="mt-5 font-display text-2xl font-bold text-white"><?= e($heading ?? 'Atur Username & Password') ?></h1>
            <p class="mt-2 text-sm text-slate-400"><?= e($intro ?? 'Terakhir — buat kredensial untuk login cepat berikutnya.') ?><?php if (!empty($emailCtx)): ?><br><b class="text-neon-cyan"><?= e($emailCtx) ?></b><?php endif; ?></p>
        </div>

        <form method="post" action="<?= e($action ?? '/auth/register/complete') ?>" class="mt-8 space-y-5">
            <?= csrf_field() ?>
            <div>
                <label for="username" class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Username</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="40"
                       pattern="[A-Za-z0-9_.]{3,40}" autocomplete="username" autofocus
                       placeholder="contoh: chiper_ganteng" class="form-input w-full text-base">
                <p class="mt-1.5 text-[11px] text-slate-500">3–40 karakter: huruf, angka, titik, garis bawah. Huruf kecil otomatis.</p>
            </div>
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Password</label>
                <input type="password" id="password" name="password" required minlength="6" maxlength="200"
                       autocomplete="new-password" placeholder="minimal 6 karakter" class="form-input w-full text-base">
            </div>
            <div>
                <label for="password2" class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Ulangi Password</label>
                <input type="password" id="password2" name="password2" required minlength="6" maxlength="200"
                       autocomplete="new-password" placeholder="sama persis seperti di atas" class="form-input w-full text-base">
                <p id="pwHint" class="mt-1.5 text-[11px] text-slate-500 transition"></p>
            </div>
            <button id="submitBtn" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30 disabled:opacity-40 disabled:cursor-not-allowed">
                Simpan & Selesai ✓
            </button>
        </form>
    </div>
</section>
<script>
    // Validasi konfirmasi password real-time
    (function () {
        const p1 = document.getElementById('password');
        const p2 = document.getElementById('password2');
        const hint = document.getElementById('pwHint');
        const btn = document.getElementById('submitBtn');
        function cek() {
            if (!p2.value) { hint.textContent = ''; btn.disabled = false; return; }
            if (p1.value === p2.value) { hint.textContent = '✓ Password cocok'; hint.style.color = '#4ade80'; btn.disabled = false; }
            else { hint.textContent = '✗ Belum sama'; hint.style.color = '#f87171'; btn.disabled = true; }
        }
        p1.addEventListener('input', cek);
        p2.addEventListener('input', cek);
    })();
</script>
