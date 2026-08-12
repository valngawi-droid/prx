<?php /** LOGIN via kode OTP (jalur alternatif tanpa password) */ ?>
<section class="min-h-[80vh] grid place-items-center px-6 py-16">
    <div class="glass-card w-full max-w-md p-8 sm:p-10" data-anim="zoom">
        <div class="text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center font-display text-2xl font-bold text-white shadow-[0_0_35px_rgba(139,92,246,.5)]">📨</div>
            <h1 class="mt-5 font-display text-2xl font-bold text-white">Masuk via Kode OTP</h1>
            <p class="mt-2 text-sm text-slate-400">Lupa/belum mengatur password? Kami kirim <b class="text-neon-cyan">kode 6 digit</b> ke email Anda.</p>
        </div>
        <form method="post" action="/auth/otp/send" class="mt-8 space-y-5">
            <?= csrf_field() ?>
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Alamat Email</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="email"
                       placeholder="nama@gmail.com" class="form-input w-full text-base">
            </div>
            <button class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">
                Kirim Kode OTP →
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-400">Ingat password? <a href="/login" class="text-neon-cyan hover:text-white transition font-semibold">Login biasa</a></p>
    </div>
</section>
