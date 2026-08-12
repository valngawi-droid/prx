<?php /** 🔎 PENCARIAN GLOBAL — postingan + kanal + DM milikmu (gaya Telegram) */ ?>
<section class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-6">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-3xl font-bold text-white">🔎 Pencarian <span class="text-neon-cyan">Global</span></h1>
        <p class="mt-2 text-sm text-slate-400">Cari postingan, pesan kanal, dan isi chat pribadimu — dalam sekali ketuk.</p>
    </header>

    <form method="get" action="/cari" class="glass-card p-3 flex gap-2" data-anim="zoom">
        <input name="q" value="<?= e($q) ?>" maxlength="60" placeholder="Ketik kata kunci… (min. 2 huruf)" required class="form-input flex-1 text-sm py-3" autofocus>
        <button class="px-6 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold">Cari 🔍</button>
    </form>

    <?php if ($q !== ''): ?>
        <?php $total = count($posts) + count($kanal) + count($dm); ?>
        <p class="text-xs text-slate-500">Ditemukan <b class="text-neon-cyan"><?= $total ?></b> hasil untuk “<b class="text-white"><?= e($q) ?></b>”</p>

        <?php if ($posts): ?>
        <div class="glass-card p-5" data-anim="fade-up">
            <h2 class="font-display font-bold text-white mb-3">💬 Postingan Komunitas (<?= count($posts) ?>)</h2>
            <div class="space-y-3">
                <?php foreach ($posts as $p): ?>
                    <a href="/komunitas#p<?= (int) $p['id'] ?>" class="flex items-start gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/5 hover:bg-white/[0.06] transition">
                        <span class="shrink-0 w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center text-xs font-bold text-slate-900 overflow-hidden"><?php if (!empty($p['avatar'])): ?><img src="/media/avatar/<?= e((string) $p['avatar']) ?>" class="w-full h-full object-cover" alt=""><?php else: ?><?= e(strtoupper(mb_substr((string) $p['name'], 0, 1))) ?><?php endif; ?></span>
                        <span class="min-w-0 flex-1">
                            <b class="text-sm text-white"><?= e($p['name']) ?></b> <span class="text-[10px] text-slate-600"><?= e(waktu_lalu((string) $p['created_at'])) ?></span>
                            <span class="block text-xs text-slate-400 truncate"><?= e(mb_substr((string) $p['body'], 0, 90)) ?><?= !empty($p['image']) ? ' 📷' : '' ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($kanal): ?>
        <div class="glass-card p-5" data-anim="fade-up">
            <h2 class="font-display font-bold text-white mb-3">📢 Pesan Kanal (<?= count($kanal) ?>)</h2>
            <div class="space-y-3">
                <?php foreach ($kanal as $k): ?>
                    <a href="/kanal/<?= e($k['slug']) ?>#m<?= (int) $k['id'] ?>" class="flex items-start gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/5 hover:bg-white/[0.06] transition">
                        <span class="shrink-0 w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-400/25 grid place-items-center text-xs">#</span>
                        <span class="min-w-0 flex-1">
                            <b class="text-sm text-white"><?= e($k['name']) ?></b> <span class="text-[10px] text-slate-500">di <?= e($k['channel_name']) ?> · <?= e(waktu_lalu((string) $k['created_at'])) ?></span>
                            <span class="block text-xs text-slate-400 truncate"><?= e(mb_substr((string) $k['body'], 0, 90)) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dm): ?>
        <div class="glass-card p-5" data-anim="fade-up">
            <h2 class="font-display font-bold text-white mb-3">✉️ Chat Pribadimu (<?= count($dm) ?>)</h2>
            <div class="space-y-3">
                <?php foreach ($dm as $d): ?>
                    <a href="/pesan/<?= e(rawurlencode((string) $d['username'])) ?>" class="flex items-start gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/5 hover:bg-white/[0.06] transition">
                        <span class="shrink-0 w-9 h-9 rounded-xl bg-cyan-500/15 border border-cyan-400/25 grid place-items-center text-xs"><?= $d['arah'] === 'out' ? '↗️' : '↙️' ?></span>
                        <span class="min-w-0 flex-1">
                            <b class="text-sm text-white"><?= e($d['name']) ?></b> <span class="text-[10px] text-slate-600"><?= e(waktu_lalu((string) $d['created_at'])) ?></span>
                            <span class="block text-xs text-slate-400 truncate"><?= e(mb_substr((string) $d['body'], 0, 90)) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($total === 0): ?>
            <div class="glass-card p-10 text-center" data-anim="zoom">
                <p class="text-4xl mb-3">🕵️</p>
                <p class="text-slate-400 text-sm">Tidak ada hasil untuk “<?= e($q) ?>”. Coba kata kunci lain!</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
