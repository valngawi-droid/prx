<?php /** RUANG CHAT — gelembung pesan ala WhatsApp, polling 8 detik */ ?>
<?php $tuser = rawurlencode((string) $target['username']); ?>
<section class="max-w-3xl mx-auto flex flex-col" style="min-height:70vh;">
    <!-- kepala chat -->
    <header class="flex items-center gap-3 mb-4 glass-card p-3 sm:p-4" data-anim="fade-up">
        <a href="/pesan" class="text-slate-400 hover:text-white transition px-1 py-1" title="Kembali">←</a>
        <a href="/u/<?= e($tuser) ?>" class="shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 grid place-items-center font-bold text-slate-900"><?= e(strtoupper(mb_substr((string) $target['name'], 0, 1))) ?></a>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-white text-sm truncate flex items-center gap-1.5 flex-wrap">
                <?= e($target['name']) ?> <?= user_badges(['role' => $target['role'], 'badges' => $target['badges'] ?? '']) ?>
            </p>
            <p class="text-[11px] text-slate-500 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>@<?= e($target['username']) ?> · live 8 dtk</p>
        </div>
        <a href="/u/<?= e($tuser) ?>" class="text-xs text-neon-cyan hover:underline shrink-0 px-2">Lihat Profil</a>
    </header>

    <!-- gelembung pesan (diisi JS) -->
    <div id="chatList" class="flex-1 glass-card p-4 space-y-2.5 overflow-y-auto" style="max-height:55vh;"></div>

    <!-- pengirim pesan -->
    <form method="post" action="/pesan/<?= e($tuser) ?>" class="mt-3 flex gap-2 items-center glass-card p-2.5 sm:p-3">
        <?= csrf_field() ?>
        <input name="body" maxlength="500" required autocomplete="off" placeholder="Tulis pesan… 💬" class="form-input flex-1 text-sm py-3">
        <button class="w-11 h-11 shrink-0 rounded-full bg-gradient-to-r from-violet-600 to-cyan-500 text-white grid place-items-center hover:opacity-90 transition shadow-lg shadow-violet-600/25" title="Kirim">➤</button>
    </form>
    <p class="text-[10px] text-slate-600 mt-2 text-center">Pesan bersifat pribadi — hanya kalian berdua yang bisa melihatnya. 🔒</p>
</section>

<script>
// ── Live chat (poll 8 detik, render DOM aman anti-XSS) ──
(() => {
    const list = document.getElementById('chatList');
    let lastId = 0;
    const esc = (t) => { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    const bubble = (m) => {
        const wrap = document.createElement('div');
        wrap.className = 'flex ' + (m.mine ? 'justify-end' : 'justify-start');
        const b = document.createElement('div');
        b.className = m.mine
            ? 'max-w-[80%] sm:max-w-[70%] rounded-2xl rounded-br-md px-3.5 py-2.5 bg-gradient-to-br from-violet-600 to-cyan-600 text-white shadow-lg shadow-violet-600/20'
            : 'max-w-[80%] sm:max-w-[70%] rounded-2xl rounded-bl-md px-3.5 py-2.5 bg-white/[0.07] border border-white/10 text-slate-200';
        const body = document.createElement('p');
        body.className = 'text-sm whitespace-pre-line break-words';
        body.innerHTML = esc(m.body);
        const meta = document.createElement('p');
        meta.className = 'text-[9px] mt-1 text-right ' + (m.mine ? 'text-white/60' : 'text-slate-500');
        meta.textContent = m.time + (m.mine ? (m.read ? ' ✓✓' : ' ✓') : '');
        b.appendChild(body); b.appendChild(meta); wrap.appendChild(b);
        return wrap;
    };
    const empty = () => {
        list.innerHTML = '<p class="text-center text-slate-600 text-xs py-10">Belum ada pesan — sapa duluan! 👋</p>';
    };
    empty();
    const tick = () => fetch('/pesan/<?= e($tuser) ?>/json')
        .then(r => r.ok ? r.json() : null)
        .then(d => {
            if (!d || !d.ok) return;
            if (!d.items.length) { if (lastId === 0) empty(); return; }
            const fresh = d.items.filter(m => m.id > lastId);
            if (!fresh.length) { // update centang baca pesanku
                d.items.forEach(m => { if (m.mine && m.read) { const el = list.querySelector('[data-mid="' + m.id + '"] .readmark'); if (el) el.textContent = ' ✓✓'; } });
                return;
            }
            if (lastId === 0) list.innerHTML = '';
            fresh.forEach(m => {
                const el = bubble(m); el.dataset.mid = m.id;
                if (m.mine) el.querySelector('p:last-child').classList.add('readmark');
                list.appendChild(el); lastId = m.id;
            });
            list.scrollTop = list.scrollHeight;
        })
        .catch(() => {});
    tick();
    setInterval(tick, 8000);
})();
</script>
