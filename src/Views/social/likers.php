<?php /** ❤️ DISUKAI OLEH — daftar penyuka postingan (IG) */ ?>
<section class="max-w-md mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-5">
    <header class="flex items-center gap-3" data-anim="fade-up">
        <a href="/komunitas#p<?= (int) $post['id'] ?>" class="text-slate-400 hover:text-white transition px-1 py-1">←</a>
        <div class="min-w-0">
            <h1 class="font-display text-xl font-bold text-white">❤️ Disukai oleh</h1>
            <p class="text-[11px] text-slate-500 truncate">Postingan <?= e($post['name']) ?>: “<?= e(mb_substr((string) $post['body'], 0, 50)) ?>…”</p>
        </div>
    </header>

    <div class="glass-card divide-y divide-white/5 overflow-hidden" data-anim="fade-up">
        <?php if (empty($likers)): ?>
            <p class="p-8 text-center text-slate-500 text-sm">Belum ada yang menyukai.</p>
        <?php endif; ?>
        <?php foreach ($likers as $l): ?>
            <a href="/profil/@<?= e(rawurlencode((string) $l['username'])) ?>" class="flex items-center gap-3 p-3.5 hover:bg-white/[0.04] transition">
                <span class="shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-rose-500 to-pink-500 grid place-items-center font-bold text-white text-sm overflow-hidden">
                    <?php if (!empty($l['avatar'])): ?><img src="/media/avatar/<?= e((string) $l['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $l['name'], 0, 1))) ?><?php endif; ?>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-white truncate flex items-center gap-1.5"><?= e($l['name']) ?> <?= user_badges(['role' => $l['role'], 'badges' => $l['badges'] ?? '']) ?></span>
                    <span class="block text-[11px] text-slate-500">@<?= e($l['username']) ?></span>
                </span>
                <span class="text-rose-400">❤️</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
