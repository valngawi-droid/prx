<?php /** STATUS — halaman kesehatan publik */ ?>
<section class="max-w-3xl mx-auto px-5 py-12">
    <div class="text-center mb-8" data-anim="zoom">
        <div class="text-5xl mb-3"><?= $dbOk ? '🟢' : '🔴' ?></div>
        <h1 class="font-display text-3xl font-bold <?= $dbOk ? 'text-emerald-300' : 'text-red-400' ?>">
            <?= $dbOk ? 'All Systems Operational' : 'Gangguan Terdeteksi' ?>
        </h1>
        <p class="text-sm text-slate-400 mt-2">Pemeriksaan real-time — diperbarui setiap halaman dimuat.</p>
    </div>

    <div class="glass-card divide-y divide-white/5" data-anim="fade">
        <?php
        $rows = [
            ['🗄️ Database', $dbOk, $dbOk ? "Terhubung dalam {$dbMs}ms" : 'TIDAK TERHUBUNG — tim sedang mengecek'],
            ['🧱 Skema tabel', $tables >= 19, $tables . ' tabel terdeteksi'],
            ['📧 Layanan email', true, 'Driver aktif: ' . $mailDrv],
            ['🐘 Engine PHP', true, 'v' . $phpVer],
            ['🌐 Alamat publik', true, $appUrl],
        ];
        foreach ($rows as [$label, $ok, $note]): ?>
            <div class="flex items-center gap-4 p-4">
                <span class="text-lg"><?= $ok ? '✅' : '❌' ?></span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-white"><?= e($label) ?></p>
                    <p class="text-xs text-slate-500"><?= e($note) ?></p>
                </div>
                <span class="text-xs font-bold <?= $ok ? 'text-emerald-300' : 'text-red-400' ?>"><?= $ok ? 'OPERASIONAL' : 'DOWN' ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-center text-xs text-slate-600 mt-6">Server time: <?= e($wib) ?> WIB • Powered by Termux + Cloudflare Tunnel 🚀</p>
</section>
