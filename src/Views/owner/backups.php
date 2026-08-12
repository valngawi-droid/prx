<?php /** OWNER — Backup database 1-klik */ ?>
<div class="flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="font-display text-xl font-bold text-white">💾 Backup Database</h1>
        <p class="text-xs text-slate-500 mt-1">Mysqldump → .sql.gz tersimpan di <code class="text-neon-cyan">storage/backups/</code>. Unduh & simpan copy di luar HP secara berkala!</p>
    </div>
    <form method="post" action="/owner/backups/create"><?= csrf_field() ?>
        <button class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-bold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">⚡ Backup Sekarang</button>
    </form>
</div>

<div class="glass-card mt-6">
    <?php if (empty($files)): ?>
        <p class="p-8 text-center text-slate-500 text-sm">Belum ada backup. Tekan tombol ⚡ di atas untuk membuat yang pertama. 💾</p>
    <?php else: ?>
        <div class="divide-y divide-white/5">
            <?php foreach ($files as $f): ?>
                <div class="flex items-center gap-3 p-4">
                    <span class="text-xl">🗄️</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-mono text-white truncate"><?= e($f['name']) ?></p>
                        <p class="text-[10px] text-slate-500"><?= e($f['time']) ?> WIB • <?= e(number_format($f['size'] / 1024, 1)) ?> KB</p>
                    </div>
                    <a href="/owner/backups/<?= e(rawurlencode($f['name'])) ?>/download"
                       class="px-4 py-2 rounded-xl bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-xs font-bold hover:bg-emerald-500/25 transition">⬇ Unduh</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="glass-card p-5 mt-6 border-amber-500/20 bg-amber-500/5">
    <p class="text-xs text-amber-200/90 leading-relaxed">⚠️ <b>Tips senior:</b> file backup berisi SELURUH data (termasuk hash password & pengaturan). Jangan bagikan file ini ke siapa pun. Untuk restore di Termux: <code class="text-amber-300">gunzip -c file.sql.gz | mysql -u root chiperx</code></p>
</div>
