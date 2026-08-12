<?php /** 💬 RUANG CHAT — bubbles ala WhatsApp: ✓✓ biru, typing, foto, reply, pin, hapus-untuk-semua, TTL musnah, forward, export */ ?>
<?php
$tuser = rawurlencode((string) $target['username']);
$tAva  = trim((string) ($target['avatar'] ?? ''));
?>
<section class="max-w-3xl mx-auto flex flex-col" style="min-height:70vh;">
    <!-- kepala chat -->
    <header class="flex items-center gap-3 mb-3 glass-card p-3 sm:p-4" data-anim="fade-up">
        <a href="/pesan" class="text-slate-400 hover:text-white transition px-1 py-1" title="Kembali">←</a>
        <a href="/profil/@<?= e($tuser) ?>" class="shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900 overflow-hidden relative">
            <?php if ($isSelf): ?>⭐<?php elseif ($tAva !== ''): ?><img src="/media/avatar/<?= e($tAva) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= e(strtoupper(mb_substr((string) $target['name'], 0, 1))) ?><?php endif; ?>
        </a>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-white text-sm truncate flex items-center gap-1.5 flex-wrap">
                <?php if ($isSelf): ?>
                    ⭐ Pesan Tersimpan
                <?php elseif (user_is_vip($target)): ?>
                    <span class="bg-gradient-to-r from-amber-300 via-yellow-400 to-amber-300 bg-clip-text text-transparent font-bold"><?= e($target['name']) ?> 💎</span>
                <?php else: ?>
                    <?= e($target['name']) ?>
                <?php endif; ?>
                <?= user_badges(['role' => $target['role'], 'badges' => $target['badges'] ?? '']) ?>
            </p>
            <?php if ($isSelf): ?>
                <p class="text-[11px] text-slate-500">Hanya kamu yang bisa melihat chat ini 🔒</p>
            <?php else: ?>
                <?php $onl = online_label($target['last_activity'] ?? null); ?>
                <p class="text-[11px] <?= $onl === '🟢 Online' ? 'text-emerald-400 font-semibold' : 'text-slate-500' ?>"><span id="presenceTxt"><?= e($onl ?? '@' . (string) $target['username']) ?></span><span id="typingTxt" class="text-cyan-300 italic hidden"> · sedang mengetik…</span></p>
            <?php endif; ?>
        </div>
        <?php if (!$isSelf): ?>
        <details class="relative shrink-0">
            <summary class="list-none cursor-pointer text-slate-400 hover:text-white px-2 py-1 text-lg">⋮</summary>
            <div class="absolute right-0 top-9 z-20 w-56 glass-card border border-violet-500/30 p-2 space-y-1 text-xs">
                <a href="/profil/@<?= e($tuser) ?>" class="block px-3 py-2 rounded-lg hover:bg-white/10">👤 Lihat Profil</a>
                <a href="/pesan/<?= e($tuser) ?>/ekspor" class="block px-3 py-2 rounded-lg hover:bg-white/10">📥 Ekspor riwayat (.txt)</a>
                <form method="post" action="/pesan/<?= e($tuser) ?>/blokir" onsubmit="return confirm('<?= $iBlocked ? 'Buka blokir pengguna ini?' : 'Blokir pengguna ini? Dia tak akan bisa mengirimu pesan.' ?>')">
                    <?= csrf_field() ?>
                    <button class="w-full text-left px-3 py-2 rounded-lg <?= $iBlocked ? 'text-emerald-300 hover:bg-emerald-500/10' : 'text-red-300 hover:bg-red-500/10' ?>"><?= $iBlocked ? '✅ Buka Blokir' : '🚫 Blokir pengguna' ?></button>
                </form>
            </div>
        </details>
        <?php endif; ?>
    </header>

    <!-- 📌 pesan di-pin -->
    <?php if (!empty($pinned)): ?>
        <div class="glass-card px-4 py-2.5 mb-3 border-amber-400/30 space-y-1" data-anim="fade-up">
            <?php foreach ($pinned as $pn): ?>
                <p class="text-xs text-slate-300 truncate flex items-center gap-2"><span class="text-amber-300">📌</span> <b class="text-amber-200"><?= e($pn['name']) ?>:</b> <?= e(mb_substr((string) $pn['body'], 0, 80)) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- bubble chat (diisi JS) -->
    <div id="chatList" class="flex-1 glass-card p-4 space-y-2.5 overflow-y-auto" style="max-height:52vh;"></div>

    <?php if ($blocked): ?>
        <p class="mt-3 glass-card p-4 text-center text-sm text-red-300">🚫 Kamu diblokir pengguna ini — pesanmu tidak akan terkirim.</p>
    <?php else: ?>
    <!-- komponis -->
    <form method="post" action="/pesan/<?= e($tuser) ?>" enctype="multipart/form-data" class="mt-3 glass-card p-2.5 sm:p-3 space-y-2">
        <?= csrf_field() ?>
        <input type="hidden" name="reply_to" id="replyTo" value="">
        <div id="replyBar" class="hidden text-[11px] text-slate-400 border-l-2 border-cyan-500/60 pl-2 flex items-center justify-between gap-2">
            <span id="replyTxt" class="truncate"></span>
            <button type="button" onclick="setReply(null,'')" class="text-red-400 shrink-0">✕</button>
        </div>
        <div class="flex gap-2 items-center">
            <label class="w-11 h-11 shrink-0 rounded-full bg-white/5 border border-white/10 grid place-items-center cursor-pointer hover:bg-white/10 transition text-lg" title="Kirim foto dari HP">
                📷<input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="this.closest('form').querySelector('[name=body]').placeholder='✅ '+this.files[0].name.slice(0,18)+' (tambah teks opsional)'">
            </label>
            <select name="ttl" title="Pesan musnah otomatis" class="shrink-0 form-input text-[10px] py-3 px-2 w-24 text-slate-400">
                <option value="0">♾️ awet</option>
                <option value="3600">⏳ 1 jam</option>
                <option value="86400">⏳ 1 hari</option>
                <option value="604800">⏳ 7 hari</option>
            </select>
            <input name="body" id="chatInput" maxlength="500" autocomplete="off" placeholder="<?= $isSelf ? 'Simpan catatan/link/foto untuk dirimu… ⭐' : 'Tulis pesan… (*tebal* _miring_ ~coret~)' ?>" class="form-input flex-1 text-sm py-3">
            <button class="w-11 h-11 shrink-0 rounded-full bg-gradient-to-r from-violet-600 to-cyan-500 text-white grid place-items-center hover:opacity-90 transition shadow-lg shadow-violet-600/25" title="Kirim">➤</button>
        </div>
    </form>
    <?php endif; ?>

    <!-- modal forward -->
    <div id="fwdModal" class="hidden fixed inset-0 z-50 grid place-items-center bg-black/70 p-4">
        <form method="post" action="/pesan/<?= e($tuser) ?>/teruskan" class="glass-card w-full max-w-sm p-5 space-y-3">
            <?= csrf_field() ?>
            <p class="font-bold text-white text-sm">↪️ Teruskan pesan ke…</p>
            <input type="hidden" name="body" id="fwdBody">
            <p id="fwdPreview" class="text-xs text-slate-400 border-l-2 border-violet-500/50 pl-2 break-words"></p>
            <input name="to" required maxlength="30" placeholder="username tujuan (tanpa @)" class="form-input w-full text-sm">
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('fwdModal').classList.add('hidden')" class="flex-1 py-2.5 rounded-xl bg-white/5 border border-white/10 text-sm text-slate-300">Batal</button>
                <button class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white text-sm font-bold">Kirim ↪️</button>
            </div>
        </form>
    </div>
</section>

<script>
(() => {
    const list = document.getElementById('chatList');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let lastId = 0;
    const el = (tag, cls, html) => { const d = document.createElement(tag); if (cls) d.className = cls; if (html !== undefined) d.innerHTML = html; return d; };
    const esc = (t) => { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    const bubble = (m) => {
        const wrap = el('div', 'flex ' + (m.mine ? 'justify-end' : 'justify-start') + ' group');
        const b = el('div', 'relative max-w-[82%] sm:max-w-[70%] rounded-2xl px-3.5 py-2.5 ' + (m.mine
            ? 'rounded-br-md bg-gradient-to-br from-violet-600 to-cyan-600 text-white shadow-lg shadow-violet-600/20'
            : 'rounded-bl-md bg-white/[0.07] border border-white/10 text-slate-200'));
        if (m.reply) {
            b.appendChild(el('p', 'text-[10px] mb-1 px-2 py-1 rounded-lg bg-black/20 border-l-2 border-cyan-400/70 truncate', '↩️ <b>' + esc(m.reply.name) + '</b> ' + esc(m.reply.body)));
        }
        if (m.pinned) b.appendChild(el('span', 'absolute -top-2 -right-1 text-xs', '📌'));
        if (m.deleted) {
            b.appendChild(el('p', 'text-sm italic opacity-70', '🚫 Pesan ini telah dihapus'));
        } else {
            if (m.image) {
                const img = el('img', 'rounded-xl mb-1 max-h-60 w-auto cursor-pointer border border-white/10');
                img.src = '/media/dm/' + m.image; img.loading = 'lazy'; img.alt = 'foto';
                img.onclick = () => window.open(img.src, '_blank');
                b.appendChild(img);
            }
            if (m.html) b.appendChild(el('p', 'text-sm whitespace-pre-line break-words', m.html));
            if (m.ttl !== null) {
                const menit = Math.ceil(m.ttl / 60);
                b.appendChild(el('p', 'text-[9px] mt-1 ' + (m.mine ? 'text-white/50' : 'text-slate-500'), '⏳ musnah dalam ' + (menit >= 60 ? Math.round(menit/60) + ' jam' : menit + ' menit')));
            }
        }
        const meta = el('p', 'text-[9px] mt-1 text-right ' + (m.mine ? '' : 'text-slate-500'));
        meta.innerHTML = esc(m.time) + (m.mine ? ' <span class="ticks">' + (m.read ? '<span class="text-cyan-300">✓✓</span>' : '<span class="text-white/50">✓</span>') + '</span>' : '');
        b.appendChild(meta);
        // aksi hover: balas / pin / teruskan / hapus
        if (!m.deleted) {
            const act = el('span', 'absolute ' + (m.mine ? '-left-24' : '-right-24') + ' top-1.5 hidden group-hover:flex gap-1 text-[11px]');
            const mk = (label, title, fn) => { const s = el('button', 'px-1.5 py-1 rounded-lg bg-slate-800/90 border border-white/10 hover:bg-slate-700', label); s.type = 'button'; s.title = title; s.onclick = fn; return s; };
            act.appendChild(mk('↩️', 'Balas', () => setReply(m.id, m.plain.slice(0, 60))));
            act.appendChild(mk('↪️', 'Teruskan', () => openFwd(m.plain)));
            const pinF = document.createElement('form');
            pinF.method = 'post'; pinF.action = '/pesan/<?= e($tuser) ?>/pin/' + m.id;
            pinF.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '"><button class="px-1.5 py-1 rounded-lg bg-slate-800/90 border border-white/10 hover:bg-slate-700" title="Pin">📌</button>';
            act.appendChild(pinF);
            if (m.mine) {
                const delF = document.createElement('form');
                delF.method = 'post'; delF.action = '/pesan/msg/' + m.id + '/delete';
                delF.onsubmit = () => confirm('Hapus pesan ini untuk SEMUA orang?');
                delF.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '"><button class="px-1.5 py-1 rounded-lg bg-slate-800/90 border border-white/10 text-red-300 hover:bg-red-500/20" title="Hapus untuk semua">🗑️</button>';
                act.appendChild(delF);
            }
            b.appendChild(act);
        }
        wrap.appendChild(b);
        return wrap;
    };
    const empty = () => { list.innerHTML = '<p class="text-center text-slate-600 text-xs py-10"><?= $isSelf ? 'Simpan apapun di sini — catatan, link, foto. Hanya kamu yang lihat! ⭐' : 'Belum ada pesan — sapa duluan! 👋' ?></p>'; };
    empty();
    const tick = () => fetch('/pesan/<?= e($tuser) ?>/json')
        .then(r => r.ok ? r.json() : null)
        .then(d => {
            if (!d || !d.ok) return;
            // ✍️ typing indicator lawan
            const tt = document.getElementById('typingTxt');
            if (tt) tt.classList.toggle('hidden', !d.typing);
            if (!d.items.length) { if (lastId === 0) empty(); return; }
            const fresh = d.items.filter(m => m.id > lastId);
            if (!fresh.length) {
                // update centang baca pesanku → biru
                d.items.forEach(m => {
                    if (m.mine) {
                        const elx = list.querySelector('[data-mid="' + m.id + '"] .ticks');
                        if (elx) elx.innerHTML = m.read ? '<span class="text-cyan-300">✓✓</span>' : '<span class="text-white/50">✓</span>';
                    }
                });
                return;
            }
            if (lastId === 0) list.innerHTML = '';
            fresh.forEach(m => {
                const bx = bubble(m);
                bx.dataset.mid = m.id;
                list.appendChild(bx);
                lastId = m.id;
            });
            list.scrollTop = list.scrollHeight;
        })
        .catch(() => {});
    tick();
    setInterval(tick, 5000);

    // ✍️ kirim flag "sedang mengetik" (max 1x/3 dtk)
    const inp = document.getElementById('chatInput');
    let lastType = 0;
    if (inp) {
        inp.addEventListener('input', () => {
            if (Date.now() - lastType < 3000 || inp.value.trim() === '') return;
            lastType = Date.now();
            fetch('/pesan/<?= e($tuser) ?>/typing', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + encodeURIComponent(csrf) }).catch(() => {});
        });
    }
})();
function setReply(id, txt) {
    const bar = document.getElementById('replyBar');
    const hid = document.getElementById('replyTo');
    if (!id) { bar.classList.add('hidden'); hid.value = ''; return; }
    hid.value = id;
    document.getElementById('replyTxt').textContent = '↩️ ' + txt;
    bar.classList.remove('hidden');
    document.getElementById('chatInput').focus();
}
function openFwd(text) {
    document.getElementById('fwdBody').value = text;
    document.getElementById('fwdPreview').textContent = text.slice(0, 120);
    document.getElementById('fwdModal').classList.remove('hidden');
}
</script>
