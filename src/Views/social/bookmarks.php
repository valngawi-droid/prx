<?php /** TERSIMPAN — semua postingan yang kamu bookmark 🔖 */ ?>
<section class="max-w-2xl mx-auto space-y-6">
    <header class="flex items-center gap-3" data-anim="fade-up">
        <span class="text-3xl">🔖</span>
        <div>
            <h1 class="font-display text-2xl font-bold text-white">Postingan Tersimpan</h1>
            <p class="text-xs text-slate-500"><?= count($posts) ?> postingan kamu simpan — hanya kamu yang bisa lihat.</p>
        </div>
    </header>

    <?php if (empty($posts)): ?>
        <div class="glass-card p-10 text-center" data-anim="zoom">
            <p class="text-4xl mb-3">📑</p>
            <p class="text-slate-400 text-sm">Belum ada yang disimpan. Ketuk ikon 📑 di postingan komunitas untuk menyimpannya di sini!</p>
            <a href="/komunitas" class="mt-4 inline-block px-6 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold">Jelajahi Komunitas →</a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($posts as $p): $pid = (int) $p['id']; ?>
                <article class="glass-card p-4 sm:p-5" data-anim="fade-up">
                    <div class="flex items-center gap-3 mb-2">
                        <a href="/profil/@<?= e(rawurlencode((string) $p['username'])) ?>" class="shrink-0 w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 text-xs overflow-hidden">
                            <?php if (!empty($p['avatar'])): ?><img src="/media/avatar/<?= e((string) $p['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $p['name'], 0, 1))) ?><?php endif; ?>
                        </a>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-white truncate flex items-center gap-1.5">
                                <a href="/profil/@<?= e(rawurlencode((string) $p['username'])) ?>" class="hover:text-neon-cyan transition"><?= e($p['name']) ?></a>
                                <?= user_badges(['role' => $p['role'] ?? 'user', 'badges' => $p['badges'] ?? '']) ?>
                            </p>
                            <p class="text-[11px] text-slate-500"><?= e(waktu_lalu((string) $p['created_at'])) ?></p>
                        </div>
                        <form method="post" action="/komunitas/<?= $pid ?>/simpan" title="Hapus dari tersimpan">
                            <?= csrf_field() ?>
                            <button class="text-amber-300 text-lg px-2 py-1 hover:scale-110 transition" title="Hapus dari Tersimpan">🔖</button>
                        </form>
                    </div>
                    <a href="/komunitas#p<?= $pid ?>" class="block group">
                        <p class="text-sm text-slate-200 whitespace-pre-line break-words"><?= e($p['body']) ?></p>
                        <?php if (!empty($p['image'])): ?>
                            <img src="/media/social/<?= e($p['image']) ?>" alt="foto" loading="lazy" class="mt-2 w-full max-h-64 object-cover rounded-xl border border-white/5 group-hover:opacity-90 transition">
                        <?php endif; ?>
                        <p class="mt-2 text-[11px] text-slate-500">❤️ <?= e(singkat((int) $p['likes_count'])) ?> · 💬 <?= e(singkat((int) $p['comments_count'])) ?> · <span class="text-neon-cyan group-hover:underline">buka di komunitas →</span></p>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
