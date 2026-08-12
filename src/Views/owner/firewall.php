<?php /** OWNER > FIREWALL — kontrol ban IP & jurnal ancaman */ ?>
<section class="space-y-6">
    <header data-anim="fade-up">
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">🧯 Firewall <span class="text-neon-cyan">Anti-Deface</span></h1>
        <p class="text-xs text-slate-500 mt-1">Pelaku deface/hack otomatis diblok: <b class="text-amber-300">peringatan + 10 menit</b> untuk scanning, <b class="text-red-400">PERMANEN</b> untuk payload berbahaya (SQLi/XSS/RCE/LFI). Laporan real-time masuk Discord. 🛡️</p>
    </header>

    <!-- STATISTIK -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4" data-anim="fade-up">
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Ban Aktif</p><p class="font-display text-3xl font-bold text-white mt-1"><?= e((string) $stats['active']) ?></p></div>
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Ban Permanen</p><p class="font-display text-3xl font-bold text-red-400 mt-1"><?= e((string) $stats['permanent']) ?></p></div>
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Ancaman 24 Jam</p><p class="font-display text-3xl font-bold text-amber-300 mt-1"><?= e((string) $stats['threats24h']) ?></p></div>
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Kritis 24 Jam</p><p class="font-display text-3xl font-bold text-rose-400 mt-1"><?= e((string) $stats['critical24h']) ?></p></div>
    </div>

    <!-- 📊 GRAFIK ANCAMAN 7 HARI -->
    <div class="glass-card p-5" data-anim="fade-up">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h2 class="font-display font-bold text-white">📊 Radar Serangan — 7 Hari</h2>
            <div class="flex items-center gap-3 text-[10px] text-slate-500">
                <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm inline-block bg-amber-400/80"></i> warning</span>
                <span class="flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm inline-block bg-red-500/90"></i> critical</span>
            </div>
        </div>
        <?php
        $fwMap = [];
        foreach ($chart7 ?? [] as $r) { $fwMap[(string) $r['d']] = [(int) $r['c'], (int) $r['crit']]; }
        $fwMax = 1;
        foreach ($fwMap as [$c]) { $fwMax = max($fwMax, $c); }
        $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        ?>
        <div class="flex items-end gap-2 h-28">
            <?php for ($i = 6; $i >= 0; $i--):
                $k = date('Y-m-d', strtotime("-{$i} days"));
                [$cnt, $crit] = $fwMap[$k] ?? [0, 0];
                $h = $cnt > 0 ? max(9, (int) round($cnt / $fwMax * 100)) : 5;
                $warn = max(0, $cnt - $crit);
                $hCrit = $cnt > 0 ? (int) round($crit / $cnt * $h) : 0;
            ?>
                <div class="flex-1 flex flex-col items-center gap-1 min-w-0" title="<?= e(date('d M', strtotime($k))) ?>: <?= $cnt ?> ancaman (<?= $crit ?> kritis)">
                    <span class="text-[9px] font-bold <?= $crit > 0 ? 'text-red-300' : ($cnt > 0 ? 'text-amber-300' : 'text-slate-600') ?>"><?= $cnt > 0 ? $cnt : '·' ?></span>
                    <div class="w-full rounded-t-md overflow-hidden flex flex-col-reverse" style="height: <?= $h ?>%">
                        <?php if ($cnt > 0): ?>
                            <div class="w-full bg-amber-400/70" style="height: <?= 100 - (int) round($crit / max(1, $cnt) * 100) ?>%"></div>
                            <div class="w-full bg-red-500/90" style="height: <?= (int) round($crit / max(1, $cnt) * 100) ?>%"></div>
                        <?php else: ?>
                            <div class="w-full h-full bg-white/5"></div>
                        <?php endif; ?>
                    </div>
                    <span class="text-[9px] <?= $i === 0 ? 'text-white font-bold' : 'text-slate-500' ?>"><?= $hari[(int) date('w', strtotime($k))] ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- 🧹 PEMBERSIH & ⬇️ EKSPOR -->
    <div class="grid sm:grid-cols-2 gap-3" data-anim="fade-up">
        <form method="post" action="/owner/clean" onsubmit="return confirm('Bersihkan data usang (stories kadaluarsa, notif lama, jurnal >30 hari, shoutbox >7 hari, file tmp)?')" class="glass-card p-4 flex items-center gap-3">
            <?= csrf_field() ?>
            <div class="flex-1">
                <p class="text-sm font-bold text-white">🧹 Pembersih Satu-Klik</p>
                <p class="text-[10px] text-slate-500">Pangkas data usang → DB ringan & ngebut.</p>
            </div>
            <button class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-500 text-white text-xs font-bold whitespace-nowrap">Bersihkan ✨</button>
        </form>
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="flex-1">
                <p class="text-sm font-bold text-white">⬇️ Ekspor Keamanan</p>
                <p class="text-[10px] text-slate-500">Unduh jurnal audit & ancaman (CSV).</p>
            </div>
            <a href="/owner/export/logs.csv" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-xs text-slate-300 hover:bg-white/10">Logs</a>
            <a href="/owner/export/threats.csv" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-xs text-slate-300 hover:bg-white/10">Threats</a>
        </div>
    </div>

    <div class="grid lg:grid-cols-5 gap-6">
        <!-- DAFTAR BAN -->
        <div class="glass-card p-5 lg:col-span-3" data-anim="fade-up">
            <h2 class="font-display font-bold text-white mb-4">⛔ IP yang Diblokir</h2>
            <?php if (empty($bans)): ?>
                <p class="text-sm text-slate-500 py-8 text-center">Tidak ada IP terblokir — situs aman & damai 🕊️</p>
            <?php endif; ?>
            <div class="space-y-2.5">
                <?php foreach ($bans as $b):
                    $perm   = (int) $b['permanent'] === 1;
                    $until  = $b['banned_until'] ? strtotime((string) $b['banned_until']) : null;
                    $aktif  = $perm || ($until !== null && $until > time());
                    $sisa   = $aktif && !$perm && $until ? max(0, $until - time()) : 0;
                ?>
                    <div class="rounded-xl border p-3.5 flex items-center gap-3 flex-wrap <?= $perm ? 'border-red-500/30 bg-red-500/[0.06]' : 'border-amber-500/25 bg-amber-500/[0.05]' ?>">
                        <span class="text-xl"><?= $perm ? '💀' : '⏳' ?></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-sm font-bold text-white"><?= e($b['ip']) ?>
                                <span class="ml-2 text-[9px] font-bold px-2 py-0.5 rounded-full <?= $perm ? 'bg-red-500/20 text-red-300 border border-red-500/40' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40' ?>"><?= $perm ? 'PERMANEN' : 'SEMENTARA' ?></span>
                                <?php if (!$aktif): ?><span class="ml-1 text-[9px] px-2 py-0.5 rounded-full bg-slate-500/20 text-slate-400 border border-slate-500/40">KEDALUWARSA</span><?php endif; ?>
                            </p>
                            <p class="text-[11px] text-slate-400 truncate mt-0.5">⚡ <?= e($b['reason']) ?> · pelanggaran ×<?= (int) $b['strikes'] ?>
                                <?php if ($aktif && !$perm): ?> · sisa <b class="text-amber-300"><?= intdiv($sisa, 60) ?>m <?= $sisa % 60 ?>d</b><?php endif; ?></p>
                        </div>
                        <form method="post" action="/owner/firewall/unban" onsubmit="return confirm('Cabut ban <?= e($b['ip']) ?>?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="ip" value="<?= e($b['ip']) ?>">
                            <button class="px-3.5 py-2 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-bold hover:bg-emerald-500/25 transition">✓ Cabut</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <!-- BAN MANUAL -->
            <div class="glass-card p-5" data-anim="fade-up">
                <h2 class="font-display font-bold text-white mb-1">🔨 Ban IP Manual</h2>
                <p class="text-[11px] text-slate-500 mb-4">Usir pengacau sebelum sempat beraksi.</p>
                <form method="post" action="/owner/firewall/ban" class="space-y-3">
                    <?= csrf_field() ?>
                    <input name="ip" required maxlength="45" placeholder="Alamat IP, mis. 36.72.141.88" class="form-input w-full text-sm font-mono">
                    <input name="reason" maxlength="190" placeholder="Alasan (opsional)" class="form-input w-full text-sm">
                    <select name="duration" class="form-input w-full text-sm">
                        <option value="600">⏳ 10 menit (peringatan)</option>
                        <option value="3600">⏳ 1 jam</option>
                        <option value="86400">⏳ 24 jam</option>
                        <option value="perm">⛔ PERMANEN</option>
                    </select>
                    <button class="w-full py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-500 text-white text-sm font-bold hover:opacity-90 transition shadow-lg shadow-red-600/25">Blokir Sekarang ⛔</button>
                </form>
            </div>

            <!-- JURNAL ANCAMAN -->
            <div class="glass-card p-5" data-anim="fade-up">
                <h2 class="font-display font-bold text-white mb-4">📡 Jurnal Ancaman Terakhir</h2>
                <?php if (empty($threats)): ?>
                    <p class="text-sm text-slate-500 py-6 text-center">Belum ada ancaman terekam ✅</p>
                <?php endif; ?>
                <div class="space-y-2 max-h-[26rem] overflow-y-auto pr-1">
                    <?php foreach ($threats as $t): ?>
                        <div class="rounded-lg bg-white/[0.03] border <?= $t['level'] === 'critical' ? 'border-red-500/25' : 'border-white/10' ?> p-2.5">
                            <p class="text-[11px] flex items-center gap-2 flex-wrap">
                                <span class="font-mono font-bold <?= $t['level'] === 'critical' ? 'text-red-300' : 'text-amber-300' ?>"><?= e($t['ip']) ?></span>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold <?= $t['level'] === 'critical' ? 'bg-red-500/20 text-red-300' : 'bg-amber-500/15 text-amber-300' ?>"><?= strtoupper(e($t['level'])) ?></span>
                                <span class="text-slate-600 ml-auto"><?= e(waktu_lalu((string) $t['created_at'])) ?></span>
                            </p>
                            <p class="text-[11px] text-slate-400 mt-1">⚡ <?= e($t['pattern']) ?></p>
                            <p class="text-[10px] text-slate-600 font-mono truncate mt-0.5" title="<?= e($t['uri']) ?>"><?= e($t['uri']) ?></p>
                            <!-- 🚫 Ban langsung dari jurnal -->
                            <form method="post" action="/owner/firewall/ban" class="mt-1.5" onsubmit="return confirm('Ban <?= e($t['ip']) ?> selama 24 jam?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="ip" value="<?= e($t['ip']) ?>">
                                <input type="hidden" name="duration" value="86400">
                                <input type="hidden" name="reason" value="Diblokir dari jurnal: <?= e(mb_substr((string) $t['pattern'], 0, 120)) ?>">
                                <button class="px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-[10px] font-bold hover:bg-red-500/20 transition">🚫 Ban 24 Jam</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
