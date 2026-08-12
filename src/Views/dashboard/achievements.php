<?php /** PENCAPAIAN — galeri badge: terbuka (berkilau) vs terkunci */ ?>
<section class="max-w-4xl mx-auto space-y-6">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-3xl font-bold text-white">🏆 Pencapaian Saya</h1>
        <p class="mt-1 text-sm text-slate-400">Kumpulkan semua badge dengan aktif bermain, posting, dan bertransaksi!</p>
    </header>

    <!-- Progres keseluruhan -->
    <?php $pct = $badgeTotal > 0 ? (int) round($badgeCount / $badgeTotal * 100) : 0; ?>
    <div class="glass-card p-5" data-anim="zoom">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="text-slate-300 font-semibold">Progres Badge</span>
            <span class="text-neon-cyan font-bold"><?= (int) $badgeCount ?>/<?= (int) $badgeTotal ?> · <?= $pct ?>%</span>
        </div>
        <div class="h-3 rounded-full bg-white/5 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-violet-500 via-fuchsia-500 to-cyan-400 transition-all duration-700" style="width:<?= max(3, $pct) ?>%"></div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
        <?php foreach ($badges as $b): $open = !empty($b['unlocked_at']); ?>
            <div class="glass-card p-5 text-center relative overflow-hidden <?= $open ? 'border-amber-400/40' : 'opacity-70' ?>" data-anim="fade-up">
                <?php if ($open): ?>
                    <div class="absolute -top-10 -right-10 w-24 h-24 rounded-full bg-amber-400/20 blur-2xl"></div>
                <?php endif; ?>
                <p class="text-4xl mb-2 <?= $open ? 'drop-shadow-[0_0_12px_rgba(251,191,36,.6)]' : 'grayscale opacity-50' ?>"><?= e((string) $b['icon']) ?></p>
                <p class="font-bold text-sm <?= $open ? 'text-amber-200' : 'text-slate-300' ?>"><?= e($b['name']) ?></p>
                <p class="text-[11px] text-slate-500 mt-1 leading-snug"><?= e((string) ($b['description'] ?? '')) ?></p>
                <?php if ((int) ($b['reward_coins'] ?? 0) > 0): ?>
                    <p class="mt-2 text-[11px] font-bold text-neon-cyan">🪙 +<?= number_format((int) $b['reward_coins']) ?></p>
                <?php endif; ?>
                <p class="mt-1 text-[10px] <?= $open ? 'text-emerald-400' : 'text-slate-600' ?>">
                    <?= $open ? '✅ Terbuka ' . e(date('d M Y', strtotime((string) $b['unlocked_at']))) : '🔒 Terkunci' ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
