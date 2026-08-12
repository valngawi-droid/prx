<?php /** OWNER — Kelola API Tokens untuk integrasi bot Discord */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-white">🔑 API Tokens</h1>
        <p class="text-sm text-slate-500 mt-1">Untuk bot Discord & tools komunitas. Token hanya ditampilkan **sekali** saat dibuat.</p>
    </div>
    <button onclick="document.getElementById('modalToken').showModal()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">+ Buat Token</button>
</div>

<div class="glass-card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
            <th class="p-4">Nama</th><th class="p-4">Scopes</th><th class="p-4">Terakhir Dipakai</th><th class="p-4">Status</th><th class="p-4 text-right">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($tokens)): ?><tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada token. Buat satu untuk bot Anda 🤖</td></tr><?php endif; ?>
        <?php foreach ($tokens as $t): ?>
            <tr class="border-b border-white/5 hover:bg-white/[.02]">
                <td class="p-4 font-semibold text-white"><?= e($t['name']) ?></td>
                <td class="p-4">
                    <?php foreach (explode(',', (string) $t['scopes']) as $s): ?>
                        <span class="px-2 py-0.5 rounded-md bg-cyan-500/10 text-cyan-300 text-[10px] font-mono mr-1"><?= e(trim($s)) ?></span>
                    <?php endforeach; ?>
                </td>
                <td class="p-4 text-xs text-slate-500"><?= e($t['last_used_at'] ? waktu_lalu($t['last_used_at']) : 'belum pernah') ?></td>
                <td class="p-4"><?= $t['revoked_at'] ? '<span class="text-red-400 text-xs font-bold">● DICABUT</span>' : '<span class="text-emerald-300 text-xs font-bold">● AKTIF</span>' ?></td>
                <td class="p-4 text-right">
                    <?php if (!$t['revoked_at']): ?>
                        <form method="post" action="/owner/api-tokens/<?= (int) $t['id'] ?>/revoke" onsubmit="return confirm('Cabut token ini? Semua integrasi yang memakainya langsung mati.')">
                            <?= csrf_field() ?><button class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-300 text-xs hover:bg-red-500/25">🚫 Cabut</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Panduan pakai -->
<div class="glass-card mt-6 p-6">
    <h2 class="font-display font-bold text-white mb-3">📡 Cara Pakai (contoh curl)</h2>
    <pre class="text-xs font-mono bg-slate-900/80 border border-white/10 rounded-xl p-4 overflow-x-auto text-slate-300">curl -H "Authorization: Bearer cx_TOKEN_ANDA" <?= e(rtrim(\ChiperX\Core\Config::appUrl(), '/')) ?>/api/v1/stats

curl -H "Authorization: Bearer cx_TOKEN_ANDA" <?= e(rtrim(\ChiperX\Core\Config::appUrl(), '/')) ?>/api/v1/leaderboard

curl -H "Authorization: Bearer cx_TOKEN_ANDA" "<?= e(rtrim(\ChiperX\Core\Config::appUrl(), '/')) ?>/api/v1/user/nama@gmail.com"</pre>
    <div class="mt-4 grid sm:grid-cols-3 gap-3 text-xs text-slate-400">
        <div class="p-3 rounded-lg bg-white/[.03] border border-white/5"><b class="text-cyan-300 font-mono">stats</b> — total user, transaksi, peredaran koin</div>
        <div class="p-3 rounded-lg bg-white/[.03] border border-white/5"><b class="text-cyan-300 font-mono">leaderboard</b> — top koin all-time & mingguan</div>
        <div class="p-3 rounded-lg bg-white/[.03] border border-white/5"><b class="text-cyan-300 font-mono">user</b> — cek koin & tiket user via email</div>
    </div>
</div>

<dialog id="modalToken" class="modal-glass">
    <form method="post" action="/owner/api-tokens" class="space-y-4">
        <?= csrf_field() ?>
        <h3 class="font-display font-bold text-white text-lg">Token Baru</h3>
        <input name="name" required maxlength="80" placeholder="Nama pemakaian (mis. Bot Discord Utama)" class="form-input w-full text-sm">
        <div class="flex gap-4 text-sm">
            <?php foreach ($scopes as $s): ?>
                <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="scopes[]" value="<?= e($s) ?>" <?= $s === 'stats' ? 'checked' : '' ?> class="accent-violet-500"> <span class="font-mono text-xs"><?= e($s) ?></span></label>
            <?php endforeach; ?>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="this.closest('dialog').close()" class="flex-1 py-2.5 rounded-xl bg-white/10 text-sm">Batal</button>
            <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Generate 🔑</button>
        </div>
    </form>
</dialog>
