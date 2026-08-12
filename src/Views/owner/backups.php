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
                    <!-- 🔁 Restore 1-klik (ketik RESTORE) -->
                    <form method="post" action="/owner/backups/restore"
                          onsubmit="const k=prompt('Ketik RESTORE untuk memulihkan database dari <?= e($f['name']) ?>\n\nPERINGATAN: data saat ini akan DITIMPA!');if(k!=='RESTORE')return false;this.querySelector('[name=confirm]').value=k;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                        <input type="hidden" name="confirm" value="">
                        <button class="px-4 py-2 rounded-xl bg-red-500/10 border border-red-400/30 text-red-300 text-xs font-bold hover:bg-red-500/20 transition" title="Pulihkan database dari file ini">🔁 Restore</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 🕐 AUTO-BACKUP HARIAN -->
<div class="glass-card p-5 mt-6 border-cyan-500/25">
    <form method="post" action="/owner/backups/auto" class="flex items-center gap-4 flex-wrap">
        <?= csrf_field() ?>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-white">🕐 Auto-Backup Harian</p>
            <p class="text-[11px] text-slate-500 mt-0.5">Otomasi: setiap hari, saat Owner pertama kali membuka panel, backup <code class="text-neon-cyan">chiperx-auto-YYYYMMDD.sql.gz</code> dibuat sendiri.</p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold <?= (\ChiperX\Models\Setting::get('backup_auto', '0') === '1') ? 'text-emerald-300' : 'text-slate-400' ?>">
            <input type="checkbox" name="on" <?= (\ChiperX\Models\Setting::get('backup_auto', '0') === '1') ? 'checked' : '' ?> class="accent-emerald-500 w-4 h-4" onchange="this.form.submit()"> AKTIF
        </label>
    </form>
</div>

<div class="glass-card p-5 mt-6 border-amber-500/20 bg-amber-500/5">
    <p class="text-xs text-amber-200/90 leading-relaxed">⚠️ <b>Tips senior:</b> file backup berisi SELURUH data (termasuk hash password & pengaturan). Jangan bagikan file ini ke siapa pun. Untuk restore di Termux: <code class="text-amber-300">gunzip -c file.sql.gz | mysql -u root chiperx</code></p>
</div>
