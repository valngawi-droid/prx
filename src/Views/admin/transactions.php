<?php /** LOG TRANSAKSI (Admin, read-only) */ ?>
<h1 class="font-display text-2xl font-bold text-white">🧾 Log Transaksi</h1>
<div class="glass-card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
            <th class="p-4">ID</th><th class="p-4">Pengguna</th><th class="p-4">Produk</th><th class="p-4">Metode</th><th class="p-4">Jumlah</th><th class="p-4">Status</th><th class="p-4">Ref</th><th class="p-4">Waktu</th>
        </tr></thead>
        <tbody>
        <?php if (empty($transactions)): ?><tr><td colspan="8" class="p-8 text-center text-slate-500">Belum ada transaksi.</td></tr><?php endif; ?>
        <?php foreach ($transactions as $t): ?>
            <tr class="border-b border-white/5 hover:bg-white/[.02]">
                <td class="p-4 text-slate-500">#<?= (int) $t['id'] ?></td>
                <td class="p-4 text-slate-300"><?= e($t['email']) ?></td>
                <td class="p-4 text-white"><?= e($t['product_name']) ?></td>
                <td class="p-4 text-xs"><?= e(strtoupper($t['payment_method'])) ?></td>
                <td class="p-4 font-semibold <?= $t['payment_method'] === 'coin' ? 'text-amber-300' : 'text-neon-cyan' ?>">
                    <?= $t['payment_method'] === 'coin' ? '🪙 ' . number_format((int) $t['amount']) : e(rupiah((int) $t['amount'])) ?>
                </td>
                <td class="p-4"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $t['status'] === 'paid' ? 'bg-emerald-500/15 text-emerald-300' : ($t['status'] === 'pending' ? 'bg-amber-500/15 text-amber-300' : 'bg-red-500/15 text-red-300') ?>"><?= strtoupper(e($t['status'])) ?></span></td>
                <td class="p-4 text-[10px] text-slate-600 font-mono"><?= e((string) ($t['gateway_ref'] ?? '-')) ?></td>
                <td class="p-4 text-xs text-slate-500"><?= e(waktu_lalu($t['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
