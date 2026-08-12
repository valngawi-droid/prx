<?php /** MODERASI ULASAN (Admin) */ ?>
<h1 class="font-display text-2xl font-bold text-white">💬 Moderasi Ulasan</h1>
<div class="grid lg:grid-cols-2 gap-6 mt-6">
    <div class="glass-card p-6">
        <h2 class="font-display font-bold text-amber-300 mb-4">⏳ Menunggu Persetujuan (<?= count($pending) ?>)</h2>
        <?php if (empty($pending)): ?><p class="text-sm text-slate-500 py-6 text-center">Bersih! Tidak ada antrian 🎉</p><?php endif; ?>
        <?php foreach ($pending as $fb): ?>
            <div class="p-4 rounded-xl bg-white/[.03] border border-white/5 mb-3">
                <div class="flex justify-between text-sm"><b class="text-white"><?= e($fb['name']) ?></b><span class="text-amber-300 text-xs"><?= str_repeat('★', (int) $fb['rating']) ?></span></div>
                <p class="mt-1.5 text-sm text-slate-300">“<?= e($fb['message']) ?>”</p>
                <p class="mt-1 text-[10px] text-slate-600">IP: <?= e($fb['ip_address'] ?? '-') ?> · <?= e(waktu_lalu($fb['created_at'])) ?></p>
                <div class="flex gap-2 mt-3">
                    <form method="post" action="/admin/feedback/<?= (int) $fb['id'] ?>/approve"><?= csrf_field() ?>
                        <button class="px-4 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-300 text-xs font-semibold hover:bg-emerald-500/30">✓ Setujui</button>
                    </form>
                    <form method="post" action="/admin/feedback/<?= (int) $fb['id'] ?>/delete" onsubmit="return confirm('Hapus ulasan ini?')"><?= csrf_field() ?>
                        <button class="px-4 py-1.5 rounded-lg bg-red-500/15 text-red-300 text-xs hover:bg-red-500/25">✗ Hapus</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="glass-card p-6">
        <h2 class="font-display font-bold text-emerald-300 mb-4">✓ Disetujui (tampil di beranda)</h2>
        <?php foreach ($approved as $fb): ?>
            <div class="p-4 rounded-xl bg-white/[.02] border border-white/5 mb-2 flex justify-between items-start gap-3">
                <div><b class="text-sm text-white"><?= e($fb['name']) ?></b><p class="text-xs text-slate-400 mt-1">“<?= e($fb['message']) ?>”</p></div>
                <form method="post" action="/admin/feedback/<?= (int) $fb['id'] ?>/delete" onsubmit="return confirm('Hapus?')"><?= csrf_field() ?>
                    <button class="text-red-400/70 hover:text-red-400 text-xs">🗑️</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
