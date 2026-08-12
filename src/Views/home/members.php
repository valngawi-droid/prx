<?php /** MEMBERS — direktori publik komunitas ChiperX */ ?>
<section class="max-w-6xl mx-auto px-5 py-12">
    <div class="text-center mb-8" data-anim="zoom">
        <h1 class="font-display text-3xl font-bold text-white">👥 Members <span class="text-neon-cyan">ChiperX</span></h1>
        <p class="text-sm text-slate-400 mt-2"><b class="text-neon-green"><?= e(number_format($total)) ?></b> member telah bergabung — kenalan dengan komunitas!</p>
        <form method="get" class="mt-5 max-w-sm mx-auto flex gap-2">
            <input name="q" value="<?= e($q) ?>" placeholder="Cari nama / username…" class="form-input flex-1 text-sm">
            <button class="px-4 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-semibold">🔍</button>
        </form>
    </div>

    <?php if (empty($members)): ?>
        <div class="glass-card p-10 text-center text-slate-500 text-sm">Tidak ada member yang cocok dengan pencarian «<?= e($q) ?>». 🕵️</div>
    <?php else: ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php foreach ($members as $m): ?>
                <div class="glass-card p-5 text-center hover:border-violet-400/40 hover:-translate-y-1 transition" data-anim="fade">
                    <div class="w-14 h-14 mx-auto rounded-full grid place-items-center font-display text-xl font-bold text-slate-900 bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg shadow-violet-600/30">
                        <?= e(strtoupper(mb_substr((string) $m['name'], 0, 1))) ?>
                    </div>
                    <p class="mt-3 font-semibold text-white text-sm truncate flex items-center justify-center gap-1">
                        <?= e((string) $m['name']) ?>
                    </p>
                    <div class="mt-1 flex justify-center"><?= user_badges($m) ?></div>
                    <?php if (!empty($m['username'])): ?>
                        <a href="/u/<?= e(rawurlencode((string) $m['username'])) ?>" class="mt-2 inline-block text-[11px] text-neon-cyan hover:underline">@<?= e((string) $m['username']) ?></a>
                    <?php endif; ?>
                    <p class="mt-1.5 text-[10px] text-slate-600">gabung <?= e(date('M Y', strtotime((string) $m['created_at']))) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
