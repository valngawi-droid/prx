<?php /** OWNER — Kelola Links (halaman All Link) */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-display text-2xl font-bold text-white">🔗 Atur All Link</h1>
    <button onclick="document.getElementById('modalAdd').showModal()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">+ Tambah Link</button>
</div>

<div class="glass-card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
            <th class="p-4">#</th><th class="p-4">Judul</th><th class="p-4">URL</th><th class="p-4">Ikon</th><th class="p-4">Warna</th><th class="p-4">Status</th><th class="p-4 text-right">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($links)): ?><tr><td colspan="7" class="p-8 text-center text-slate-500">Belum ada link.</td></tr><?php endif; ?>
        <?php foreach ($links as $l): ?>
            <tr class="border-b border-white/5 hover:bg-white/[.02]">
                <td class="p-4 text-slate-500"><?= (int) $l['order_num'] ?></td>
                <td class="p-4 font-semibold text-white"><?= e($l['title']) ?></td>
                <td class="p-4 text-xs text-slate-400 max-w-[14rem] truncate"><a href="<?= e($l['url']) ?>" target="_blank" class="hover:text-neon-cyan"><?= e($l['url']) ?></a></td>
                <td class="p-4 text-xs text-slate-500"><?= e($l['icon_class']) ?></td>
                <td class="p-4"><span class="inline-block w-5 h-5 rounded-md border border-white/20" style="background: <?= e($l['color']) ?>"></span></td>
                <td class="p-4"><?= $l['is_active'] ? '<span class="text-emerald-300 text-xs font-bold">● AKTIF</span>' : '<span class="text-slate-500 text-xs">○ MATI</span>' ?></td>
                <td class="p-4">
                    <div class="flex justify-end gap-2">
                        <button onclick='editLink(<?= json_encode($l, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="px-3 py-1.5 rounded-lg bg-white/10 text-xs hover:bg-white/15">✏️</button>
                        <form method="post" action="/owner/links/<?= (int) $l['id'] ?>/delete" onsubmit="return confirm('Hapus link ini?')"><?= csrf_field() ?>
                            <button class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-300 text-xs">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$formFields = static function (): void { ?>
    <input name="title" required maxlength="120" placeholder="Judul tombol" class="form-input w-full text-sm">
    <input name="url" required maxlength="500" placeholder="https://…" class="form-input w-full text-sm">
    <div class="grid grid-cols-3 gap-3">
        <select name="icon_class" class="form-input text-sm">
            <?php foreach (['discord', 'telegram', 'instagram', 'github', 'youtube', 'web', 'link'] as $ic): ?>
                <option value="<?= $ic ?>"><?= ucfirst($ic) ?></option>
            <?php endforeach; ?>
        </select>
        <input name="color" type="color" value="#8b5cf6" class="form-input text-sm h-10 p-1 cursor-pointer">
        <input name="order_num" type="number" value="0" placeholder="Urutan" class="form-input text-sm">
    </div>
    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" name="is_active" checked class="accent-violet-500"> Tampilkan di halaman All Link</label>
<?php }; ?>

<dialog id="modalAdd" class="modal-glass">
    <form method="post" action="/owner/links" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Tambah Link</h3>
        <?php $formFields(); ?>
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Simpan</button>
        </div>
    </form>
</dialog>

<dialog id="modalEdit" class="modal-glass">
    <form method="post" id="formEdit" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Edit Link</h3>
        <?php $formFields(); ?>
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Perbarui</button>
        </div>
    </form>
</dialog>

<script>
function editLink(l) {
    const f = document.getElementById('formEdit');
    f.action = '/owner/links/' + l.id + '/update';
    f.querySelector('[name=title]').value = l.title;
    f.querySelector('[name=url]').value = l.url;
    f.querySelector('[name=icon_class]').value = l.icon_class;
    f.querySelector('[name=color]').value = l.color;
    f.querySelector('[name=order_num]').value = l.order_num;
    f.querySelector('[name=is_active]').checked = !!+l.is_active;
    document.getElementById('modalEdit').showModal();
}
</script>
