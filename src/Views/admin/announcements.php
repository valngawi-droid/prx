<?php /** PENGUMUMAN (Admin) */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-display text-2xl font-bold text-white">📢 Pengumuman Komunitas</h1>
    <button onclick="document.getElementById('modalAnn').showModal()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">+ Buat Pengumuman</button>
</div>

<div class="grid md:grid-cols-2 gap-4 mt-6">
    <?php if (empty($announcements)): ?><p class="glass-card p-8 text-center text-slate-500 md:col-span-2">Belum ada pengumuman.</p><?php endif; ?>
    <?php foreach ($announcements as $a): ?>
        <div class="glass-card p-5 <?= $a['is_active'] ? '' : 'opacity-50' ?>">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $a['level'] === 'success' ? 'bg-emerald-500/15 text-emerald-300' : ($a['level'] === 'warning' ? 'bg-amber-500/15 text-amber-300' : 'bg-cyan-500/15 text-cyan-300') ?>"><?= strtoupper(e($a['level'])) ?></span>
                    <h3 class="mt-2 font-semibold text-white"><?= e($a['title']) ?></h3>
                    <p class="mt-1 text-sm text-slate-400"><?= e($a['body']) ?></p>
                    <p class="mt-2 text-[10px] text-slate-600">oleh <?= e($a['author'] ?? '-') ?> · <?= e(waktu_lalu($a['created_at'])) ?></p>
                </div>
                <div class="flex flex-col gap-2 shrink-0">
                    <form method="post" action="/admin/announcements/<?= (int) $a['id'] ?>/toggle"><?= csrf_field() ?>
                        <button class="px-3 py-1.5 rounded-lg bg-white/10 text-xs hover:bg-white/15"><?= $a['is_active'] ? '⏸️' : '▶️' ?></button>
                    </form>
                    <form method="post" action="/admin/announcements/<?= (int) $a['id'] ?>/delete" onsubmit="return confirm('Hapus pengumuman?')"><?= csrf_field() ?>
                        <button class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-300 text-xs hover:bg-red-500/25">🗑️</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<dialog id="modalAnn" class="modal-glass">
    <form method="post" action="/admin/announcements" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Pengumuman Baru</h3>
        <input name="title" required maxlength="160" placeholder="Judul" class="form-input w-full text-sm">
        <select name="level" class="form-input w-full text-sm">
            <option value="info">ℹ️ Info</option><option value="success">✅ Sukses</option><option value="warning">⚠️ Peringatan</option>
        </select>
        <textarea name="body" required rows="4" maxlength="3000" placeholder="Isi pengumuman..." class="form-input w-full text-sm"></textarea>
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Terbitkan</button>
        </div>
    </form>
</dialog>
