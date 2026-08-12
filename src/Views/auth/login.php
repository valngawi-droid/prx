<?php /** LOGIN — Username/Email + Password → link verifikasi email (tanpa ketik OTP) */ ?>
<section class="min-h-[80vh] grid place-items-center px-6 py-16">
    <div class="glass-card w-full max-w-md p-8 sm:p-10" data-anim="zoom">
        <div class="text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center font-display text-2xl font-bold text-white shadow-[0_0_35px_rgba(139,92,246,.5)]">CX</div>
            <h1 class="mt-5 font-display text-2xl font-bold text-white">Masuk ke ChiperX</h1>
            <p class="mt-2 text-sm text-slate-400">Username + password, lalu <b class="text-neon-cyan">klik tautan</b> yang kami kirim ke email Anda — tanpa mengetik kode.</p>
        </div>

        <form method="post" action="/auth/login" class="mt-8 space-y-5">
            <?= csrf_field() ?>
            <div>
                <label for="identity" class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Username atau Email</label>
                <input type="text" id="identity" name="identity" required autofocus autocomplete="username"
                       placeholder="username / nama@gmail.com" class="form-input w-full text-base">
            </div>
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••" class="form-input w-full text-base">
            </div>
            <button class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 hover:scale-[1.01] transition shadow-lg shadow-violet-600/30">
                Lanjut — Kirim Tautan Verifikasi 📧
            </button>
        </form>

        <div class="mt-6 flex items-center justify-between text-sm">
            <a href="/register" class="text-neon-purple hover:text-neon-cyan transition font-semibold">Daftar akun baru</a>
            <a href="/login/otp" class="text-slate-400 hover:text-white transition">Masuk via kode OTP</a>
        </div>

        <div class="mt-6 flex items-start gap-2.5 text-xs text-slate-500 bg-white/[.03] border border-white/10 rounded-xl p-4">
            <span class="text-base">🛡️</span>
            <p>Verifikasi dua langkah: password benar → kami kirim <b class="text-slate-300">tautan masuk</b> ke email. Anti brute-force aktif (5x salah = kunci 10 menit).</p>
        </div>
        <?php if (!empty($refCode)): ?>
            <div class="mt-3 flex items-start gap-2.5 text-xs text-violet-200 bg-violet-600/10 border border-violet-500/30 rounded-xl p-4">
                <span class="text-base">🎁</span>
                <p>Anda diundang melalui kode referral <b class="font-mono"><?= e($refCode) ?></b> — daftar dulu agar pengundang menerima bonusnya!</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
