<?php /** PENGUMUMAN (Admin) */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-display text-2xl font-bold text-white">📢 Pengumuman Komunitas</h1>
    <button onclick="document.getElementById('modalAnn').showModal()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">+ Buat Pengumuman</button>
</div>

<!-- 📣 BROADCAST LONCENG — notifikasi instan ke semua member -->
<div class="glass-card p-5 mt-6 border-cyan-500/25 bg-cyan-500/[0.03]" data-anim="fade-up">
    <h2 class="font-display font-bold text-white text-sm mb-1">📣 Broadcast Lonceng <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/15 text-cyan-300 align-middle">BARU</span></h2>
    <p class="text-[11px] text-slate-500 mb-4">Langsung masuk ke 🔔 notifikasi semua member aktif — cocok untuk info event, maintenance, giveaway.</p>
    <form method="post" action="/admin/broadcast" class="grid sm:grid-cols-[1fr_1.4fr_auto] gap-3 items-end" onsubmit="return confirm('Kirim notifikasi ke SEMUA member sekarang?')">
        <?= csrf_field() ?>
        <div><label class="text-xs text-slate-400">Judul *</label>
            <input name="title" maxlength="120" required placeholder="Event Double Koin dimulai! ⚡" class="form-input w-full text-sm mt-1"></div>
        <div><label class="text-xs text-slate-400">Isi singkat (opsional)</label>
            <input name="body" maxlength="300" placeholder="18-21 Agustus semua hadiah ×2 — jangan sampai ketinggalan!" class="form-input w-full text-sm mt-1"></div>
        <button class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-600 to-sky-500 text-sm font-bold text-white hover:opacity-90 transition whitespace-nowrap">📣 Kirim ke Semua</button>
    </form>
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
