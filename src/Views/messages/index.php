<?php /** KOTAK MASUK — daftar percakapan DM (ala WhatsApp/Telegram) */ ?>
<section class="max-w-3xl mx-auto">
    <header class="flex items-center justify-between flex-wrap gap-3 mb-6" data-anim="fade-up">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-white">✉️ Pesan</h1>
            <p class="text-xs text-slate-500 mt-1">Chat pribadi 1-lawan-1 dengan member lain — privat & real-time.</p>
        </div>
        <a href="/members" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition">＋ Chat Baru</a>
    </header>

    <?php if (empty($convs)): ?>
        <div class="glass-card p-10 text-center" data-anim="fade-up">
            <p class="text-5xl mb-3">📭</p>
            <p class="text-slate-400 text-sm">Belum ada percakapan.</p>
            <p class="text-xs text-slate-600 mt-2">Pilih member di <a href="/members" class="text-neon-cyan hover:underline">halaman Members</a> atau dari postingan di <a href="/komunitas" class="text-neon-cyan hover:underline">Komunitas</a> lalu ketuk ✉️ untuk memulai chat.</p>
        </div>
    <?php endif; ?>

    <div class="glass-card divide-y divide-white/5 overflow-hidden" data-anim="fade-up">
        <?php foreach ($convs as $c): ?>
            <a href="/pesan/<?= e(rawurlencode((string) $c['username'])) ?>" class="flex items-center gap-3 p-4 hover:bg-white/[0.04] transition">
                <span class="relative shrink-0 w-11 h-11 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 overflow-hidden">
                    <?php if (!empty($c['avatar'])): ?><img src="/media/avatar/<?= e((string) $c['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $c['name'], 0, 1))) ?><?php endif; ?>
                    <?php if (($c['last_activity'] ?? null) && (time() - strtotime((string) $c['last_activity']) < 180)): ?>
                        <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-400 border-2 border-slate-900" title="Online"></span>
                    <?php endif; ?>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-1.5">
                        <?php if (user_is_vip($c)): ?>
                            <b class="text-sm truncate bg-gradient-to-r from-amber-300 via-yellow-400 to-amber-300 bg-clip-text text-transparent"><?= e($c['name']) ?> 💎</b>
                        <?php else: ?>
                            <b class="text-sm text-white truncate"><?= e($c['name']) ?></b>
                        <?php endif; ?>
                        <?= user_badges(['role' => $c['role'], 'badges' => $c['badges'] ?? '']) ?>
                    </span>
                    <span class="block text-xs <?= (int) $c['unread'] > 0 ? 'text-cyan-300 font-semibold' : 'text-slate-500' ?> truncate"><?= e(mb_substr((string) ($c['last_body'] ?? ''), 0, 48)) ?></span>
                </span>
                <span class="shrink-0 text-right">
                    <span class="block text-[10px] text-slate-600"><?= $c['last_at'] ? e(waktu_lalu((string) $c['last_at'])) : '' ?></span>
                    <?php if ((int) $c['unread'] > 0): ?>
                        <span class="mt-1 inline-grid place-items-center min-w-[20px] h-5 px-1.5 rounded-full bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-[10px] font-bold"><?= (int) $c['unread'] > 99 ? '99+' : (int) $c['unread'] ?></span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
