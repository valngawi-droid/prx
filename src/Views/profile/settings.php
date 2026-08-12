<?php /** PENGATURAN AKUN — pusat kendali: username, password, info akun */ ?>
<section class="max-w-2xl mx-auto space-y-6">
    <header data-anim="fade-up">
        <h1 class="font-display text-2xl font-bold text-white">⚙️ Pengaturan Akun</h1>
        <p class="text-xs text-slate-500 mt-1">Kelola identitas login & keamanan akunmu di sini.</p>
    </header>

    <!-- Info akun ringkas -->
    <div class="glass-card p-5 flex items-center gap-4" data-anim="zoom">
        <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center font-display font-bold text-white text-lg overflow-hidden shrink-0">
            <?php if (!empty($user['avatar'])): ?><img src="/media/avatar/<?= e((string) $user['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $user['name'], 0, 1))) ?><?php endif; ?>
        </span>
        <div class="min-w-0">
            <p class="font-bold text-white truncate flex items-center gap-2"><?= e($user['name']) ?> <?= user_badges($user) ?></p>
            <p class="text-xs text-slate-500 truncate">@<?= e($user['username'] ?? '—') ?> · <?= e($user['email']) ?></p>
        </div>
        <span class="ml-auto text-[10px] px-2.5 py-1 rounded-full bg-white/5 border border-white/10 text-slate-400 uppercase tracking-wider"><?= e($user['role']) ?></span>
    </div>

    <!-- Ganti username -->
    <div class="glass-card p-6" data-anim="fade-up">
        <h2 class="font-display font-bold text-white mb-1">🏷️ Username</h2>
        <p class="text-[11px] text-slate-500 mb-4">Username dipakai untuk url profil <span class="text-slate-400">/profil/@username</span> dan DM. Huruf kecil, angka, titik, underscore (3-20 karakter).</p>
        <form method="post" action="/pengaturan/username" class="flex flex-col sm:flex-row gap-3">
            <?= csrf_field() ?>
            <input name="username" value="<?= e((string) ($user['username'] ?? '')) ?>" maxlength="20" pattern="[a-z0-9_.]{3,20}" required class="form-input flex-1 text-sm font-mono" placeholder="username_baru">
            <button class="px-6 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition shrink-0">Simpan ✓</button>
        </form>
    </div>

    <!-- Ganti password -->
    <div class="glass-card p-6" data-anim="fade-up">
        <h2 class="font-display font-bold text-white mb-1">🔐 Password</h2>
        <p class="text-[11px] text-slate-500 mb-4"><?= $hasPassword ? 'Masukkan password lama dulu demi keamanan.' : 'Akunmu belum punya password (login via email/OTP) — buat sekarang biar bisa login cepat!' ?></p>
        <form method="post" action="/pengaturan/password" class="space-y-3">
            <?= csrf_field() ?>
            <?php if ($hasPassword): ?>
                <input type="password" name="current_password" required maxlength="100" placeholder="Password lama" class="form-input w-full text-sm">
            <?php endif; ?>
            <input type="password" name="password" required minlength="8" maxlength="100" placeholder="Password baru (min. 8 karakter)" class="form-input w-full text-sm">
            <input type="password" name="password_confirm" required minlength="8" maxlength="100" placeholder="Ulangi password baru" class="form-input w-full text-sm">
            <button class="px-6 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-500 text-white text-sm font-bold hover:opacity-90 transition">Ganti Password 🔐</button>
        </form>
    </div>

    <p class="text-center text-[11px] text-slate-600">💡 Avatar, sampul & warna aksen diatur di <a href="/profil" class="text-neon-cyan hover:underline">halaman Profil</a> (bagian Tampilan Profil).</p>
</section>
