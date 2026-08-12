<?php /** TOOLS — katalog 30+ alat AI/Downloader/Maker/Stalker */ ?>
<?php
$order = ['AI', 'Canvas/Maker', 'Downloader', 'HD Enhancer', 'Fake Maker', 'Stalker', 'Tools'];
$iconCat = [
    'AI' => '🤖', 'Canvas/Maker' => '🎨', 'Downloader' => '⬇️', 'HD Enhancer' => '🖼️',
    'Fake Maker' => '🎭', 'Stalker' => '🕵️', 'Tools' => '🛠️',
];
$grouped = [];
foreach ($tools as $key => $t) {
    $grouped[$t['cat']][$key] = $t;
}
?>
<section class="space-y-8">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-white">🚀 Tools <span class="bg-gradient-to-r from-violet-400 to-cyan-400 bg-clip-text text-transparent">30+ Gratis</span></h1>
        <p class="text-xs sm:text-sm text-slate-400 mt-2 max-w-xl mx-auto">Chat AI · downloader TikTok/YouTube/Spotify · pembuat logo & quotes · HD-in foto/video · stalker FF/IG — semua tinggal ketuk! ✨</p>
    </header>

    <?php foreach ($order as $cat): if (empty($grouped[$cat])) continue; ?>
        <div data-anim="fade-up">
            <h2 class="font-display font-bold text-white text-sm uppercase tracking-widest mb-3 flex items-center gap-2">
                <span><?= $iconCat[$cat] ?? '📁' ?></span> <?= e($cat) ?>
                <span class="text-[10px] font-normal text-slate-600 normal-case tracking-normal"><?= count($grouped[$cat]) ?> alat</span>
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <?php foreach ($grouped[$cat] as $key => $t): ?>
                    <a href="/tools/<?= e($key) ?>" class="glass-card p-4 group hover:border-violet-500/50 hover:-translate-y-1 transition duration-200">
                        <span class="text-2xl block mb-2 group-hover:scale-110 transition origin-left"><?= $t['icon'] ?></span>
                        <p class="text-[13px] font-semibold text-white leading-snug"><?= e($t['name']) ?></p>
                        <p class="text-[10px] text-neon-cyan mt-1.5 font-semibold opacity-0 group-hover:opacity-100 transition">Buka alat →</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <p class="text-center text-[11px] text-slate-600">⚡ Didukung API alwayscodex.my.id · hasil maksimal tergantung kualitas sumber</p>
</section>
