<?php /** STORY VIEWER — slide foto 24 jam ala Instagram/WhatsApp (auto-next 5 dtk) */ ?>
<?php
$tuser = rawurlencode((string) $target['username']);
$canDel = $me && ((int) $me['id'] === (int) $target['id'] || in_array($me['role'] ?? 'user', ['admin', 'owner'], true));
?>
<section class="max-w-md mx-auto px-4 py-6 sm:py-10">
    <div class="relative rounded-3xl overflow-hidden border border-white/10 bg-black/60 shadow-2xl shadow-violet-900/40" style="aspect-ratio:9/16;">
        <!-- pips progres -->
        <div class="absolute top-3 inset-x-3 z-20 flex gap-1.5">
            <?php foreach ($stories as $i => $s): ?>
                <span class="pip flex-1 h-0.5 rounded-full bg-white/25 overflow-hidden"><span class="pip-fill block h-full w-0 bg-white"></span></span>
            <?php endforeach; ?>
        </div>

        <!-- kepala -->
        <div class="absolute top-6 inset-x-0 z-20 flex items-center gap-2.5 px-3">
            <a href="/profil/@<?= e($tuser) ?>" class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 text-xs shrink-0 overflow-hidden"><?php if (!empty($target['avatar'] ?? '')): ?><img src="/media/avatar/<?= e((string) $target['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $target['name'], 0, 1))) ?><?php endif; ?></a>
            <div class="min-w-0 flex-1">
                <p class="text-white text-sm font-semibold truncate drop-shadow"><?= e($target['name']) ?> <?= user_is_vip($target) ? '💎' : '' ?></p>
                <p id="stTime" class="text-white/60 text-[10px]"></p>
            </div>
            <a href="/komunitas" class="text-white/70 hover:text-white text-xl px-2" title="Tutup">✕</a>
        </div>

        <!-- slide -->
        <div id="stWrap" class="absolute inset-0"></div>

        <!-- navigasi ketuk kiri/kanan -->
        <button id="stPrev" class="absolute left-0 top-0 bottom-0 w-1/3 z-10" aria-label="Sebelumnya"></button>
        <button id="stNext" class="absolute right-0 top-0 bottom-0 w-2/3 z-10" aria-label="Berikutnya"></button>

        <!-- caption -->
        <p id="stCaption" class="absolute bottom-5 inset-x-0 z-20 text-center text-white text-sm px-6 drop-shadow-lg whitespace-pre-line"></p>

        <!-- hapus -->
        <?php if ($canDel): ?>
            <form id="stDelForm" method="post" action="" class="absolute bottom-3 right-3 z-20" onsubmit="return confirm('Hapus story ini?')">
                <?= csrf_field() ?>
                <button class="text-white/60 hover:text-red-400 text-xs px-2 py-1 rounded-lg bg-black/40">🗑️ Hapus</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="text-center text-[11px] text-slate-600 mt-4">Story menghilang otomatis setelah 24 jam ⏰ · ketuk kanan untuk lanjut</p>

    <?php if ($me && (int) $me['id'] === (int) $target['id']): ?>
    <!-- 👀 Insight penonton (hanya pemilik story — gaya Instagram) -->
    <div class="glass-card mt-5 p-4" data-anim="fade-up">
        <p class="text-xs font-bold text-white mb-2.5">👀 Dilihat oleh <b class="text-neon-cyan"><?= count($viewers ?? []) ?></b> member</p>
        <?php if (!empty($viewers)): ?>
            <div class="space-y-2 max-h-40 overflow-y-auto">
                <?php foreach ($viewers as $v): ?>
                    <a href="/profil/@<?= e(rawurlencode((string) $v['username'])) ?>" class="flex items-center gap-2.5 text-xs hover:bg-white/5 rounded-lg px-1.5 py-1 transition">
                        <span class="w-6 h-6 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center text-[9px] font-bold text-slate-900 overflow-hidden shrink-0"><?php if (!empty($v['avatar'])): ?><img src="/media/avatar/<?= e((string) $v['avatar']) ?>" class="w-full h-full object-cover" alt=""><?php else: ?><?= e(strtoupper(mb_substr((string) $v['name'], 0, 1))) ?><?php endif; ?></span>
                        <span class="text-slate-300 truncate"><?= e($v['name']) ?></span>
                        <span class="ml-auto text-[10px] text-slate-600 shrink-0"><?= e(waktu_lalu((string) $v['viewed_at'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-[11px] text-slate-600">Belum ada yang lihat — bagikan link story-mu! 📣</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<script>
(() => {
    const stories = <?= json_encode(array_map(static fn($s) => [
        'id'      => (int) $s['id'],
        'img'     => '/media/social/' . $s['image'],
        'caption' => (string) ($s['caption'] ?? ''),
        'time'    => waktu_lalu((string) $s['created_at']),
    ], $stories), JSON_UNESCAPED_SLASHES) ?>;
    const wrap = document.getElementById('stWrap');
    const cap  = document.getElementById('stCaption');
    const tm   = document.getElementById('stTime');
    const pips = [...document.querySelectorAll('.pip .pip-fill')];
    const delF = document.getElementById('stDelForm');
    let idx = 0, timer = null;

    const show = (i) => {
        idx = Math.max(0, Math.min(stories.length - 1, i));
        const s = stories[idx];
        wrap.innerHTML = '';
        const img = document.createElement('img');
        img.src = s.img; img.alt = 'story';
        img.className = 'w-full h-full object-cover';
        img.style.animation = 'stIn .3s ease-out';
        wrap.appendChild(img);
        cap.textContent = s.caption;
        tm.textContent = s.time;
        pips.forEach((p, j) => {
            p.style.transition = 'none';
            p.style.width = j < idx ? '100%' : (j === idx ? '0%' : '0%');
            if (j === idx) { void p.offsetWidth; p.style.transition = 'width 5s linear'; p.style.width = '100%'; }
        });
        if (delF) delF.action = '/story/' + s.id + '/delete';
        clearTimeout(timer);
        timer = setTimeout(() => { idx + 1 < stories.length ? show(idx + 1) : (location.href = '/komunitas'); }, 5000);
    };
    document.getElementById('stNext').addEventListener('click', () => show(idx + 1));
    document.getElementById('stPrev').addEventListener('click', () => show(idx - 1));
    show(0);
})();
</script>
<style>@keyframes stIn{from{opacity:.2;transform:scale(1.02)}to{opacity:1;transform:none}}</style>
