<?php /** OWNER CONTROL — ringkasan god mode + tes discord */ ?>
<h1 class="font-display text-2xl font-bold text-white">👑 Owner Control</h1>
<p class="text-sm text-slate-500 mt-1">Mode dewa diaktifkan. Semua perubahan tercatat di audit log & Discord.</p>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
    <div class="glass-card p-5"><p class="text-xs text-slate-500">👥 Total Akun</p><p class="mt-1 font-display text-3xl font-bold text-white"><?= e(number_format($totalUsers)) ?></p></div>
    <div class="glass-card p-5"><p class="text-xs text-slate-500">🛡️ Admin Aktif</p><p class="mt-1 font-display text-3xl font-bold text-neon-purple"><?= e(number_format((int) $totalAdmins)) ?></p></div>
    <div class="glass-card p-5"><p class="text-xs text-slate-500">📜 Log Hari Ini</p><p class="mt-1 font-display text-3xl font-bold text-neon-cyan"><?= e(number_format($logsToday)) ?></p></div>
    <div class="glass-card p-5 flex flex-col justify-between">
        <p class="text-xs text-slate-500">🔔 Integrasi Discord</p>
        <form method="post" action="/owner/discord/test" class="mt-2"><?= csrf_field() ?>
            <button class="w-full py-2 rounded-xl bg-indigo-500/20 border border-indigo-400/40 text-indigo-200 text-xs font-bold hover:bg-indigo-500/30 transition">TES WEBHOOK 🚀</button>
        </form>
    </div>
</div>

<!-- GRAFIK 7 HARI + BROADCAST -->
<div class="grid lg:grid-cols-5 gap-4 mt-6">
    <div class="glass-card p-6 lg:col-span-3">
        <h2 class="font-display font-bold text-white text-sm mb-4">📈 Aktivitas 7 Hari Terakhir</h2>
        <div class="h-52"><canvas id="ownerChart"></canvas></div>
    </div>
    <div class="glass-card p-6 lg:col-span-2 border-violet-500/25">
        <h2 class="font-display font-bold text-white text-sm">📣 Broadcast Notifikasi</h2>
        <p class="text-[11px] text-slate-500 mt-1">Terkirim ke lonceng 🔔 SEMUA user sekaligus.</p>
        <form method="post" action="/owner/broadcast" class="mt-3 space-y-2.5">
            <?= csrf_field() ?>
            <input name="title" maxlength="120" required placeholder="Judul — cth: Event Double Koin dimulai! 🎉" class="form-input w-full text-sm">
            <textarea name="body" maxlength="300" rows="2" placeholder="Isi pesan (opsional)…" class="form-input w-full text-sm"></textarea>
            <button class="w-full py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-xs font-bold text-white hover:opacity-90 transition">🚀 Kirim ke Semua User</button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
<script>
addEventListener('load', () => {
    const el = document.getElementById('ownerChart');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels ?? []) ?>,
            datasets: [
                { label: '👥 Daftar baru', data: <?= json_encode($chartRegs ?? []) ?>, borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,.15)', fill: true, tension: .4, pointRadius: 3 },
                { label: '🧾 Transaksi sukses', data: <?= json_encode($chartTx ?? []) ?>, borderColor: '#22d3ee', backgroundColor: 'rgba(34,211,238,.12)', fill: true, tension: .4, pointRadius: 3 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#94a3b8', boxWidth: 12, font: { size: 10 } } } },
            scales: {
                x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.04)' } },
                y: { beginAtZero: true, ticks: { color: '#64748b', precision: 0, font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.04)' } },
            },
        },
    });
});
</script>

<!-- Shortcut god mode -->
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
    <?php
    $shortcuts = [
        ['/owner/firewall', '🧯', 'Firewall', 'Anti-deface: ban IP otomatis/manual + jurnal ancaman'],
        ['/owner/filemanager', '🗂️', 'File Manager', 'Edit source code langsung dari browser (Monaco Editor)'],
        ['/owner/users', '🛡️', 'Kelola Akun', 'Add / edit / ban / delete admin & user'],
        ['/owner/settings', '⚙️', 'RTP & Setting', 'Atur win-rate RNG semua mini games'],
        ['/owner/links', '🔗', 'Atur Link', 'Kelola halaman All Link ChiperX'],
        ['/owner/backups', '💾', 'Backup DB', 'mysqldump → .sql.gz satu klik + unduh'],
        ['/owner/integrations', '🧩', 'Integrasi', 'SMTP Kirim.Email, Discord, payment keys'],
        ['/owner/api-tokens', '🔑', 'API Tokens', 'Akses API publik untuk developer'],
        ['/owner/logs', '📜', 'Audit Logs', 'Jejak semua aktivitas sensitif'],
    ];
    foreach ($shortcuts as [$href, $icon, $t, $d]): ?>
        <a href="<?= e($href) ?>" class="glass-card p-5 hover:border-violet-500/50 hover:-translate-y-1 transition group">
            <span class="text-2xl"><?= $icon ?></span>
            <h3 class="mt-2 font-semibold text-white group-hover:text-neon-cyan transition"><?= e($t) ?></h3>
            <p class="mt-1 text-xs text-slate-500"><?= e($d) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<div class="glass-card p-6 mt-6">
    <h2 class="font-display font-bold text-white mb-4">📜 Aktivitas Terakhir</h2>
    <div class="space-y-1.5 text-xs font-mono">
        <?php foreach ($recentLogs as $log): ?>
            <div class="flex flex-wrap gap-x-3 gap-y-0.5 p-2.5 rounded-lg bg-white/[.02] border border-white/5">
                <span class="<?= $log['level'] === 'critical' ? 'text-red-400' : ($log['level'] === 'warning' ? 'text-amber-300' : 'text-emerald-300') ?>">[<?= strtoupper(e($log['level'])) ?>]</span>
                <span class="text-slate-300"><?= e($log['action']) ?></span>
                <span class="text-slate-600"><?= e($log['email'] ?? 'sistem') ?></span>
                <span class="ml-auto text-slate-600"><?= e(waktu_lalu($log['created_at'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <a href="/owner/logs" class="inline-block mt-4 text-xs text-neon-cyan hover:underline">Lihat semua log →</a>
</div>
