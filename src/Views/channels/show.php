<?php /** 💬 RUANG KANAL gaya Discord — chat, reaksi, reply, pin, poll, slowmode */ ?>
<?php
$chs    = (string) $ch['slug'];
$emojis = $emojis ?? ['👍', '❤️', '🔥', '😂', '🎉'];
?>
<section class="max-w-3xl mx-auto flex flex-col" style="min-height:72vh;">
    <!-- kepala kanal -->
    <header class="glass-card p-4 mb-3 flex items-center gap-3" data-anim="fade-up">
        <a href="/kanal" class="text-slate-400 hover:text-white transition px-1">←</a>
        <span class="text-2xl"><?= $ch['kind'] === 'announce' ? '📣' : '<b class="text-neon-cyan">#</b>' ?></span>
        <div class="min-w-0 flex-1">
            <h1 class="font-display font-bold text-white text-base sm:text-lg truncate flex items-center gap-2">
                <?= e($ch['name']) ?>
                <?php if ($ch['kind'] === 'announce'): ?><span class="text-[9px] px-2 py-0.5 rounded-full bg-amber-500/15 border border-amber-400/30 text-amber-300">RESMI</span><?php endif; ?>
                <?php if ((int) $ch['slowmode'] > 0): ?><span class="text-[9px] px-2 py-0.5 rounded-full bg-white/5 border border-white/10 text-slate-400">🐌 slowmode <?= (int) $ch['slowmode'] ?> dtk</span><?php endif; ?>
            </h1>
            <?php if (!empty($ch['topic'])): ?><p class="text-[11px] text-slate-500 truncate"><?= e($ch['topic']) ?></p><?php endif; ?>
        </div>
        <?php if ($canMod): ?>
            <details class="relative shrink-0">
                <summary class="list-none cursor-pointer text-slate-400 hover:text-white text-lg px-2">⚙️</summary>
                <form method="post" action="/kanal/<?= e($chs) ?>/atur" class="absolute right-0 top-8 z-20 w-64 glass-card p-4 space-y-3 border border-violet-500/30">
                    <?= csrf_field() ?>
                    <p class="text-xs font-bold text-white">Pengaturan Kanal</p>
                    <input name="topic" value="<?= e((string) ($ch['topic'] ?? '')) ?>" maxlength="120" placeholder="Topik kanal…" class="form-input w-full text-xs">
                    <select name="slowmode" class="form-input w-full text-xs">
                        <?php foreach ([0 => '🚀 Slowmode mati', 10 => '🐌 10 detik', 30 => '🐌 30 detik', 60 => '🐌 1 menit', 300 => '🐌 5 menit'] as $v => $lab): ?>
                            <option value="<?= $v ?>" <?= (int) $ch['slowmode'] === $v ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="w-full py-2 rounded-lg bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-xs font-bold">Simpan ⚙️</button>
                </form>
            </details>
        <?php endif; ?>
    </header>

    <!-- 📌 PIN pesan -->
    <?php if ($pinned): ?>
        <div class="glass-card px-4 py-2.5 mb-3 border-amber-400/30 flex items-center gap-2 text-xs" data-anim="fade-up">
            <span class="text-amber-300">📌</span>
            <p class="text-slate-300 truncate flex-1"><b class="text-amber-200"><?= e($pinned['name']) ?>:</b> <?= e(mb_substr((string) $pinned['body'], 0, 90)) ?></p>
        </div>
    <?php endif; ?>

    <!-- daftar pesan -->
    <div id="chList" class="flex-1 glass-card p-4 space-y-4 overflow-y-auto" style="max-height:58vh;">
        <?php if (empty($messages)): ?>
            <p class="text-center text-slate-600 text-xs py-10">Belum ada pesan di kanal ini — mulai obrolannya! 👋</p>
        <?php endif; ?>
        <?php foreach ($messages as $m): $mid = (int) $m['id']; $isMine = $me && (int) $m['user_id'] === (int) $me['id']; ?>
            <div id="m<?= $mid ?>" class="group flex gap-3 scroll-mt-24" data-anim="fade-up">
                <a href="/profil/@<?= e(rawurlencode((string) $m['username'])) ?>" class="shrink-0 w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 text-xs overflow-hidden">
                    <?php if (!empty($m['avatar'])): ?><img src="/media/avatar/<?= e((string) $m['avatar']) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $m['name'], 0, 1))) ?><?php endif; ?>
                </a>
                <div class="min-w-0 flex-1">
                    <p class="text-xs flex items-center gap-1.5 flex-wrap">
                        <?php if (user_is_vip($m)): ?>
                            <a href="/profil/@<?= e(rawurlencode((string) $m['username'])) ?>" class="font-bold bg-gradient-to-r from-amber-300 via-yellow-400 to-amber-300 bg-clip-text text-transparent"><?= e($m['name']) ?> 💎</a>
                        <?php else: ?>
                            <a href="/profil/@<?= e(rawurlencode((string) $m['username'])) ?>" class="font-bold text-white hover:text-neon-cyan transition"><?= e($m['name']) ?></a>
                        <?php endif; ?>
                        <?= user_badges(['role' => $m['role'], 'badges' => $m['badges'] ?? '']) ?>
                        <span class="text-[10px] text-slate-600"><?= e(waktu_lalu((string) $m['created_at'])) ?><?= $m['edited_at'] ? ' · <i>(diedit ✏️)</i>' : '' ?></span>
                    </p>

                    <?php if (!empty($m['reply_to']) && !empty($m['reply_body'])): ?>
                        <!-- ↩️ kutipan balasan -->
                        <p class="mt-1 text-[11px] text-slate-500 border-l-2 border-violet-500/50 pl-2 truncate">↩️ <b class="text-slate-400"><?= e((string) ($m['reply_name'] ?? '')) ?></b>: <?= e(mb_substr((string) $m['reply_body'], 0, 70)) ?></p>
                    <?php endif; ?>

                    <?php if ($m['kind'] === 'poll' && !empty($m['poll_options'])): ?>
                        <!-- 📊 POLLING gaya Telegram/Discord -->
                        <div class="mt-2 rounded-xl bg-white/[0.04] border border-white/10 p-3.5 space-y-2 max-w-md">
                            <p class="font-bold text-white text-sm">📊 <?= e($m['body']) ?></p>
                            <?php $opts = (array) json_decode((string) $m['poll_options'], true); $tv = max(1, (int) $m['total_votes']); ?>
                            <?php foreach ($opts as $oi => $otext): $vc = (int) ($m['votes'][$oi] ?? 0); $pct = (int) round($vc / $tv * 100); $chosen = $m['my_vote'] === $oi; ?>
                                <form method="post" action="/kanal/<?= e($chs) ?>/vote/<?= $mid ?>" class="relative">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="opt" value="<?= $oi ?>">
                                    <button class="w-full text-left relative overflow-hidden rounded-lg border px-3 py-2 text-xs transition <?= $chosen ? 'border-cyan-400/60 bg-cyan-500/10' : 'border-white/10 bg-white/[0.03] hover:bg-white/[0.07]' ?>">
                                        <span class="absolute inset-y-0 left-0 bg-cyan-500/15" style="width:<?= $pct ?>%"></span>
                                        <span class="relative flex items-center justify-between gap-2">
                                            <span class="<?= $chosen ? 'text-cyan-200 font-bold' : 'text-slate-300' ?>"><?= $chosen ? '✓ ' : '' ?><?= e((string) $otext) ?></span>
                                            <span class="text-slate-500 font-mono"><?= $pct ?>% (<?= $vc ?>)</span>
                                        </span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                            <p class="text-[10px] text-slate-600">🗳️ <?= (int) $m['total_votes'] ?> suara — ketuk opsi untuk memilih</p>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-200 mt-0.5 whitespace-pre-line break-words"><?= render_chat($m['body']) ?></p>
                    <?php endif; ?>

                    <!-- 😀 aksi & reaksi -->
                    <div class="mt-1.5 flex items-center gap-1 flex-wrap">
                        <?php if ($me): ?>
                            <?php foreach ($emojis as $em): $ex = null; foreach ($m['reactions'] as $rr) { if ($rr['emoji'] === $em) { $ex = $rr; break; } } ?>
                                <form method="post" action="/kanal/<?= e($chs) ?>/reaksi/<?= $mid ?>" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="emoji" value="<?= $em ?>">
                                    <button class="px-2 py-1 rounded-lg text-xs border transition <?= $ex && $ex['mine'] ? 'bg-violet-500/20 border-violet-400/50' : 'bg-white/[0.03] border-white/10 hover:bg-white/[0.08]' ?>"><?= $em ?><?= $ex ? ' <b class="text-[10px]">' . (int) $ex['count'] . '</b>' : '' ?></button>
                                </form>
                            <?php endforeach; ?>
                            <button type="button" title="Balas" onclick="setReply(<?= $mid ?>, '<?= e(addslashes((string) $m['name'])) ?>')" class="px-2 py-1 rounded-lg text-[11px] text-slate-500 hover:bg-white/10 hover:text-white transition">↩️</button>
                            <?php if ($isMine && $m['kind'] === 'text'): ?>
                                <button type="button" title="Edit (≤15 mnt)" onclick="const b=prompt('Edit pesan:', this.dataset.body);if(b!==null&&b.trim()!==''){const f=document.getElementById('editForm<?= $mid ?>');f.querySelector('[name=body]').value=b;f.submit()}" data-body="<?= e($m['body']) ?>" class="px-2 py-1 rounded-lg text-[11px] text-slate-500 hover:bg-white/10 hover:text-white transition">✏️</button>
                                <form id="editForm<?= $mid ?>" method="post" action="/kanal/<?= e($chs) ?>/edit/<?= $mid ?>" class="hidden"><?= csrf_field() ?><input name="body"></form>
                            <?php endif; ?>
                            <?php if ($canMod): ?>
                                <form method="post" action="/kanal/<?= e($chs) ?>/pin/<?= $mid ?>" class="inline"><?= csrf_field() ?><button title="Pin pesan" class="px-2 py-1 rounded-lg text-[11px] text-slate-500 hover:bg-white/10 hover:text-amber-300 transition">📌</button></form>
                            <?php endif; ?>
                            <?php if ($isMine || $canMod): ?>
                                <form method="post" action="/kanal/<?= e($chs) ?>/hapus/<?= $mid ?>" class="inline" onsubmit="return confirm('Hapus pesan ini?')"><?= csrf_field() ?><button title="Hapus" class="px-2 py-1 rounded-lg text-[11px] text-slate-600 hover:bg-white/10 hover:text-red-400 transition">🗑️</button></form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- komponis -->
    <?php if ($canPost): ?>
        <form method="post" action="/kanal/<?= e($chs) ?>/post" id="kirim" class="mt-3 glass-card p-3 space-y-2">
            <?= csrf_field() ?>
            <input type="hidden" name="reply_to" id="replyTo" value="">
            <div id="replyBar" class="hidden text-[11px] text-slate-400 border-l-2 border-violet-500/60 pl-2 flex items-center justify-between gap-2">
                <span id="replyTxt" class="truncate"></span>
                <button type="button" onclick="setReply(null,'')" class="text-red-400 hover:underline shrink-0">✕ batal</button>
            </div>
            <div class="flex gap-2 items-center">
                <input name="body" id="chInput" maxlength="1000" autocomplete="off" placeholder="Tulis pesan di #<?= e($ch['name']) ?>…  (coba: *tebal* _miring_ @sebutan)" class="form-input flex-1 text-sm py-3">
                <button type="button" onclick="document.getElementById('pollBox').classList.toggle('hidden')" title="Buat polling" class="w-11 h-11 shrink-0 rounded-full bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition">📊</button>
                <button class="w-11 h-11 shrink-0 rounded-full bg-gradient-to-r from-violet-600 to-cyan-500 text-white grid place-items-center hover:opacity-90 transition shadow-lg shadow-violet-600/25" title="Kirim">➤</button>
            </div>
            <!-- 📊 kotak polling -->
            <div id="pollBox" class="hidden rounded-xl bg-white/[0.04] border border-white/10 p-3 space-y-2">
                <input type="hidden" name="mode" id="pollMode" value="text" disabled>
                <p class="text-xs font-bold text-white">📊 Polling baru <span class="text-slate-500 font-normal">(isi semua + kirim lewat tombol ➤)</span></p>
                <input name="question" maxlength="160" placeholder="Pertanyaan polling…" class="form-input w-full text-xs" disabled data-poll>
                <input name="opt1" maxlength="60" placeholder="Opsi 1 *" class="form-input w-full text-xs" disabled data-poll>
                <input name="opt2" maxlength="60" placeholder="Opsi 2 *" class="form-input w-full text-xs" disabled data-poll>
                <input name="opt3" maxlength="60" placeholder="Opsi 3 (opsional)" class="form-input w-full text-xs" disabled data-poll>
                <input name="opt4" maxlength="60" placeholder="Opsi 4 (opsional)" class="form-input w-full text-xs" disabled data-poll>
            </div>
        </form>
    <?php elseif ($ch['kind'] === 'announce'): ?>
        <p class="mt-3 glass-card p-3.5 text-center text-xs text-slate-500">📣 Kanal pengumuman — hanya tim ChiperX yang memosting. Kamu tetap bisa kasih reaksi! 😀</p>
    <?php else: ?>
        <p class="mt-3 glass-card p-3.5 text-center text-xs text-slate-500">🔒 <a href="/login" class="text-neon-cyan hover:underline">Masuk</a> untuk ikut ngobrol di kanal ini.</p>
    <?php endif; ?>
</section>

<script>
(() => {
    // auto-scroll ke bawah saat memuat
    const list = document.getElementById('chList');
    if (list) list.scrollTop = list.scrollHeight;
    // 📊 toggle polling → switch mode form
    const pollBox = document.getElementById('pollBox');
    const pollMode = document.getElementById('pollMode');
    const inputs = pollBox ? pollBox.querySelectorAll('[data-poll]') : [];
    const obs = new MutationObserver(() => {
        const open = !pollBox.classList.contains('hidden');
        pollMode.disabled = !open;
        pollMode.value = open ? 'poll' : 'text';
        inputs.forEach(i => i.disabled = !open);
        const body = document.getElementById('chInput');
        if (body) body.disabled = open;
    });
    if (pollBox) obs.observe(pollBox, { attributes: true });
    // 🔄 refresh lembut tiap 25 dtk HANYA bila komponis kosong (biar teks tak hilang)
    setInterval(() => {
        const inp = document.getElementById('chInput');
        if (inp && inp.value.trim() === '') location.reload();
    }, 25000);
})();
function setReply(id, name) {
    const bar = document.getElementById('replyBar');
    const hid = document.getElementById('replyTo');
    if (!id) { bar.classList.add('hidden'); hid.value = ''; return; }
    hid.value = id;
    document.getElementById('replyTxt').textContent = '↩️ Membalas ' + name + '…';
    bar.classList.remove('hidden');
    document.getElementById('chInput').focus();
}
</script>
