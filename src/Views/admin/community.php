<?php /** ADMIN — Meja moderasi komunitas: pantau & hapus postingan bermasalah */ ?>
<div class="flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="font-display text-xl font-bold text-white">🧹 Moderasi Komunitas</h1>
        <p class="text-xs text-slate-500 mt-1">40 postingan terbaru — hapus yang melanggar aturan. Penghapusan tercatat di audit log.</p>
    </div>
    <a href="/komunitas" target="_blank" class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-slate-300 hover:bg-white/10 transition">👁️ Lihat feed publik</a>
</div>

<?php if (empty($posts)): ?>
    <div class="glass-card p-10 mt-6 text-center text-sm text-slate-500">Belum ada postingan komunitas. 🕊️</div>
<?php endif; ?>

<div class="space-y-3 mt-6">
    <?php foreach ($posts as $p): ?>
        <article class="glass-card p-4 flex items-start gap-3" data-anim="fade-up">
            <?php if (!empty($p['image'])): ?>
                <img src="/media/social/<?= e($p['image']) ?>" alt="" loading="lazy" class="w-16 h-16 rounded-xl object-cover border border-white/10 shrink-0">
            <?php endif; ?>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-400">
                    <b class="text-white"><?= e($p['name']) ?></b> <span class="font-mono text-slate-500">@<?= e($p['username']) ?></span>
                    <span class="<?= $p['role'] === 'owner' ? 'text-amber-300' : ($p['role'] === 'admin' ? 'text-violet-300' : 'text-slate-500') ?>">· <?= e($p['role']) ?></span>
                    · <?= e(waktu_lalu((string) $p['created_at'])) ?>
                </p>
                <p class="text-sm text-slate-200 mt-1 whitespace-pre-line break-words line-clamp-4"><?= e($p['body']) ?></p>
                <p class="text-[11px] text-slate-500 mt-2">❤️ <?= (int) $p['likes_count'] ?> · 💬 <?= (int) $p['comments_count'] ?> · <a href="/komunitas#p<?= (int) $p['id'] ?>" target="_blank" class="text-neon-cyan hover:underline">lihat</a></p>
            </div>
            <form method="post" action="/komunitas/<?= (int) $p['id'] ?>/delete" onsubmit="return confirm('Hapus postingan <?= e($p['username']) ?> ini secara permanen?')">
                <?= csrf_field() ?>
                <button class="px-3.5 py-2 rounded-xl bg-red-500/15 border border-red-500/30 text-red-300 text-xs font-bold hover:bg-red-500/25 transition">🗑️ Hapus</button>
            </form>
        </article>
    <?php endforeach; ?>
</div>
