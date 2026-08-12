<?php /** KELOLA PRODUK (Admin) — tambah/edit/hapus + upload file */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-display text-2xl font-bold text-white">📦 Produk & Upload File</h1>
    <button onclick="document.getElementById('modalAdd').showModal()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white hover:opacity-90 transition">+ Tambah Produk</button>
</div>

<div class="glass-card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
            <th class="p-4">Produk</th><th class="p-4">Tipe</th><th class="p-4">Harga</th><th class="p-4">Stok</th><th class="p-4">Status</th><th class="p-4 text-right">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($products)): ?><tr><td colspan="6" class="p-8 text-center text-slate-500">Belum ada produk.</td></tr><?php endif; ?>
        <?php foreach ($products as $p): ?>
            <tr class="border-b border-white/5 hover:bg-white/[.02]">
                <td class="p-4"><b class="text-white"><?= e($p['name']) ?></b><p class="text-xs text-slate-500 truncate max-w-[16rem]"><?= e($p['description'] ?? '') ?></p></td>
                <td class="p-4"><span class="px-2 py-1 rounded-lg text-[10px] font-bold <?= $p['type'] === 'coin_redeem' ? 'bg-amber-500/15 text-amber-300' : 'bg-cyan-500/15 text-cyan-300' ?>"><?= $p['type'] === 'coin_redeem' ? '🎁 REDEEM' : '🛒 STORE' ?></span></td>
                <td class="p-4 font-semibold <?= $p['type'] === 'coin_redeem' ? 'text-amber-300' : 'text-neon-cyan' ?>"><?= $p['type'] === 'coin_redeem' ? '🪙 ' . number_format((int) $p['price']) : e(rupiah((int) $p['price'])) ?></td>
                <td class="p-4 text-slate-400">
                    <!-- 📦 Stok cepat -->
                    <form method="post" action="/admin/products/<?= (int) $p['id'] ?>/stock" class="flex items-center gap-1">
                        <?= csrf_field() ?>
                        <input name="stock" type="number" min="0" max="100000" value="<?= $p['stock'] === null ? '' : (int) $p['stock'] ?>" placeholder="∞"
                               class="form-input w-16 text-[11px] py-1 px-2" title="Stok (kosong = tak terbatas)">
                        <button class="px-2 py-1 rounded-lg bg-white/10 border border-white/15 text-slate-300 text-[10px] hover:bg-white/15 transition" title="Simpan stok">✓</button>
                    </form>
                </td>
                <td class="p-4">
                    <!-- 🟢 Toggle aktif 1-klik -->
                    <form method="post" action="/admin/products/<?= (int) $p['id'] ?>/toggle">
                        <?= csrf_field() ?>
                        <button class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition <?= $p['is_active'] ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 hover:bg-emerald-500/25' : 'bg-slate-500/10 text-slate-500 border border-white/10 hover:bg-white/10' ?>" title="Ketuk untuk mengubah status">
                            <?= $p['is_active'] ? '● AKTIF' : '○ NONAKTIF' ?>
                        </button>
                    </form>
                </td>
                <td class="p-4">
                    <div class="flex justify-end gap-2">
                        <button onclick='editProduct(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="px-3 py-1.5 rounded-lg bg-white/10 text-xs hover:bg-white/15">✏️ Edit</button>
                        <form method="post" action="/admin/products/<?= (int) $p['id'] ?>/delete" onsubmit="return confirm('Hapus produk ini permanen?')">
                            <?= csrf_field() ?><button class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-300 text-xs hover:bg-red-500/25">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- DIALOG tambah -->
<dialog id="modalAdd" class="modal-glass">
    <form method="post" action="/admin/products" enctype="multipart/form-data" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Tambah Produk</h3>
        <?php include BASE_PATH . '/src/Views/partials/product-form.php'; ?>
        <div class="flex gap-3 pt-2">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Simpan</button>
        </div>
    </form>
</dialog>

<!-- DIALOG edit -->
<dialog id="modalEdit" class="modal-glass">
    <form method="post" id="formEdit" enctype="multipart/form-data" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Edit Produk</h3>
        <?php include BASE_PATH . '/src/Views/partials/product-form.php'; ?>
        <div class="flex gap-3 pt-2">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Perbarui</button>
        </div>
    </form>
</dialog>

<script>
function editProduct(p) {
    const f = document.getElementById('formEdit');
    f.action = '/admin/products/' + p.id + '/update';
    f.querySelector('[name=name]').value = p.name;
    f.querySelector('[name=type]').value = p.type;
    f.querySelector('[name=price]').value = p.price;
    f.querySelector('[name=stock]').value = p.stock ?? '';
    f.querySelector('[name=file_url]').value = p.file_url?.startsWith('http') ? p.file_url : '';
    f.querySelector('[name=description]').value = p.description ?? '';
    f.querySelector('[name=is_featured]').checked = !!+p.is_featured;
    f.querySelector('[name=is_active]').checked = !!+p.is_active;
    document.getElementById('modalEdit').showModal();
}
</script>
