<?php /** PROFIL — identitas + tag role beranimasi + centang biru + statistik */ ?>
<?php
$t      = $target;
$inisial = strtoupper(mb_substr($t['name'] ?? 'U', 0, 1));
$roleEmoji = ['owner' => '👑', 'admin' => '🛡️', 'user' => '🎮'][$t['role'] ?? 'user'];
?>
<section class="px-6 py-14">
    <div class="max-w-3xl mx-auto space-y-6">

        <!-- Kartu identitas -->
        <div class="glass-card p-8 sm:p-10 relative overflow-hidden" data-anim="zoom">
            <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-violet-700/20 blur-[90px] pointer-events-none"></div>
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative shrink-0">
                    <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-violet-600 to-cyan-400 grid place-items-center font-display text-4xl font-bold text-white shadow-[0_0_45px_rgba(139,92,246,.45)]">
                        <?= e($inisial) ?>
                    </div>
                    <span class="absolute -bottom-2 -right-2 text-2xl"><?= $roleEmoji ?></span>
                </div>
                <div class="text-center sm:text-left">
                    <h1 class="font-display text-2xl sm:text-3xl font-bold text-white flex items-center gap-2 justify-center sm:justify-start flex-wrap">
                        <?= e($t['name']) ?>
                    </h1>
                    <p class="text-sm text-slate-400 font-mono mt-0.5">@<?= e($t['username'] ?? '—') ?></p>
                    <!-- TAGS — role + verified + kustom -->
                    <div class="mt-3 flex flex-wrap items-center gap-2 justify-center sm:justify-start">
                        <?= user_badges($t) ?>
                    </div>
                    <?php if ($isSelf): ?>
                        <p class="mt-2 text-xs text-slate-500"><?= e($t['email']) ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-[11px] text-slate-600">Bergabung <?= e(date('d M Y', strtotime((string) $t['created_at']))) ?> WIB</p>
                </div>
                <div class="sm:ml-auto text-center">
                    <p class="text-[11px] uppercase tracking-wider text-slate-500">Saldo Koin</p>
                    <p class="font-display text-3xl font-bold text-neon-cyan drop-shadow-[0_0_10px_rgba(34,211,238,.5)]"><?= number_format((int) $t['coin_balance']) ?></p>
                </div>
            </div>
        </div>

        <!-- Statistik -->
        <div class="grid grid-cols-3 gap-4" data-anim="fade-up">
            <div class="glass-card p-5 text-center"><p class="font-display text-2xl font-bold text-white"><?= number_format($p['games']) ?></p><p class="text-[11px] text-slate-500 mt-1">Permainan</p></div>
            <div class="glass-card p-5 text-center"><p class="font-display text-2xl font-bold text-neon-green"><?= number_format($p['wins']) ?></p><p class="text-[11px] text-slate-500 mt-1">Menang</p></div>
            <div class="glass-card p-5 text-center"><p class="font-display text-2xl font-bold text-neon-purple"><?= number_format($p['orders']) ?></p><p class="text-[11px] text-slate-500 mt-1">Penukaran</p></div>
        </div>

        <?php if ($isSelf): ?>
        <!-- Edit profil -->
        <div class="glass-card p-8" data-anim="fade-up">
            <h2 class="font-display text-lg font-bold text-white mb-5">✏️ Edit Profil</h2>
            <form method="post" action="/profil" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Nama Tampilan</label>
                    <input name="name" value="<?= e($t['name']) ?>" maxlength="80" class="form-input w-full text-sm">
                </div>
                <?php if (($t['role'] ?? 'user') === 'owner'): ?>
                <div class="rounded-xl border border-violet-500/30 bg-violet-500/5 p-4">
                    <label class="block text-xs font-semibold text-violet-300 mb-1 uppercase tracking-wider">🏷️ Tags Kustom (khusus Owner)</label>
                    <p class="text-[11px] text-slate-500 mb-2">Pisahkan dengan koma — maks 5 tag @ 24 karakter. Contoh: <code>ChiperX, Founder, VIP</code></p>
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
