<?php /** KELOLA PENGGUNA (Admin) — tambah & edit user standar */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-display text-2xl font-bold text-white">👥 Pengguna (<?= (int) $total ?>)</h1>
    <div class="flex gap-3">
        <form method="get" class="flex gap-2">
            <input name="q" value="<?= e($search) ?>" placeholder="Cari email/nama…" class="form-input text-sm w-48">
            <button class="px-4 rounded-xl bg-white/10 text-sm hover:bg-white/15">🔍</button>
        </form>
        <button onclick="document.getElementById('modalAddUser').showModal()" class="px-4 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">+ Tambah</button>
    </div>
</div>

<div class="glass-card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
            <th class="p-4">Pengguna</th><th class="p-4">Koin</th><th class="p-4">Login Terakhir</th><th class="p-4">Status</th><th class="p-4 text-right">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($users)): ?><tr><td colspan="5" class="p-8 text-center text-slate-500">Tidak ada pengguna.</td></tr><?php endif; ?>
        <?php foreach ($users as $usr): ?>
            <tr class="border-b border-white/5 hover:bg-white/[.02]">
                <td class="p-4"><b class="text-white"><?= e($usr['name']) ?></b><p class="text-xs text-slate-500"><?= e($usr['email']) ?></p></td>
                <td class="p-4 text-amber-300 font-semibold">
                    🪙 <?= e(number_format((int) $usr['coin_balance'])) ?>
                    <!-- 💰 Atur koin cepat (+/−) -->
                    <form method="post" action="/admin/users/<?= (int) $usr['id'] ?>/coins" class="mt-1.5 flex items-center gap-1">
                        <?= csrf_field() ?>
                        <input name="delta" type="number" min="-100000" max="100000" placeholder="+50 / -20" required
                               class="form-input w-20 text-[11px] py-1 px-2" title="Isi +50 untuk menambah, -20 untuk mengurangi">
                        <input name="reason" maxlength="120" placeholder="alasan (ops.)" class="hidden sm:block form-input w-24 text-[11px] py-1 px-2">
                        <button class="px-2 py-1 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-300 text-[10px] font-bold hover:bg-amber-500/25 transition" title="Terapkan perubahan koin">OK</button>
                    </form>
                </td>
                <td class="p-4 text-xs text-slate-500"><?= e($usr['last_login'] ? waktu_lalu($usr['last_login']) : 'belum pernah') ?></td>
                <td class="p-4"><?= $usr['status'] === 'active' ? '<span class="text-emerald-300 text-xs font-bold">● AKTIF</span>' : '<span class="text-red-400 text-xs font-bold">● BANNED</span>' ?></td>
                <td class="p-4 text-right">
                    <button onclick='editUser(<?= json_encode($usr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="px-3 py-1.5 rounded-lg bg-white/10 text-xs hover:bg-white/15">✏️ Edit</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php $pages = (int) ceil($total / $perPage); if ($pages > 1): ?>
    <div class="flex justify-center gap-2 mt-6 text-sm">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= e($search) ?>" class="px-3.5 py-2 rounded-lg <?= $i === $page ? 'bg-violet-600/40 text-white' : 'bg-white/5 text-slate-400 hover:bg-white/10' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<dialog id="modalAddUser" class="modal-glass">
    <form method="post" action="/admin/users" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Tambah Pengguna</h3>
        <input type="email" name="email" required placeholder="email pengguna" class="form-input w-full text-sm">
        <p class="text-xs text-slate-500">Pengguna akan login melalui OTP email seperti biasa.</p>
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Buat Akun</button>
        </div>
    </form>
</dialog>

<dialog id="modalEditUser" class="modal-glass">
    <form method="post" id="formEditUser" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Edit Pengguna</h3>
        <input name="name" required maxlength="80" placeholder="Nama" class="form-input w-full text-sm">
        <input name="coin_balance" type="number" min="0" placeholder="Koin" class="form-input w-full text-sm">
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Simpan</button>
        </div>
    </form>
</dialog>

<script>
function editUser(u) {
    const f = document.getElementById('formEditUser');
    f.action = '/admin/users/' + u.id + '/update';
    f.querySelector('[name=name]').value = u.name;
    f.querySelector('[name=coin_balance]').value = u.coin_balance;
    document.getElementById('modalEditUser').showModal();
}
</script>
