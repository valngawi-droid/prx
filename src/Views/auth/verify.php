<?php /** VERIFIKASI OTP — 6 kotak input + resend cooldown 60 detik */ ?>
<section class="min-h-[80vh] grid place-items-center px-6 py-16">
    <div class="glass-card w-full max-w-md p-8 sm:p-10" data-anim="zoom">
        <div class="text-center">
            <h1 class="font-display text-2xl font-bold text-white">Cek Email Anda 📬</h1>
            <p class="mt-2 text-sm text-slate-400">Kode 6 digit dikirim ke<br><b class="text-neon-cyan"><?= e($email) ?></b></p>
            <p class="mt-1 text-xs text-slate-500">Berlaku <b class="text-neon-purple"><?= \ChiperX\Services\OtpService::TTL_MINUTES ?> menit</b> (WIB)</p>
            <?php if (($ttlLeft ?? 0) > 0): ?>
            <p class="mt-2 inline-block rounded-lg bg-slate-800/70 border border-white/10 px-3 py-1 text-xs">
                ⏳ Sisa waktu: <b id="otpCountdown" class="text-neon-cyan font-mono">--:--</b>
            </p>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e($action ?? '/auth/otp/verify') ?>" id="otpForm" class="mt-8">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?= e($email) ?>"><?php /* penolong jika isi sesi hilang di tengah jalan */ ?>
            <div class="flex justify-center gap-2.5" id="otpBoxes">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" name="otp_<?= $i ?>" <?= $i === 1 ? 'autofocus' : '' ?>
                           class="otp-box w-11 h-14 sm:w-12 sm:h-16 text-center text-2xl font-bold rounded-xl bg-slate-800/80 border border-white/15 text-neon-cyan
                                  focus:border-neon-cyan focus:ring-2 focus:ring-cyan-400/40 focus:outline-none transition shadow-inner">
                <?php endfor; ?>
            </div>
            <button class="mt-7 w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">
                Verifikasi & Masuk ✓
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-slate-400">
            Tidak menerima kode?
            <form method="post" action="<?= e($resendAction ?? '/auth/otp/resend') ?>" class="inline" id="resendForm">
                <?= csrf_field() ?>
                <input type="hidden" name="email" value="<?= e($email) ?>">
                <button id="resendBtn" <?= $cooldown > 0 ? 'disabled' : '' ?>
                        class="font-semibold text-neon-purple hover:text-neon-cyan transition disabled:text-slate-600 disabled:cursor-not-allowed">
                    Kirim Ulang <span id="cooldownText"><?= $cooldown > 0 ? '(' . (int) $cooldown . 's)' : '' ?></span>
                </button>
            </form>
        </div>
        <p class="mt-4 text-center text-xs text-slate-600">Salah email? <a href="/login" class="text-slate-400 hover:text-white">← Ganti email</a></p>
    </div>
</section>

<script>
    // ===== Interaksi 6 kotak OTP: auto-next, backspace, paste, countdown =====
    const boxes = [...document.querySelectorAll('.otp-box')];
    boxes.forEach((box, i) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '');
            if (box.value && i < 5) boxes[i + 1].focus();
            if (boxes.every(b => b.value)) document.getElementById('otpForm').requestSubmit();
        });
        box.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
        });
        box.addEventListener('paste', e => {
            e.preventDefault();
            const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            digits.split('').forEach((d, j) => { if (boxes[j]) boxes[j].value = d; });
            if (digits.length === 6) document.getElementById('otpForm').requestSubmit();
            else boxes[Math.min(digits.length, 5)].focus();
        });
    });

    // ===== Hitung mundur masa berlaku OTP (real-time, dihitung dari sisa detik server) =====
    let ttlLeft = <?= (int) ($ttlLeft ?? 0) ?>;
    const ttlEl = document.getElementById('otpCountdown');
    const fmt = s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    if (ttlEl) {
        ttlEl.textContent = fmt(ttlLeft);
        const otpTimer = setInterval(() => {
            ttlLeft--;
            if (ttlLeft <= 0) {
                clearInterval(otpTimer);
                ttlEl.textContent = '00:00';
                ttlEl.classList.replace('text-neon-cyan', 'text-red-400');
                ttlEl.parentElement.innerHTML = '⌛ Kode sudah kedaluwarsa — <b class="text-neon-purple">minta kode baru</b> di bawah.';
                return;
            }
            ttlEl.textContent = fmt(ttlLeft);
            if (ttlLeft <= 60) ttlEl.classList.add('text-amber-400'); // peringatan menit terakhir
        }, 1000);
    }

    // ===== Countdown kirim ulang =====
    let cooldown = <?= (int) $cooldown ?>;
    const btn = document.getElementById('resendBtn');
    const txt = document.getElementById('cooldownText');
    const timer = setInterval(() => {
        if (cooldown <= 0) { clearInterval(timer); btn.disabled = false; txt.textContent = ''; return; }
        txt.textContent = `(${cooldown}s)`;
        cooldown--;
    }, 1000);
</script>
