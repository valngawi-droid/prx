<?php /** 📢 KANAL — daftar channel gaya Discord + siapa online */ ?>
<?php $isStaff = $me && in_array($me['role'] ?? 'user', ['admin', 'owner'], true); ?>
<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-6">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-3xl sm:text-4xl font-bold text-white">📢 Kanal <span class="text-neon-cyan">ChiperX</span></h1>
        <p class="mt-2 text-sm text-slate-400">Ruang ngobrol bareng semua member — gaya Discord, khas ChiperX. 🎮</p>
    </header>

    <!-- 🟢 Siapa online (presence gaya Discord) -->
    <?php if (!empty($online)): ?>
    <div class="glass-card p-4" data-anim="fade-up">
        <p class="text-[11px] uppercase tracking-widest text-emerald-300 font-bold mb-2.5">🟢 Online sekarang (<?= count($online) ?>)</p>
        <div class="flex gap-3 overflow-x-auto pb-1" style="scrollbar-width:none;">
            <?php foreach ($online as $o): ?>
                <a href="/profil/@<?= e(rawurlencode((string) $o['username'])) ?>" class="flex flex-col items-center gap-1 w-14 shrink-0 group" title="<?= e($o['name']) ?>">
                    <span class="relative w-11 h-11 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 text-xs overflow-hidden group-hover:scale-105 transition">
                        <?php if (!empty($o['avatar'])): ?><img src="/media/avatar/<?= e((string) $o['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $o['name'], 0, 1))) ?><?php endif; ?>
                        <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-400 border-2 border-ink"></span>
                    </span>
                    <span class="text-[10px] text-slate-400 max-w-full truncate"><?= e(explode(' ', (string) $o['name'])[0]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- daftar channel -->
    <div class="space-y-3">
        <?php foreach ($channels as $c): ?>
            <a href="/kanal/<?= e($c['slug']) ?>" class="glass-card p-4 sm:p-5 flex items-center gap-4 hover:border-violet-500/40 transition group" data-anim="fade-up">
                <span class="w-12 h-12 rounded-2xl grid place-items-center text-2xl shrink-0 <?= $c['kind'] === 'announce' ? 'bg-amber-500/15 border border-amber-400/30' : 'bg-violet-500/15 border border-violet-400/30' ?>">
                    <?= $c['kind'] === 'announce' ? '📣' : '#' ?>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2 flex-wrap">
                        <b class="text-white group-hover:text-neon-cyan transition"><?= e($c['name']) ?></b>
                        <?php if ($c['kind'] === 'announce'): ?>
                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-amber-500/15 border border-amber-400/30 text-amber-300 font-bold">RESMI</span>
                        <?php endif; ?>
                        <?php if ((int) $c['slowmode'] > 0): ?>
                            <span class="text-[9px] px-2 py-0.5 rounded-full bg-white/5 border border-white/10 text-slate-400">🐌 <?= (int) $c['slowmode'] ?>dtk</span>
                        <?php endif; ?>
                    </span>
                    <span class="block text-xs text-slate-500 truncate mt-0.5"><?= e((string) ($c['topic'] ?? '')) ?></span>
                </span>
                <span class="text-right shrink-0">
                    <b class="text-neon-cyan text-sm"><?= e(singkat((int) $c['messages_count'])) ?></b>
                    <span class="block text-[10px] text-slate-600">pesan<?= $c['last_at'] ? ' · ' . e(waktu_lalu((string) $c['last_at'])) : '' ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($isStaff): ?>
    <!-- ➕ Buat channel (admin/owner) -->
    <div class="glass-card p-5 sm:p-6" data-anim="fade-up">
        <h2 class="font-display font-bold text-white mb-3">➕ Buat Kanal Baru</h2>
        <form method="post" action="/kanal/buat" class="grid sm:grid-cols-2 gap-3">
            <?= csrf_field() ?>
            <input name="name" required maxlength="40" placeholder="Nama kanal (mis: Turnamen)" class="form-input text-sm">
            <input name="topic" maxlength="120" placeholder="Topik singkat…" class="form-input text-sm">
            <select name="kind" class="form-input text-sm">
                <option value="text">💬 Teks — semua member bisa chat</option>
                <option value="announce">📣 Pengumuman — hanya staf yang memosting</option>
            </select>
            <button class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition">Buat Kanal ✨</button>
        </form>
    </div>
    <?php endif; ?>

    <p class="text-center text-xs text-slate-600">💡 Ketuk kanal untuk masuk — gunakan @username untuk mention, 📊 untuk polling, dan reaksi emoji di tiap pesan.</p>
</section>
