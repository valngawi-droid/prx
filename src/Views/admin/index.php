<?php /** RINGKASAN ADMIN */ ?>
<h1 class="font-display text-2xl font-bold text-white">📊 Ringkasan Admin</h1>

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
    <?php
    $cards = [
        ['Total Pengguna', number_format($totalUsers), '👥', 'text-neon-cyan'],
        ['Transaksi Lunas', number_format($totalTx), '🧾', 'text-emerald-300'],
        ['Total Pendapatan', rupiah($totalRevenue), '💰', 'text-amber-300'],
        ['Total Game Dimainkan', number_format($totalPlays), '🎮', 'text-neon-purple'],
        ['Tiket Terbuka', number_format($openTickets), '🎫', 'text-red-300'],
        ['Ulasan Menunggu', number_format($pendingFeedback), '💬', 'text-cyan-300'],
    ];
    foreach ($cards as [$label, $val, $icon, $color]): ?>
        <div class="glass-card p-5">
            <div class="flex items-center justify-between"><span class="text-xs text-slate-500"><?= e($label) ?></span><span><?= $icon ?></span></div>
            <p class="mt-2 font-display text-2xl font-bold <?= e($color) ?>"><?= e($val) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- ⚡ Pintasan aksi cepat -->
<div class="flex items-center gap-2 flex-wrap mt-6 text-xs" data-anim="fade-up">
    <a href="/admin/komunitas" class="px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 hover:text-white transition">🧹 Moderasi Komunitas</a>
    <a href="/admin/export/users.csv" class="px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 hover:text-white transition">⬇️ CSV Pengguna</a>
    <a href="/admin/export/transactions.csv" class="px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 hover:text-white transition">⬇️ CSV Transaksi</a>
    <a href="/admin/export/codes.csv" class="px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 hover:text-white transition">⬇️ CSV Kode</a>
</div>

<!-- 📊 GRAFIK 7 HARI + EKONOMI KOIN -->
<div class="grid lg:grid-cols-3 gap-4 mt-4">
    <?php
    $mkChart = function (array $rows): array {
        $map = [];
        foreach ($rows as $r) { $map[(string) $r['d']] = (int) $r['c']; }
        return [$map, max(1, max($map ?: [0]))];
    };
    [$uMap, $uMax] = $mkChart($chartUsers ?? []);
    [$tMap, $tMax] = $mkChart($chartTx ?? []);
    $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $bar = static function (array $map, int $max, string $grad, string $ring) use ($hari): string {
        $html = '';
        for ($i = 6; $i >= 0; $i--) {
            $k = date('Y-m-d', strtotime("-{$i} days"));
            $v = $map[$k] ?? 0;
            $h = $v > 0 ? max(9, (int) round($v / $max * 100)) : 5;
            $t = $i === 0;
            $html .= '<div class="flex-1 flex flex-col items-center gap-1" title="' . e(date('d M', strtotime($k))) . ': ' . $v . '">'
                . '<span class="text-[9px] font-bold ' . ($v > 0 ? 'text-white' : 'text-slate-600') . '">' . ($v > 0 ? $v : '·') . '</span>'
                . '<div class="w-full rounded-t-md ' . ($v > 0 ? $grad : 'bg-white/5') . ($t ? ' ' . $ring : '') . '" style="height:' . $h . '%"></div>'
                . '<span class="text-[9px] ' . ($t ? 'text-white font-bold' : 'text-slate-500') . '">' . $hari[(int) date('w', strtotime($k))] . '</span></div>';
        }
        return $html;
    };
    ?>
    <div class="glass-card p-5" data-anim="fade-up">
        <p class="text-xs font-bold text-white mb-3">👥 Pengguna Baru / Hari</p>
        <div class="flex items-end gap-1.5 h-24"><?= $bar($uMap, $uMax, 'bg-gradient-to-t from-cyan-600/70 to-cyan-300/80', 'ring-1 ring-cyan-400/60') ?></div>
    </div>
    <div class="glass-card p-5" data-anim="fade-up">
        <p class="text-xs font-bold text-white mb-3">🧾 Transaksi / Hari</p>
        <div class="flex items-end gap-1.5 h-24"><?= $bar($tMap, $tMax, 'bg-gradient-to-t from-violet-600/70 to-fuchsia-400/80', 'ring-1 ring-fuchsia-400/60') ?></div>
    </div>
    <div class="glass-card p-5 flex flex-col justify-center gap-3" data-anim="fade-up">
        <p class="text-xs font-bold text-white">🪙 Ekonomi Koin</p>
        <div class="flex items-center justify-between text-sm"><span class="text-slate-400 text-xs">Koin beredar</span><b class="text-amber-300"><?= e(singkat((int) ($coinSupply ?? 0))) ?></b></div>
        <div class="flex items-center justify-between text-sm"><span class="text-slate-400 text-xs">Klaim redeem 7 hari</span><b class="text-emerald-300"><?= e(number_format((int) ($redeemsWeek ?? 0))) ?></b></div>
        <div class="flex items-center justify-between text-sm"><span class="text-slate-400 text-xs">Postingan 7 hari</span><b class="text-neon-cyan"><?= e(number_format((int) ($postsWeek ?? 0))) ?></b></div>
    </div>
</div>

<div class="glass-card p-6 mt-6">
    <h2 class="font-display font-bold text-white mb-4">🧾 Transaksi Terbaru</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs text-slate-500 border-b border-white/10">
                <th class="py-2 pr-4">Pengguna</th><th class="py-2 pr-4">Produk</th><th class="py-2 pr-4">Jumlah</th><th class="py-2 pr-4">Status</th><th class="py-2">Waktu</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentTx as $t): ?>
                <tr class="border-b border-white/5 hover:bg-white/[.02]">
                    <td class="py-2.5 pr-4 text-slate-300"><?= e($t['email']) ?></td>
                    <td class="py-2.5 pr-4 text-white"><?= e($t['product_name']) ?></td>
                    <td class="py-2.5 pr-4"><?= $t['payment_method'] === 'coin' ? '🪙 ' . number_format((int) $t['amount']) : e(rupiah((int) $t['amount'])) ?></td>
                    <td class="py-2.5 pr-4"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $t['status'] === 'paid' ? 'bg-emerald-500/15 text-emerald-300' : ($t['status'] === 'pending' ? 'bg-amber-500/15 text-amber-300' : 'bg-red-500/15 text-red-300') ?>"><?= strtoupper(e($t['status'])) ?></span></td>
                    <td class="py-2.5 text-xs text-slate-500"><?= e(waktu_lalu($t['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
