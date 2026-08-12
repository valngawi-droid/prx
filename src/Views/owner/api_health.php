<?php /** OWNER > CEK KESEHATAN 30 API (dijalankan server Termux sendiri) */ ?>
<?php
$total   = count($rows);
$okCount = count(array_filter($rows, static fn($r) => $r['ok']));
$fail    = $total - $okCount;
?>
<section class="space-y-6">
    <header data-anim="fade-up">
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-white">🩺 Kesehatan <span class="text-neon-cyan">30 API</span></h1>
        <p class="text-xs text-slate-500 mt-1">Dites langsung dari server Termux-mu (bukan cache) — bukti nyata endpoint hidup/mati.</p>
    </header>

    <div class="grid grid-cols-3 gap-3 sm:gap-4" data-anim="fade-up">
        <div class="glass-card p-4 sm:p-5 text-center"><p class="font-display text-3xl font-bold text-white"><?= $total ?></p><p class="text-[10px] uppercase tracking-widest text-slate-500 mt-1">Total Endpoint</p></div>
        <div class="glass-card p-4 sm:p-5 text-center"><p class="font-display text-3xl font-bold text-emerald-400"><?= $okCount ?></p><p class="text-[10px] uppercase tracking-widest text-slate-500 mt-1">✅ Berfungsi</p></div>
        <div class="glass-card p-4 sm:p-5 text-center"><p class="font-display text-3xl font-bold <?= $fail > 0 ? 'text-red-400' : 'text-slate-500' ?>"><?= $fail ?></p><p class="text-[10px] uppercase tracking-widest text-slate-500 mt-1">❌ Bermasalah</p></div>
    </div>

    <div class="glass-card overflow-hidden" data-anim="fade-up">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] uppercase tracking-widest text-slate-500 border-b border-white/10">
                        <th class="px-4 py-3">Alat</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Kecepatan</th>
                        <th class="px-4 py-3">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($rows as $key => $r): $t = $tools[$key]; ?>
                        <tr class="hover:bg-white/[0.03]">
                            <td class="px-4 py-2.5 text-xs"><?= $t['icon'] ?> <b class="text-white"><?= e($t['name']) ?></b></td>
                            <td class="px-4 py-2.5 text-[11px] text-slate-500"><?= e($t['cat']) ?></td>
                            <td class="px-4 py-2.5">
                                <?php if ($r['ok']): ?>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/40 text-emerald-300">✅ HIDUP</span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-red-500/15 border border-red-500/40 text-red-300">❌ MATI</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-[11px] font-mono text-slate-400"><?= $r['ms'] ? $r['ms'] . ' ms' : '—' ?></td>
                            <td class="px-4 py-2.5 text-[11px] text-slate-500 max-w-[220px] truncate" title="<?= e((string) $r['note']) ?>"><?= e((string) $r['note']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex items-center gap-3 flex-wrap" data-anim="fade-up">
        <a href="/owner/api-health" onclick="this.textContent='⏳ Mengetes ulang 30 endpoint… (bisa 1-2 menit)'" class="px-5 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition">🔄 Tes Ulang Semua</a>
        <a href="/owner/sentinel" class="px-5 py-3 rounded-xl bg-white/5 border border-white/10 text-slate-300 text-sm hover:bg-white/10 transition">← AI Sentinel</a>
        <a href="/owner/integrations" class="px-5 py-3 rounded-xl bg-white/5 border border-white/10 text-slate-300 text-sm hover:bg-white/10 transition">🧩 Atur CODEX_API_KEY (opsional)</a>
    </div>
</section>
