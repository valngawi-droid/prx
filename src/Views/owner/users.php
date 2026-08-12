<?php /** OWNER — Kelola Admin & User (god mode) */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-display text-2xl font-bold text-white">🛡️ Kelola Admin & User (<?= (int) $total ?>)</h1>
    <div class="flex flex-wrap gap-2">
        <form method="get" class="flex gap-2">
            <input name="q" value="<?= e($search) ?>" placeholder="Cari…" class="form-input text-sm w-40">
            <select name="role" class="form-input text-sm" onchange="this.form.submit()">
                <option value="">Semua Role</option>
                <?php foreach (['owner', 'admin', 'user'] as $rOpt): ?>
                    <option value="<?= $rOpt ?>" <?= $role === $rOpt ? 'selected' : '' ?>><?= strtoupper($rOpt) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="px-4 rounded-xl bg-white/10 text-sm">🔍</button>
        </form>
        <button onclick="document.getElementById('modalAdd').showModal()" class="px-4 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">+ Tambah Akun</button>
    </div>
</div>

<div class="glass-card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
            <th class="p-4">Akun</th><th class="p-4">Role</th><th class="p-4">Koin</th><th class="p-4">Status</th><th class="p-4">Tags</th><th class="p-4">Login</th><th class="p-4 text-right">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach ($users as $usr): ?>
            <tr class="border-b border-white/5 hover:bg-white/[.02]">
                <td class="p-4"><b class="text-white"><?= e($usr['name']) ?></b><p class="text-xs text-slate-500"><?= e($usr['email']) ?></p></td>
                <td class="p-4"><span class="px-2 py-1 rounded-lg text-[10px] font-bold
                    <?= $usr['role'] === 'owner' ? 'bg-amber-500/15 text-amber-300' : ($usr['role'] === 'admin' ? 'bg-violet-500/15 text-violet-300' : 'bg-slate-500/15 text-slate-400') ?>">
                    <?= strtoupper(e($usr['role'])) ?></span></td>
                <td class="p-4 text-amber-300">🪙 <?= e(number_format((int) $usr['coin_balance'])) ?></td>
                <td class="p-4"><?= $usr['status'] === 'active' ? '<span class="text-emerald-300 text-xs font-bold">● AKTIF</span>' : '<span class="text-red-400 text-xs font-bold">● BANNED</span>' ?></td>
                <td class="p-4 min-w-[150px] max-w-[240px]">
                    <?php
                    $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($usr['badges'] ?? '')))));
                    ?>
                    <div class="flex flex-wrap gap-1 items-center">
                        <?php foreach ($tags as $tag):
                            $minus = implode(',', array_values(array_filter($tags, static fn($t) => $t !== $tag)));
                        ?>
                            <form method="post" action="/owner/users/<?= (int) $usr['id'] ?>/badges" class="inline" title="Ketuk untuk menghapus tag «<?= e($tag) ?>»">
                                <?= csrf_field() ?><input type="hidden" name="badges" value="<?= e($minus) ?>">
                                <button class="px-2 py-0.5 rounded-full text-[10px] font-semibold border border-violet-400/40 bg-violet-500/10 text-violet-200 hover:bg-red-500/20 hover:border-red-400/50 hover:text-red-200 transition"><?= e($tag) ?> <span class="opacity-60">×</span></button>
                            </form>
                        <?php endforeach; ?>
                        <?php if (count($tags) < 5): ?>
                            <?php foreach (['💎 VIP', '🛍️ Seller', '⭐ Premium', '🧪 Tester'] as $preset):
                                if (in_array($preset, $tags, true)) { continue; }
                                $plus = implode(',', [...$tags, $preset]);
                            ?>
                                <form method="post" action="/owner/users/<?= (int) $usr['id'] ?>/badges" class="inline" title="Tambah tag <?= e($preset) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="badges" value="<?= e($plus) ?>">
                                    <button class="px-1.5 py-0.5 rounded-full text-[10px] border border-dashed border-white/20 text-slate-500 hover:border-neon-cyan hover:text-neon-cyan transition">+<?= e($preset) ?></button>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($tags === []): ?><span class="text-[10px] text-slate-600">— tanpa tags —</span><?php endif; ?>
                    </div>
                </td>
                <td class="p-4 text-xs text-slate-500"><?= e($usr['last_login'] ? waktu_lalu($usr['last_login']) : '-') ?></td>
                <td class="p-4">
                    <div class="flex justify-end items-center gap-2 flex-wrap">
                        <button onclick='editUser(<?= json_encode($usr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="px-3 py-1.5 rounded-lg bg-white/10 text-xs hover:bg-white/15">✏️</button>
                        <form method="post" action="/owner/users/<?= (int) $usr['id'] ?>/verify" title="Toggle centang biru"><?= csrf_field() ?>
                            <button class="px-3 py-1.5 rounded-lg text-xs <?= !empty($usr['is_verified']) ? 'bg-sky-500/20 text-sky-300' : 'bg-white/5 text-slate-500' ?>"><?= !empty($usr['is_verified']) ? '✓ Verified' : '✓?' ?></button>
                        </form>
                        <a href="/profil/@<?= e(rawurlencode((string) ($usr['username'] ?? ''))) ?>" target="_blank" title="Lihat profil publik" class="px-3 py-1.5 rounded-lg bg-white/10 text-xs hover:bg-white/15">👤</a>
                        <?php if ($usr['role'] !== 'owner'): ?>
                            <form method="post" action="/owner/users/<?= (int) $usr['id'] ?>/ban" onsubmit="return confirm('<?= $usr['status'] === 'banned' ? 'Aktifkan kembali' : 'Ban' ?> akun ini?')"><?= csrf_field() ?>
                                <button class="px-3 py-1.5 rounded-lg text-xs <?= $usr['status'] === 'banned' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300' ?>"><?= $usr['status'] === 'banned' ? '✓ Unban' : '🔨 Ban' ?></button>
                            </form>
                            <form method="post" action="/owner/users/<?= (int) $usr['id'] ?>/delete" onsubmit="return confirm('HAPUS PERMANEN akun ini beserta seluruh datanya?')"><?= csrf_field() ?>
                                <button class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-300 text-xs">🗑️</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php $pages = (int) ceil($total / $perPage); if ($pages > 1): ?>
    <div class="flex justify-center gap-2 mt-6 text-sm">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?= $i ?>&q=<?= e($search) ?>&role=<?= e($role) ?>" class="px-3.5 py-2 rounded-lg <?= $i === $page ? 'bg-violet-600/40 text-white' : 'bg-white/5 text-slate-400' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<dialog id="modalAdd" class="modal-glass">
    <form method="post" action="/owner/users" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Tambah Akun Baru</h3>
        <input type="email" name="email" required placeholder="email akun" class="form-input w-full text-sm">
        <select name="role" class="form-input w-full text-sm">
            <option value="user">USER</option><option value="admin">ADMIN</option>
        </select>
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Buat</button>
        </div>
    </form>
</dialog>

<dialog id="modalEdit" class="modal-glass">
    <form method="post" id="formEdit" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Edit Akun</h3>
        <input name="name" required maxlength="80" placeholder="Nama" class="form-input w-full text-sm">
        <div class="grid grid-cols-2 gap-3">
            <select name="role" id="editRole" class="form-input text-sm"><option value="user">USER</option><option value="admin">ADMIN</option><option value="owner">OWNER</option></select>
            <select name="status" class="form-input text-sm"><option value="active">AKTIF</option><option value="banned">BANNED</option></select>
        </div>
        <input name="coin_balance" type="number" min="0" placeholder="Koin" class="form-input w-full text-sm">
        <input name="badges" maxlength="190" placeholder="Tags kustom (koma) — cth: VIP, Tester" class="form-input w-full text-sm">
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Simpan</button>
        </div>
    </form>
</dialog>

<script>
function editUser(u) {
    const f = document.getElementById('formEdit');
    f.action = '/owner/users/' + u.id + '/update';
    f.querySelector('[name=name]').value = u.name;
    f.querySelector('#editRole').value = u.role;
    f.querySelector('[name=status]').value = u.status;
    f.querySelector('[name=coin_balance]').value = u.coin_balance;
    f.querySelector('[name=badges]').value = u.badges || '';
    document.getElementById('modalEdit').showModal();
}
</script>
