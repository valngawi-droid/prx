<?php /** FEED KOMUNITAS — Stories 24 jam + posting teks/foto + ❤️ + komentar + share (ala IG/FB/WA) */ ?>
<?php
$canPost  = $me !== null;
$canMod   = $me && in_array($me['role'] ?? 'user', ['admin', 'owner'], true);
$myId     = (int) ($me['id'] ?? 0);
$palettes = [
    'from-violet-500 to-fuchsia-500', 'from-cyan-500 to-blue-500', 'from-emerald-500 to-teal-500',
    'from-amber-500 to-orange-500', 'from-rose-500 to-pink-500', 'from-indigo-500 to-violet-500',
];
$avatar = static function (array $u) use ($palettes): string {
    $grad = $palettes[((int) ($u['user_id'] ?? $u['id'] ?? 0)) % count($palettes)];
    $vip  = user_is_vip($u) ? ' ring-2 ring-amber-400 shadow-[0_0_14px_rgba(251,191,36,.5)]' : '';
    return '<a href="/profil/@' . e(rawurlencode((string) $u['username'])) . '" class="shrink-0 w-10 h-10 rounded-full bg-gradient-to-br ' . $grad . $vip . ' grid place-items-center font-bold text-slate-900 text-sm shadow-lg">'
        . e(strtoupper(mb_substr((string) $u['name'], 0, 1))) . '</a>';
};
// Nama dengan flair VIP emas
$nama = static function (array $u, string $class = 'hover:text-neon-cyan transition'): string {
    $label = e($u['name']);
    if (user_is_vip($u)) {
        return '<a href="/profil/@' . e(rawurlencode((string) $u['username'])) . '" class="bg-gradient-to-r from-amber-300 via-yellow-400 to-amber-300 bg-clip-text text-transparent font-bold drop-shadow-[0_0_8px_rgba(251,191,36,.45)]">' . $label . ' 💎</a>';
    }
    return '<a href="/profil/@' . e(rawurlencode((string) $u['username'])) . '" class="' . $class . '">' . $label . '</a>';
};
?>
<section class="max-w-2xl mx-auto px-4 sm:px-6 py-8 sm:py-14">
    <header class="text-center mb-6" data-anim="fade-up">
        <h1 class="font-display text-3xl sm:text-4xl font-bold text-white">💬 Feed <span class="text-neon-cyan">Komunitas</span></h1>
        <p class="mt-2 text-sm text-slate-400">Story 24 jam · posting · ❤️ · komentar — khas ChiperX.</p>
    </header>

    <!-- ======================== STORIES BAR (24 jam) ======================== -->
    <?php if ($canPost || !empty($stories)): ?>
    <div class="mb-7 -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto" data-anim="fade-up" style="scrollbar-width:none;">
        <div class="flex gap-3 pb-1" style="min-width:max-content;">
            <?php if ($canPost): ?>
                <div class="flex flex-col items-center gap-1.5 w-[68px] shrink-0">
                    <form method="post" action="/komunitas/story" enctype="multipart/form-data" id="storyForm">
                        <?= csrf_field() ?>
                        <label class="relative block w-[60px] h-[60px] rounded-full cursor-pointer" title="Buat Story 24 jam">
                            <span class="w-full h-full rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 border-2 border-dashed border-white/30"><?= e(strtoupper(mb_substr((string) $me['name'], 0, 1))) ?></span>
                            <span class="absolute bottom-0 right-0 w-5 h-5 rounded-full bg-cyan-400 text-slate-900 text-sm font-black grid place-items-center border-2 border-ink leading-none">＋</span>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="if(this.files[0])document.getElementById('storyForm').submit();">
                            <input type="hidden" name="caption" value="">
                        </label>
                    </form>
                    <span class="text-[10px] text-slate-500 text-center leading-tight">Story<br>Kamu</span>
                </div>
            <?php endif; ?>
            <?php foreach ($stories as $st): ?>
                <a href="/story/<?= e(rawurlencode((string) $st['username'])) ?>" class="flex flex-col items-center gap-1.5 w-[68px] shrink-0 group" title="Story <?= e($st['name']) ?>">
                    <span class="w-[60px] h-[60px] rounded-full p-[3px] bg-gradient-to-tr from-violet-500 via-fuchsia-500 to-cyan-400 shadow-[0_0_16px_rgba(168,85,247,.4)] group-hover:scale-105 transition">
                        <img src="/media/social/<?= e($st['image']) ?>" alt="story" loading="lazy" class="w-full h-full rounded-full object-cover border-2 border-ink">
                    </span>
                    <span class="text-[10px] text-slate-400 max-w-full truncate"><?= e(explode(' ', (string) $st['name'])[0]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canPost): ?>
        <!-- ======================== KOMPOSER POSTINGAN ======================== -->
        <form method="post" action="/komunitas/post" enctype="multipart/form-data" class="glass-card p-4 sm:p-5 mb-8" data-anim="fade-up">
            <?= csrf_field() ?>
            <div class="flex gap-3">
                <span class="shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 text-sm"><?= e(strtoupper(mb_substr((string) $me['name'], 0, 1))) ?></span>
                <textarea name="body" rows="2" maxlength="500" placeholder="Lagi apa, <?= e(explode(' ', (string) $me['name'])[0]) ?>? Bagikan ke member lain… ✨"
                          class="form-input flex-1 text-sm resize-none"></textarea>
            </div>
            <div class="flex items-center justify-between mt-3 pl-[3.25rem] gap-2 flex-wrap">
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer hover:text-neon-cyan transition">
                    📷 <span id="imgLabel">Tambah foto</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="document.getElementById('imgLabel').textContent=this.files[0]?this.files[0].name.slice(0,22):'Tambah foto'">
                </label>
                <button class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold hover:opacity-90 transition shadow-lg shadow-violet-600/25">Kirim 🚀</button>
            </div>
        </form>
    <?php else: ?>
        <div class="glass-card p-5 mb-8 text-center" data-anim="fade-up">
            <p class="text-sm text-slate-300">Ingin ikut posting, ❤️, dan berkomentar?</p>
            <a href="/login" class="mt-3 inline-block px-6 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold">Masuk Dulu Yuk 🚀</a>
        </div>
    <?php endif; ?>

    <!-- ============================ DAFTAR POSTINGAN ============================ -->
    <?php if (empty($posts)): ?>
        <p class="glass-card p-10 text-center text-slate-500">Belum ada postingan — jadilah yang pertama! ✨</p>
    <?php endif; ?>

    <div class="space-y-5">
        <?php foreach ($posts as $p): $pid = (int) $p['id']; ?>
            <article id="p<?= $pid ?>" class="glass-card overflow-hidden scroll-mt-24" data-anim="fade-up">
                <!-- kepala post -->
                <div class="flex items-center gap-3 p-4 sm:p-5 pb-3">
                    <?= $avatar($p) ?>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-white text-sm truncate flex items-center gap-1.5 flex-wrap">
                            <?= $nama($p) ?>
                            <?= user_badges(['role' => $p['role'], 'badges' => $p['badges'] ?? '']) ?>
                        </p>
                        <p class="text-[11px] text-slate-500">@<?= e($p['username']) ?> · <?= e(waktu_lalu((string) $p['created_at'])) ?></p>
                    </div>
                    <?php if ($me && ((int) $p['user_id'] === $myId || $canMod)): ?>
                        <form method="post" action="/komunitas/<?= $pid ?>/delete" onsubmit="return confirm('Hapus postingan ini?')">
                            <?= csrf_field() ?>
                            <button title="Hapus" class="text-slate-600 hover:text-red-400 transition text-sm px-2 py-1">🗑️</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- isi -->
                <p class="px-4 sm:px-5 pb-3 text-sm text-slate-200 whitespace-pre-line break-words"><?= e($p['body']) ?></p>
                <?php if (!empty($p['image'])): ?>
                    <img src="/media/social/<?= e($p['image']) ?>" alt="Foto postingan" loading="lazy"
                         class="w-full max-h-[26rem] object-cover border-y border-white/5">
                <?php endif; ?>

                <!-- aksi ❤️ 💬 ↗️ -->
                <div class="flex items-center gap-1 px-3 sm:px-4 py-2">
                    <?php if ($canPost): ?>
                        <form method="post" action="/komunitas/<?= $pid ?>/like">
                            <?= csrf_field() ?>
                            <button class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm transition <?= $p['liked'] ? 'text-rose-400' : 'text-slate-400 hover:text-rose-300' ?>">
                                <?= $p['liked'] ? '❤️' : '🤍' ?> <b><?= e(singkat((int) $p['likes_count'])) ?></b>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="/login" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm text-slate-400">🤍 <b><?= e(singkat((int) $p['likes_count'])) ?></b></a>
                    <?php endif; ?>
                    <a href="#p<?= $pid ?>" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-neon-cyan transition">💬 <b><?= e(singkat((int) $p['comments_count'])) ?></b></a>
                    <button type="button" title="Bagikan" onclick="const b=this;navigator.clipboard&&navigator.clipboard.writeText(location.origin+'/komunitas#p<?= $pid ?>').then(()=>{b.classList.add('text-emerald-400');setTimeout(()=>b.classList.remove('text-emerald-400'),1200)})" class="px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-emerald-300 transition">↗️</button>
                    <a href="/pesan/<?= e(rawurlencode((string) $p['username'])) ?>" title="Kirim DM" class="ml-auto px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-neon-purple transition">✉️</a>
                </div>

                <!-- komentar -->
                <?php $clist = $comments[$pid] ?? []; ?>
                <?php if (!empty($clist)): ?>
                    <div class="px-4 sm:px-5 pb-3 space-y-2 border-t border-white/5 pt-3">
                        <?php foreach (array_slice($clist, -5) as $c): ?>
                            <div class="flex items-start gap-2 text-sm">
                                <?php if (user_is_vip($c)): ?>
                                    <a href="/profil/@<?= e(rawurlencode((string) $c['username'])) ?>" class="shrink-0 font-bold bg-gradient-to-r from-amber-300 via-yellow-400 to-amber-300 bg-clip-text text-transparent"><?= e($c['name']) ?> 💎</a>
                                <?php else: ?>
                                    <a href="/profil/@<?= e(rawurlencode((string) $c['username'])) ?>" class="shrink-0 font-semibold text-neon-cyan hover:underline"><?= e($c['name']) ?></a>
                                <?php endif; ?>
                                <p class="text-slate-300 break-words flex-1 min-w-0"><?= e($c['body']) ?>
                                    <span class="block text-[10px] text-slate-600"><?= e(waktu_lalu((string) $c['created_at'])) ?></span>
                                </p>
                                <?php if ($me && ((int) $c['user_id'] === $myId || (int) $p['user_id'] === $myId || $canMod)): ?>
                                    <form method="post" action="/komunitas/komentar/<?= (int) $c['id'] ?>/delete" onsubmit="return confirm('Hapus komentar ini?')">
                                        <?= csrf_field() ?>
                                        <button class="text-slate-700 hover:text-red-400 text-xs px-1">✕</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($clist) > 5): ?>
                            <p class="text-[11px] text-slate-600">+<?= count($clist) - 5 ?> komentar lainnya…</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- form komentar -->
                <?php if ($canPost): ?>
                    <form method="post" action="/komunitas/<?= $pid ?>/comment" class="flex items-center gap-2 px-4 sm:px-5 py-3 border-t border-white/5">
                        <?= csrf_field() ?>
                        <input name="body" maxlength="300" required placeholder="Tulis komentar…" class="form-input flex-1 text-sm py-2">
                        <button class="text-neon-cyan text-sm font-bold px-2 py-2 hover:opacity-80 transition">Kirim</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="text-center text-xs text-slate-600 mt-10">💡 Lihat member lain di <a href="/members" class="text-neon-cyan hover:underline">halaman Members</a> · kirim pesan pribadi lewat tombol ✉️</p>
</section>
