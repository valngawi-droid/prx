<?php /** OWNER > AI SENTINEL — penjaga keamanan 24/7 berbasis AI */ ?>
<section class="space-y-6">
    <header data-anim="fade-up">
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">🤖 AI <span class="text-neon-cyan">Sentinel</span>
            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>JAGA 24/7</span>
        </h1>
        <p class="text-xs text-slate-500 mt-1">Penjaga keamanan digital ala WhatsApp Security: memantau, menilai risiko, dan menyarankan aksi — otomatis & berkala melapor ke Discord setiap hari.</p>
    </header>

    <!-- STATUS PATROLI -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4" data-anim="fade-up">
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Ancaman 24 Jam</p><p class="font-display text-3xl font-bold <?= $stats['threats24h'] > 0 ? 'text-amber-300' : 'text-emerald-400' ?> mt-1"><?= e((string) $stats['threats24h']) ?></p></div>
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Serangan Kritis</p><p class="font-display text-3xl font-bold <?= $stats['critical24h'] > 0 ? 'text-red-400' : 'text-emerald-400' ?> mt-1"><?= e((string) $stats['critical24h']) ?></p></div>
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Ban Aktif</p><p class="font-display text-3xl font-bold text-white mt-1"><?= e((string) $stats['active']) ?></p></div>
        <div class="glass-card p-4 sm:p-5"><p class="text-[10px] uppercase tracking-widest text-slate-500">Ban Permanen</p><p class="font-display text-3xl font-bold text-rose-400 mt-1"><?= e((string) $stats['permanent']) ?></p></div>
    </div>

    <!-- ANALISIS AI -->
    <div class="glass-card p-5 sm:p-6" data-anim="fade-up">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-500 grid place-items-center text-2xl shadow-lg shadow-violet-600/30">🧠</div>
            <div class="flex-1 min-w-0">
                <h2 class="font-display font-bold text-white">Analisis Ancaman oleh AI</h2>
                <p class="text-[11px] text-slate-500">AI senior membaca jurnal ancaman → penilaian risiko + rekomendasi aksi (Bahasa Indonesia).</p>
            </div>
            <form method="post" action="/owner/sentinel/analyze" onsubmit="const b=this.querySelector('button');b.disabled=true;b.textContent='🧠 Menganalisis…';">
                <?= csrf_field() ?>
                <button class="px-5 py-3 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition shadow-lg shadow-violet-600/25">🔍 Analisis Sekarang</button>
            </form>
        </div>

        <?php if (is_string($aiAnswer ?? null) && $aiAnswer !== ''): ?>
            <div class="mt-5 rounded-2xl border border-cyan-500/25 bg-cyan-500/[0.05] p-5 relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-cyan-500/10 blur-3xl"></div>
                <p class="text-[10px] uppercase tracking-widest text-neon-cyan font-bold mb-3 flex items-center gap-1.5">🤖 Laporan AI Sentinel <span class="text-slate-600 normal-case tracking-normal">· <?= e(date('d M Y H:i')) ?> WIB</span></p>
                <div class="text-sm text-slate-200 whitespace-pre-line leading-relaxed"><?= e($aiAnswer) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ANCAMAN TERAKHIR + TINDAKAN -->
    <div class="grid lg:grid-cols-2 gap-6">
        <div class="glass-card p-5" data-anim="fade-up">
            <h2 class="font-display font-bold text-white mb-4">📡 Radar Ancaman Terakhir</h2>
            <?php if (empty($threats)): ?>
                <p class="text-sm text-slate-500 py-8 text-center">Radar bersih — tidak ada ancaman. Semua aman! 🕊️</p>
            <?php endif; ?>
            <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                <?php foreach ($threats as $t): ?>
                    <div class="rounded-lg bg-white/[0.03] border <?= $t['level'] === 'critical' ? 'border-red-500/25' : 'border-white/10' ?> p-2.5 text-[11px]">
                        <span class="font-mono font-bold <?= $t['level'] === 'critical' ? 'text-red-300' : 'text-amber-300' ?>"><?= e($t['ip']) ?></span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded-full font-bold <?= $t['level'] === 'critical' ? 'bg-red-500/20 text-red-300' : 'bg-amber-500/15 text-amber-300' ?>"><?= strtoupper(e($t['level'])) ?></span>
                        <span class="text-slate-600 float-right"><?= e(waktu_lalu((string) $t['created_at'])) ?></span>
                        <p class="text-slate-400 mt-1">⚡ <?= e($t['pattern']) ?> → <code class="text-slate-500"><?= e(mb_substr($t['uri'], 0, 60)) ?></code></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="/owner/firewall" class="mt-4 inline-block text-xs text-neon-cyan hover:underline">Kelola ban di Firewall →</a>
        </div>

        <div class="glass-card p-5" data-anim="fade-up">
            <h2 class="font-display font-bold text-white mb-3">🛡️ Lapisan Pertahanan Aktif</h2>
            <ul class="space-y-2.5 text-[13px] text-slate-300">
                <?php foreach ([
                    ['✅', 'WAF pola serangan', '60+ signature SQLi/XSS/RCE/LFI/webshell — ban PERMANEN'],
                    ['✅', 'Honeypot paths', '.env, .git, wp-admin, phpMyAdmin → peringatan + 10 menit'],
                    ['✅', 'Rate limiting', 'Anti-flood & brute-force massal (150 req/menit/IP)'],
                    ['✅', 'Eskalasi otomatis', 'Peringatan ke-3 dalam 24 jam → permanen'],
                    ['✅', 'Laporan Discord', 'Alert real-time tiap ban + ringkasan harian 06:00 WIB'],
                    ['✅', 'Security headers', 'nosniff, frame-ancestors, Referrer-Policy'],
                    ['✅', 'Audit forensik', 'Semua kejadian tercatat di Audit Logs'],
                ] as [$ic, $t, $d]): ?>
                    <li class="flex gap-3 rounded-xl bg-white/[0.03] border border-white/5 p-3">
                        <span><?= $ic ?></span>
                        <div><b class="text-white text-xs"><?= e($t) ?></b><p class="text-[11px] text-slate-500 mt-0.5"><?= e($d) ?></p></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="/owner/api-health" class="mt-4 inline-flex items-center gap-2 text-xs px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition">🩺 Cek kesehatan 30 API Tools →</a>
        </div>
    </div>
</section>
