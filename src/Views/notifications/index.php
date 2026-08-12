<?php /** NOTIFIKASI — riwayat lonceng pengguna */ ?>
<?php
$iconOf = static function (string $t): string {
    return match ($t) {
        'success'  => '✅',
        'warning'  => '⚠️',
        'critical' => '🚨',
        default    => '🔔',
    };
};
?>
<section class="max-w-2xl mx-auto px-5 py-10">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold text-white">🔔 Notifikasi</h1>
            <p class="text-xs text-slate-500 mt-1">Kabar terbaru seputar akunmu — otomatis ditandai dibaca saat halaman ini dibuka.</p>
        </div>
        <a href="javascript:history.back()" class="text-xs text-slate-400 hover:text-neon-cyan transition">← Kembali</a>
    </div>

    <?php if (empty($items)): ?>
        <div class="glass-card p-10 text-center" data-anim="zoom">
            <p class="text-4xl mb-3">🌙</p>
            <p class="text-slate-400 text-sm">Belum ada notifikasi. Lonceng ini akan berbunyi saat ada kabar — misalnya tiket dibalas admin atau kamu dapat badge baru.</p>
        </div>
    <?php else: ?>
        <div class="space-y-2.5">
            <?php foreach ($items as $n): ?>
                <?php $isUnread = empty($n['is_read']); ?>
                <<?= !empty($n['url']) ? 'a href="' . e((string) $n['url']) . '"' : 'div' ?>
                   class="block glass-card p-4 flex gap-3 items-start transition hover:border-violet-400/40 <?= $isUnread ? 'border-violet-400/30 bg-violet-500/5' : 'opacity-75' ?>">
                    <span class="text-xl shrink-0 mt-0.5"><?= $iconOf((string) $n['type']) ?></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-white leading-snug"><?= e((string) $n['title']) ?></p>
                        <?php if (!empty($n['body'])): ?>
                            <p class="text-xs text-slate-400 mt-1 leading-relaxed"><?= e((string) $n['body']) ?></p>
                        <?php endif; ?>
                        <p class="text-[10px] text-slate-600 mt-1.5"><?= e(waktu_lalu((string) $n['created_at'])) ?></p>
                    </div>
                    <?php if ($isUnread): ?><span class="w-2 h-2 rounded-full bg-neon-cyan shrink-0 mt-2 shadow-[0_0_8px_rgba(34,211,238,.9)]"></span><?php endif; ?>
                </<?= !empty($n['url']) ? 'a' : 'div' ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
