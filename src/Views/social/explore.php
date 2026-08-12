<?php /** 🧭 JELAJAHI — grid semua foto/video komunitas (Explore ala Instagram) */ ?>
<section class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-6">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-3xl sm:text-4xl font-bold text-white">🧭 Jelajahi <span class="text-neon-cyan">Kreator</span></h1>
        <p class="mt-2 text-sm text-slate-400">Galeri foto & video terbaik member ChiperX — temukan yang lagi viral! 🔥</p>
    </header>

    <?php if (empty($posts)): ?>
        <div class="glass-card p-10 text-center text-slate-500" data-anim="zoom">Belum ada media — jadilah yang pertama upload! 📸</div>
    <?php else: ?>
        <div class="grid grid-cols-3 gap-1.5 sm:gap-2.5" data-anim="fade-up">
            <?php foreach ($posts as $p): ?>
                <?php $isVid = (bool) preg_match('/\.(mp4|webm)$/', (string) $p['image']); ?>
                <a href="/komunitas#p<?= (int) $p['id'] ?>" class="group relative aspect-square rounded-xl overflow-hidden border border-white/5 bg-slate-900">
                    <?php if ($isVid): ?>
                        <video src="/media/social/<?= e($p['image']) ?>" muted playsinline preload="metadata" class="w-full h-full object-cover"></video>
                        <span class="absolute top-1.5 right-1.5 text-[10px] px-1.5 py-0.5 rounded-md bg-black/60 text-white">🎬</span>
                    <?php else: ?>
                        <img src="/media/social/<?= e($p['image']) ?>" alt="media" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                    <?php endif; ?>
                    <span class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition grid place-items-center text-center p-2">
                        <span class="text-xs font-bold text-white">❤️ <?= e(singkat((int) $p['likes_count'])) ?> · 💬 <?= e(singkat((int) $p['comments_count'])) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="text-center text-xs text-slate-600">💡 Upload fotomu di <a href="/komunitas" class="text-neon-cyan hover:underline">Komunitas</a> biar muncul di sini!</p>
</section>
