<?php /** PROFIL — identitas + tag role beranimasi + bio + statistik + grid postingan (ala Instagram) */ ?>
<?php
$t        = $target;
$inisial  = strtoupper(mb_substr($t['name'] ?? 'U', 0, 1));
$roleEmoji = ['owner' => '👑', 'admin' => '🛡️', 'user' => '🎮'][$t['role'] ?? 'user'];
$tuser    = rawurlencode((string) ($t['username'] ?? ''));
$bio      = trim((string) ($t['bio'] ?? ''));
$vip      = user_is_vip($t);
$online   = online_label($t['last_activity'] ?? null);
$hasStory = (int) ($storyCount ?? 0) > 0;
?>
<section class="px-4 sm:px-6 py-10 sm:py-14">
    <div class="max-w-3xl mx-auto space-y-6">

        <!-- Kartu identitas -->
        <div class="glass-card p-6 sm:p-10 relative overflow-hidden" data-anim="zoom">
            <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-violet-700/20 blur-[90px] pointer-events-none"></div>
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative shrink-0">
                    <?php
                    $avatarOpen  = $hasStory ? '<a href="/story/' . e($tuser) . '" title="Lihat Story" class="block rounded-[1.6rem] p-[3px] bg-gradient-to-tr from-violet-500 via-fuchsia-500 to-cyan-400 shadow-[0_0_24px_rgba(168,85,247,.5)] cursor-pointer hover:scale-105 transition">' : '<div class="block rounded-[1.6rem]">';
                    $avatarClose = $hasStory ? '</a>' : '</div>';
                    $avatarCls   = $vip ? 'ring-2 ring-amber-400 shadow-[0_0_45px_rgba(251,191,36,.5)]' : 'shadow-[0_0_45px_rgba(139,92,246,.45)]';
                    ?>
                    <?= $avatarOpen ?>
                        <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center font-display text-4xl font-bold text-white <?= $avatarCls ?>">
                            <?= e($inisial) ?>
                        </div>
                    <?= $avatarClose ?>
                    <span class="absolute -bottom-2 -right-2 text-2xl"><?= $roleEmoji ?></span>
                </div>
                <div class="text-center sm:text-left min-w-0 flex-1">
                    <h1 class="font-display text-2xl sm:text-3xl font-bold flex items-center gap-2 justify-center sm:justify-start flex-wrap <?= $vip ? 'bg-gradient-to-r from-amber-300 via-yellow-400 to-amber-300 bg-clip-text text-transparent drop-shadow-[0_0_12px_rgba(251,191,36,.5)]' : 'text-white' ?>">
                        <?= e($t['name']) ?><?= $vip ? ' 💎' : '' ?>
                    </h1>
                    <p class="text-sm text-slate-400 font-mono mt-0.5">@<?= e($t['username'] ?? '—') ?></p>
                    <?php if ($online): ?>
                        <p class="text-[11px] mt-1 <?= $online === '🟢 Online' ? 'text-emerald-400 font-semibold' : 'text-slate-500' ?>"><?= e($online) ?></p>
                    <?php endif; ?>
                    <!-- TAGS — role + verified + kustom -->
                    <div class="mt-3 flex flex-wrap items-center gap-2 justify-center sm:justify-start">
                        <?= user_badges($t) ?>
                    </div>
                    <?php if ($bio !== ''): ?>
                        <p class="mt-3 text-sm text-slate-300 whitespace-pre-line break-words"><?= e($bio) ?></p>
                    <?php endif; ?>
                    <?php if ($isSelf): ?>
                        <p class="mt-2 text-xs text-slate-500"><?= e($t['email']) ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-[11px] text-slate-600">Bergabung <?= e(date('d M Y', strtotime((string) $t['created_at']))) ?> WIB</p>

                    <!-- Pengikut / Mengikuti (ala Instagram) -->
                    <p class="mt-3 text-xs text-slate-400 flex items-center gap-4 justify-center sm:justify-start">
                        <span><b class="text-white text-sm"><?= e(singkat((int) ($followers ?? 0))) ?></b> Pengikut</span>
                        <span><b class="text-white text-sm"><?= e(singkat((int) ($following ?? 0))) ?></b> Mengikuti</span>
                    </p>

                    <!-- Aksi sosial: Ikuti + DM + salin tautan -->
                    <div class="mt-4 flex items-center gap-2 justify-center sm:justify-start flex-wrap">
                        <?php if (!$isSelf && is_logged_in()): ?>
                            <form method="post" action="/profil/@<?= e($tuser) ?>/follow">
                                <?= csrf_field() ?>
                                <button class="px-5 py-2.5 rounded-xl text-sm font-bold transition shadow-lg <?= $isFollowing ? 'bg-white/10 border border-white/15 text-slate-300 hover:bg-white/15 shadow-none' : 'bg-gradient-to-r from-violet-600 to-fuchsia-500 text-white hover:opacity-90 shadow-fuchsia-600/25' ?>">
                                    <?= $isFollowing ? '✓ Mengikuti' : '➕ Ikuti' ?>
                                </button>
                            </form>
                            <a href="/pesan/<?= e($tuser) ?>" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition shadow-lg shadow-violet-600/25">✉️ Pesan</a>
                        <?php elseif (!$isSelf): ?>
                            <a href="/login" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold">Masuk untuk Ikuti & Chat ✉️</a>
                        <?php else: ?>
                            <a href="/profil" class="px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-slate-300 text-sm font-semibold hover:bg-white/10 transition">✏️ Edit Profil</a>
                        <?php endif; ?>
                        <button type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(location.href).then(()=>{this.textContent='✅ Tautan Disalin'})" class="px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-slate-400 text-sm hover:bg-white/10 transition">🔗 Salin Tautan</button>
                    </div>
                </div>
                <?php if ($isSelf): ?>
                <div class="sm:ml-auto text-center shrink-0">
                    <p class="text-[11px] uppercase tracking-wider text-slate-500">Saldo Koin</p>
                    <p class="font-display text-3xl font-bold text-neon-cyan drop-shadow-[0_0_10px_rgba(34,211,238,.5)]"><?= number_format((int) $t['coin_balance']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistik (4 kartu — tambah Postingan) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4" data-anim="fade-up">
            <div class="glass-card p-4 sm:p-5 text-center"><p class="font-display text-2xl font-bold text-white"><?= number_format($p['games']) ?></p><p class="text-[11px] text-slate-500 mt-1">Permainan</p></div>
            <div class="glass-card p-4 sm:p-5 text-center"><p class="font-display text-2xl font-bold text-neon-green"><?= number_format($p['wins']) ?></p><p class="text-[11px] text-slate-500 mt-1">Menang</p></div>
            <div class="glass-card p-4 sm:p-5 text-center"><p class="font-display text-2xl font-bold text-neon-purple"><?= number_format($p['orders']) ?></p><p class="text-[11px] text-slate-500 mt-1">Penukaran</p></div>
            <div class="glass-card p-4 sm:p-5 text-center"><p class="font-display text-2xl font-bold text-amber-300"><?= number_format($p['posts'] ?? 0) ?></p><p class="text-[11px] text-slate-500 mt-1">Postingan</p></div>
        </div>

        <!-- Grid postingan (ala Instagram) -->
        <?php if (!empty($posts)): ?>
        <div class="glass-card p-4 sm:p-6" data-anim="fade-up">
            <h2 class="font-display text-base font-bold text-white mb-4">📸 Postingan <?= $isSelf ? 'Saya' : e($t['name']) ?></h2>
            <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
                <?php foreach ($posts as $pp): ?>
                    <a href="/komunitas#p<?= (int) $pp['id'] ?>" class="group relative aspect-square rounded-xl overflow-hidden border border-white/5 <?= empty($pp['image']) ? 'bg-gradient-to-br from-violet-800/30 to-cyan-800/20 p-2.5' : '' ?>">
                        <?php if (!empty($pp['image'])): ?>
                            <img src="/media/social/<?= e($pp['image']) ?>" alt="postingan" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <?php else: ?>
                            <span class="text-[10px] sm:text-xs text-slate-300 leading-snug line-clamp-4"><?= e(mb_substr((string) $pp['body'], 0, 90)) ?></span>
                        <?php endif; ?>
                        <span class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition grid place-items-center text-xs font-bold text-white gap-2">❤️ <?= (int) $pp['likes_count'] ?> · 💬 <?= (int) $pp['comments_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isSelf): ?>
        <!-- Edit profil -->
        <div class="glass-card p-6 sm:p-8" data-anim="fade-up">
            <h2 class="font-display text-lg font-bold text-white mb-5">✏️ Edit Profil</h2>
            <form method="post" action="/profil" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Nama Tampilan</label>
                    <input name="name" value="<?= e($t['name']) ?>" maxlength="80" class="form-input w-full text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">📝 Bio (tampil di profil publik)</label>
                    <textarea name="bio" rows="2" maxlength="160" placeholder="Contoh: Sultan ChiperX 🎮 | top up aman & cepat ⚡" class="form-input w-full text-sm resize-none"><?= e($bio) ?></textarea>
                    <p class="text-[10px] text-slate-600 mt-1">Maks 160 karakter — seperti bio Instagram.</p>
                </div>
                <?php if (($t['role'] ?? 'user') === 'owner'): ?>
                <div class="rounded-xl border border-violet-500/30 bg-violet-500/5 p-4">
                    <label class="block text-xs font-semibold text-violet-300 mb-1 uppercase tracking-wider">🏷️ Tags Kustom (khusus Owner)</label>
                    <p class="text-[11px] text-slate-500 mb-2">Pisahkan dengan koma — maks 5 tag @ 24 karakter. Contoh: <code>ChiperX, Founder</code></p>
                    <input name="badges" value="<?= e((string) ($t['badges'] ?? '')) ?>" placeholder="ChiperX, Founder" maxlength="190" class="form-input w-full text-sm">
                </div>
                <?php endif; ?>
                <div class="flex items-center gap-3 flex-wrap">
                    <button class="px-6 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white text-sm hover:opacity-90 transition shadow-lg shadow-violet-600/30">
                        Simpan Perubahan ✓
                    </button>
                </div>
            </form>
            <form method="post" action="/logout" class="mt-6 pt-5 border-t border-white/10">
                <?= csrf_field() ?>
                <button class="px-6 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 font-semibold text-sm hover:bg-red-500/20 transition w-full sm:w-auto">
                    🚪 Logout
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</section>
