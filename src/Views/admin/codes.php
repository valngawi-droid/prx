<?php /** ADMIN — Kode Redeem kustom (buat + pantau klaim) */ ?>
<div class="flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="font-display text-xl font-bold text-white">🎟️ Kode Redeem Kustom</h1>
        <p class="text-xs text-slate-500 mt-1">Bagi-bagi koin via kode — cocok untuk event komunitas. Satu user hanya bisa klaim sekali per kode.</p>
    </div>
</div>

<div class="glass-card p-6 mt-6">
    <h2 class="font-display font-bold text-white text-sm mb-4">+ Buat Kode Baru</h2>
    <form method="post" action="/admin/codes" class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <?= csrf_field() ?>
        <div><label class="text-xs text-slate-400">Kode</label>
            <input name="code" maxlength="40" required placeholder="NEBULA21" class="form-input w-full text-sm mt-1 font-mono uppercase"></div>
        <div><label class="text-xs text-slate-400">Koin</label>
            <input name="coins" type="number" min="1" max="100000" required value="50" class="form-input w-full text-sm mt-1"></div>
        <div><label class="text-xs text-slate-400">Kuota (kosong = ∞)</label>
            <input name="quota" type="number" min="1" placeholder="100" class="form-input w-full text-sm mt-1"></div>
        <div><label class="text-xs text-slate-400">Kedaluwarsa (opsional)</label>
            <input name="expires_at" type="datetime-local" class="form-input w-full text-sm mt-1"></div>
        <button class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-bold text-white hover:opacity-90 transition">⚡ Aktifkan</button>
    </form>
</div>

<div class="glass-card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
            <th class="p-4">Kode</th><th class="p-4">Koin</th><th class="p-4">Diklaim</th><th class="p-4">Kedaluwarsa</th><th class="p-4">Status</th><th class="p-4 text-right">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($codes)): ?>
            <tr><td colspan="6" class="p-8 text-center text-slate-500 text-xs">Belum ada kode — buat yang pertama di atas! 🎟️</td></tr>
        <?php endif; ?>
        <?php foreach ($codes as $c):
            $expired = !empty($c['expires_at']) && strtotime((string) $c['expires_at']) < time();
            $habis = $c['quota'] !== null && (int) $c['used'] >= (int) $c['quota'];
        ?>
            <tr class="border-b border-white/5 hover:bg-white/[.02]">
                <td class="p-4 font-mono font-bold text-neon-cyan"><?= e($c['code']) ?></td>
                <td class="p-4 text-amber-300">🪙 <?= e(number_format((int) $c['coins'])) ?></td>
                <td class="p-4 text-xs"><?= (int) $c['used'] ?><?= $c['quota'] !== null ? ' / ' . (int) $c['quota'] : '' ?></td>
                <td class="p-4 text-xs text-slate-500"><?= !empty($c['expires_at']) ? e(date('d M Y H:i', strtotime((string) $c['expires_at']))) : '∞' ?></td>
                <td class="p-4 text-xs font-bold <?= !$c['is_active'] || $expired || $habis ? 'text-red-400' : 'text-emerald-300' ?>">
                    <?= !$c['is_active'] ? '● NONAKTIF' : ($expired ? '● KEDALUWARSA' : ($habis ? '● HABIS' : '● AKTIF')) ?>
                </td>
                <td class="p-4 text-right">
                    <form method="post" action="/admin/codes/<?= (int) $c['id'] ?>/toggle" class="inline"><?= csrf_field() ?>
                        <button class="px-3 py-1.5 rounded-lg text-xs <?= $c['is_active'] ? 'bg-amber-500/15 text-amber-300' : 'bg-emerald-500/15 text-emerald-300' ?>"><?= $c['is_active'] ? '⏸ Nonaktifkan' : '▶ Aktifkan' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
