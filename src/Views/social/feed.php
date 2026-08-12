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
    $cls  = 'shrink-0 w-10 h-10 rounded-full bg-gradient-to-br ' . $grad . $vip . ' grid place-items-center font-bold text-slate-900 text-sm shadow-lg overflow-hidden relative';
    $ava  = trim((string) ($u['avatar'] ?? ''));
    $isi  = $ava !== ''
        ? '<img src="/media/avatar/' . e($ava) . '" alt="" loading="lazy" class="w-full h-full object-cover">'
        : e(strtoupper(mb_substr((string) $u['name'], 0, 1)));
    return '<a href="/profil/@' . e(rawurlencode((string) $u['username'])) . '" class="' . $cls . '">' . $isi . '</a>';
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
        <p class="mt-2 text-xs text-slate-500 flex items-center justify-center gap-3 flex-wrap">
            <a href="/jelajahi" class="px-3 py-1.5 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 hover:text-white transition">🧭 Jelajahi</a>
            <a href="/kanal" class="px-3 py-1.5 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 hover:text-white transition">📢 Kanal</a>
            <a href="/cari" class="px-3 py-1.5 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 hover:text-white transition">🔎 Cari</a>
        </p>
    </header>

    <?php if (!empty($banner)): ?>
    <!-- 📢 Pengumuman resmi + reaksi (gaya Telegram channel) -->
    <div id="pengumuman" class="glass-card p-4 sm:p-5 mb-6 border-amber-400/30 relative overflow-hidden" data-anim="fade-up">
        <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full bg-amber-500/15 blur-3xl"></div>
        <p class="text-[10px] uppercase tracking-widest font-bold <?= $banner['level'] === 'warning' ? 'text-amber-300' : ($banner['level'] === 'success' ? 'text-emerald-300' : 'text-cyan-300') ?>">📢 PENGUMUMAN RESMI</p>
        <h2 class="font-display font-bold text-white mt-1"><?= e($banner['title']) ?></h2>
        <p class="text-sm text-slate-300 mt-1 whitespace-pre-line"><?= e(mb_substr((string) $banner['body'], 0, 220)) ?></p>
        <div class="mt-3 flex items-center gap-1.5 flex-wrap">
            <?php foreach (['👍', '🔥', '🎉', '❤️'] as $em): $rx = null; foreach (($bannerReacts ?? []) as $rr) { if ($rr['emoji'] === $em) { $rx = $rr; break; } } ?>
                <?php if ($canPost): ?>
                    <form method="post" action="/pengumuman/<?= (int) $banner['id'] ?>/reaksi" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="emoji" value="<?= $em ?>">
                        <button class="px-2.5 py-1 rounded-xl text-sm border transition <?= $rx && $rx['mine'] ? 'bg-amber-500/20 border-amber-400/50' : 'bg-white/[0.04] border-white/10 hover:bg-white/[0.09]' ?>"><?= $em ?><?= $rx ? ' <b class="text-[11px]">' . (int) $rx['c'] . '</b>' : '' ?></button>
                    </form>
                <?php else: ?>
                    <span class="px-2.5 py-1 rounded-xl text-sm bg-white/[0.04] border border-white/10"><?= $em ?><?= $rx ? ' <b class="text-[11px]">' . (int) $rx['c'] . '</b>' : '' ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                <span class="shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 text-sm overflow-hidden"><?php if (!empty($me['avatar'])): ?><img src="/media/avatar/<?= e((string) $me['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $me['name'], 0, 1))) ?><?php endif; ?></span>
                <textarea name="body" rows="2" maxlength="500" placeholder="Lagi apa, <?= e(explode(' ', (string) $me['name'])[0]) ?>? Bagikan ke member lain… ✨"
                          class="form-input flex-1 text-sm resize-none"></textarea>
            </div>
            <div class="flex items-center justify-between mt-3 pl-[3.25rem] gap-2 flex-wrap">
                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer hover:text-neon-cyan transition">
                    📷 <span id="imgLabel">Tambah foto/video</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" class="hidden" onchange="document.getElementById('imgLabel').textContent=this.files[0]?this.files[0].name.slice(0,22):'Tambah foto/video'">
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
                        <div class="flex items-center gap-1">
                            <?php if ((int) $p['user_id'] === $myId): ?>
                                <!-- 📥 Arsipkan — sembunyikan dari publik tanpa menghapus (gaya IG) -->
                                <form method="post" action="/komunitas/<?= $pid ?>/arsip" title="<?= !empty($p['is_archived']) ? 'Pulihkan ke publik' : 'Arsipkan (sembunyikan dari publik)' ?>">
                                    <?= csrf_field() ?>
                                    <button class="text-slate-600 hover:text-cyan-300 transition text-sm px-1.5 py-1"><?= !empty($p['is_archived']) ? '📤' : '📥' ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="/komunitas/<?= $pid ?>/delete" onsubmit="return confirm('Hapus postingan ini?')">
                                <?= csrf_field() ?>
                                <button title="Hapus" class="text-slate-600 hover:text-red-400 transition text-sm px-1.5 py-1">🗑️</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- isi -->
                <p class="px-4 sm:px-5 pb-3 text-sm text-slate-200 whitespace-pre-line break-words"><?= render_chat($p['body']) ?></p>
                <?php if (!empty($p['image'])): ?>
                    <?php $isVid = (bool) preg_match('/\.(mp4|webm)$/', (string) $p['image']); ?>
                    <?php if (!empty($p['nsfw'])): ?>
                        <!-- 🔞 NSFW: blur sampai diklik -->
                        <div class="relative border-y border-white/5 overflow-hidden cursor-pointer" onclick="this.querySelector('.nsfw-layer').classList.add('hidden');this.querySelector('.nsfw-media').classList.remove('blur-2xl','scale-110')">
                            <<?= $isVid ? 'video controls playsinline' : 'img' ?> src="/media/social/<?= e($p['image']) ?>" <?= $isVid ? '' : 'loading="lazy" alt="Konten sensitif"' ?>
                                 class="nsfw-media w-full max-h-[26rem] object-cover blur-2xl scale-110 transition duration-500"></<?= $isVid ? 'video' : 'img' ?>>
                            <div class="nsfw-layer absolute inset-0 grid place-items-center bg-ink/60 backdrop-blur-sm">
                                <span class="px-4 py-2.5 rounded-2xl bg-red-500/15 border border-red-400/40 text-center">
                                    <span class="block text-lg">🔞</span>
                                    <span class="block text-xs font-bold text-red-300">Konten Sensitif / NSFW</span>
                                    <span class="block text-[10px] text-slate-400 mt-0.5">Ketuk untuk menampilkan</span>
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- 💗 double-tap untuk SUKA (gaya Instagram) · klik 1x = perbesar -->
                        <div class="relative border-y border-white/5 post-media select-none" data-post="<?= $pid ?>" ondblclick="dblLike(this)" onclick="openLightbox(this.querySelector('img,video').currentSrc||this.querySelector('img,video').src, <?= $isVid ? 'true' : 'false' ?>)">
                            <?php if ($isVid): ?>
                                <video src="/media/social/<?= e($p['image']) ?>" controls playsinline preload="metadata" onclick="event.stopPropagation()" class="w-full max-h-[26rem] object-contain bg-black"></video>
                            <?php else: ?>
                                <img src="/media/social/<?= e($p['image']) ?>" alt="Foto postingan" loading="lazy" class="w-full max-h-[26rem] object-cover">
                            <?php endif; ?>
                            <span class="like-heart pointer-events-none absolute inset-0 grid place-items-center text-7xl opacity-0 transition">❤️</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- aksi ❤️ 💬 ↗️ 🔖 -->
                <div class="flex items-center gap-1 px-3 sm:px-4 py-2">
                    <?php if ($canPost): ?>
                        <form method="post" action="/komunitas/<?= $pid ?>/like" id="likeForm<?= $pid ?>" class="like-form" data-pid="<?= $pid ?>">
                            <?= csrf_field() ?>
                            <button class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm transition <?= $p['liked'] ? 'text-rose-400' : 'text-slate-400 hover:text-rose-300' ?>">
                                <?= $p['liked'] ? '❤️' : '🤍' ?> <b class="like-count"><?= e(singkat((int) $p['likes_count'])) ?></b>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="/login" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm text-slate-400">🤍 <b><?= e(singkat((int) $p['likes_count'])) ?></b></a>
                    <?php endif; ?>
                    <!-- ❤️ daftar penyuka (gaya IG) -->
                    <a href="/komunitas/<?= $pid ?>/suka" title="Lihat yang menyukai" class="text-[10px] text-slate-500 hover:text-rose-300 transition -ml-1 pr-1 underline decoration-dotted">suka</a>
                    <a href="#p<?= $pid ?>" class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-neon-cyan transition">💬 <b><?= e(singkat((int) $p['comments_count'])) ?></b></a>
                    <button type="button" title="Bagikan" onclick="const b=this;navigator.clipboard&&navigator.clipboard.writeText(location.origin+'/komunitas#p<?= $pid ?>').then(()=>{b.classList.add('text-emerald-400');setTimeout(()=>b.classList.remove('text-emerald-400'),1200)})" class="px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-emerald-300 transition">↗️</button>
                    <?php if ($canPost): ?>
                        <!-- 🔖 Simpan postingan (bookmark) — ada di halaman Tersimpan -->
                        <form method="post" action="/komunitas/<?= $pid ?>/simpan" class="ml-auto">
                            <?= csrf_field() ?>
                            <button title="<?= !empty($p['saved']) ? 'Hapus dari Tersimpan' : 'Simpan postingan' ?>" class="px-3 py-2 rounded-xl text-sm transition <?= !empty($p['saved']) ? 'text-amber-300' : 'text-slate-400 hover:text-amber-300' ?>">
                                <?= !empty($p['saved']) ? '🔖' : '📑' ?>
                            </button>
                        </form>
                        <a href="/pesan/<?= e(rawurlencode((string) $p['username'])) ?>" title="Kirim DM" class="px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-neon-purple transition">✉️</a>
                    <?php else: ?>
                        <a href="/pesan/<?= e(rawurlencode((string) $p['username'])) ?>" title="Kirim DM" class="ml-auto px-3 py-2 rounded-xl text-sm text-slate-400 hover:text-neon-purple transition">✉️</a>
                    <?php endif; ?>
                </div>

                <!-- komentar -->
                <?php $clist = $comments[$pid] ?? []; ?>
                <?php if (!empty($clist)): ?>
                    <div class="px-4 sm:px-5 pb-3 space-y-2 border-t border-white/5 pt-3">
                        <?php foreach (array_slice($clist, -5) as $c): ?>
                            <div class="flex items-start gap-2 text-sm">
                                <!-- 📸 avatar mini (gaya IG) -->
                                <a href="/profil/@<?= e(rawurlencode((string) $c['username'])) ?>" class="shrink-0 w-6 h-6 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center text-[9px] font-bold text-slate-900 overflow-hidden mt-0.5">
                                    <?php if (!empty($c['avatar'])): ?><img src="/media/avatar/<?= e((string) $c['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $c['name'], 0, 1))) ?><?php endif; ?>
                                </a>
                                <p class="text-slate-300 break-words flex-1 min-w-0">
                                    <?php if (user_is_vip($c)): ?>
                                        <a href="/profil/@<?= e(rawurlencode((string) $c['username'])) ?>" class="font-bold bg-gradient-to-r from-amber-300 via-yellow-400 to-amber-300 bg-clip-text text-transparent"><?= e($c['name']) ?> 💎</a>
                                    <?php else: ?>
                                        <a href="/profil/@<?= e(rawurlencode((string) $c['username'])) ?>" class="font-semibold text-neon-cyan hover:underline"><?= e($c['name']) ?></a>
                                    <?php endif; ?>
                                    <?php if (!empty($c['parent_id']) && !empty($c['parent_username'])): ?>
                                        <span class="text-[10px] text-slate-500">↩️ membalas <a href="/profil/@<?= e(rawurlencode((string) $c['parent_username'])) ?>" class="text-violet-300 hover:underline">@<?= e($c['parent_username']) ?></a></span>
                                    <?php endif; ?>
                                    <?= render_chat($c['body']) ?>
                                    <span class="block text-[10px] text-slate-600 mt-0.5 flex items-center gap-2"><?= e(waktu_lalu((string) $c['created_at'])) ?>
                                        <?php if ($canPost): ?>
                                            <button type="button" class="text-slate-500 hover:text-neon-cyan transition" onclick="balasKomentar(<?= $pid ?>, <?= (int) $c['id'] ?>, '<?= e((string) $c['username']) ?>')">↩️ balas</button>
                                        <?php endif; ?>
                                    </span>
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

                <!-- form komentar (dukung ↩️ balas + @mention) -->
                <?php if ($canPost): ?>
                    <div class="border-t border-white/5">
                        <div id="replyInfo<?= $pid ?>" class="hidden px-4 pt-2 text-[11px] text-slate-400 flex items-center justify-between gap-2">
                            <span id="replyLbl<?= $pid ?>" class="truncate"></span>
                            <button type="button" class="text-red-400" onclick="balasKomentar(<?= $pid ?>, 0, '')">✕ batal</button>
                        </div>
                        <form method="post" action="/komunitas/<?= $pid ?>/comment" class="flex items-center gap-2 px-4 sm:px-5 py-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="parent_id" id="parentId<?= $pid ?>" value="">
                            <input name="body" id="cmtInput<?= $pid ?>" maxlength="300" required placeholder="Tulis komentar… (bisa @sebutan)" class="form-input flex-1 text-sm py-2">
                            <button class="text-neon-cyan text-sm font-bold px-2 py-2 hover:opacity-80 transition">Kirim</button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <p class="text-center text-xs text-slate-600 mt-10">💡 Lihat member lain di <a href="/members" class="text-neon-cyan hover:underline">halaman Members</a> · kirim pesan pribadi lewat tombol ✉️</p>
</section>

<!-- 🔍 LIGHTBOX foto fullscreen (klik untuk tutup) -->
<div id="lightbox" class="hidden fixed inset-0 z-[70] bg-black/90 backdrop-blur-sm grid place-items-center p-4 cursor-zoom-out" onclick="this.classList.add('hidden')">
    <img id="lightboxImg" src="" alt="perbesar" class="max-w-full max-h-full rounded-2xl border border-white/10 shadow-2xl object-contain">
</div>

<script>
// ↩️ Balas komentar (gaya Instagram)
function balasKomentar(pid, cid, username) {
    const hid = document.getElementById('parentId' + pid);
    const info = document.getElementById('replyInfo' + pid);
    const inp = document.getElementById('cmtInput' + pid);
    if (!hid) return;
    if (!cid) { hid.value = ''; info.classList.add('hidden'); return; }
    hid.value = cid;
    document.getElementById('replyLbl' + pid).textContent = '↩️ Membalas @' + username;
    info.classList.remove('hidden');
    inp.value = '@' + username + ' ';
    inp.focus();
}
// 💗 Double-tap pada media = SUKA (AJAX, tanpa reload!)
function dblLike(box) {
    clearTimeout(lbTimer); // batalkan pembukaan lightbox dari klik pertama
    const pid = box.dataset.post;
    const form = document.getElementById('likeForm' + pid);
    const heart = box.querySelector('.like-heart');
    if (heart) {
        heart.style.opacity = '1'; heart.style.transform = 'scale(1.2)';
        setTimeout(() => { heart.style.opacity = '0'; heart.style.transform = 'scale(1)'; }, 600);
    }
    if (!form) return;
    fetch(form.action, { method: 'POST', body: new FormData(form) }).then(() => {
        // sinkronkan tampilan tombol suka
        const btn = form.querySelector('button');
        const cnt = form.querySelector('.like-count');
        const on = btn.classList.toggle('text-rose-400');
        btn.classList.toggle('text-slate-400', !on);
        const cur = parseInt((cnt.textContent || '0').replace(/\D/g, '')) || 0;
        cnt.textContent = on ? (cur + 1) : Math.max(0, cur - 1);
        btn.querySelector(':scope > *:first-child');
        btn.childNodes[0].textContent = on ? '❤️' : '🤍';
    }).catch(() => form.submit());
}
// 🔍 Lightbox — klik 1x pada media = perbesar (video dibuka di tab baru)
let lbTimer = null;
function openLightbox(src, isVideo) {
    if (isVideo) return; // video punya kontrol sendiri
    clearTimeout(lbTimer);
    lbTimer = setTimeout(() => {
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightbox').classList.remove('hidden');
    }, 230); // tunda sedikit agar double-tap tak ikut membuka
}
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.getElementById('lightbox').classList.add('hidden'); });
</script>
