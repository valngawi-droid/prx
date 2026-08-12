<?php /** TIKET BANTUAN (Admin) */ ?>
<h1 class="font-display text-2xl font-bold text-white">🎫 Tiket Bantuan</h1>
<div class="grid lg:grid-cols-2 gap-6 mt-6">
    <div class="glass-card p-5 max-h-[36rem] overflow-y-auto">
        <h2 class="text-sm font-bold text-slate-400 mb-3">Daftar Tiket</h2>
        <?php if (empty($tickets)): ?><p class="text-sm text-slate-500 py-6 text-center">Tidak ada tiket 🎉</p><?php endif; ?>
        <?php foreach ($tickets as $t): ?>
            <a href="?open=<?= (int) $t['id'] ?>" class="block p-4 rounded-xl border mb-2 transition
                <?= isset($active['id']) && (int) $active['id'] === (int) $t['id'] ? 'border-violet-500/50 bg-violet-600/10' : 'border-white/5 bg-white/[.02] hover:bg-white/[.05]' ?>">
                <div class="flex justify-between items-center text-sm">
                    <b class="text-white truncate"><?= e($t['subject']) ?></b>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0
                        <?= $t['status'] === 'open' ? 'bg-amber-500/15 text-amber-300' : ($t['status'] === 'answered' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-500/15 text-slate-400') ?>"><?= strtoupper(e($t['status'])) ?></span>
                </div>
                <p class="text-xs text-slate-500 mt-1"><?= e($t['email']) ?> · <?= e($t['category']) ?> · <?= e(waktu_lalu($t['updated_at'])) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="glass-card p-5">
        <?php if (!$active): ?>
            <p class="text-sm text-slate-500 py-16 text-center">← Pilih tiket untuk melihat percakapan</p>
        <?php else: ?>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-white text-sm"><?= e($active['subject']) ?></h2>
                <form method="post" action="/admin/tickets/<?= (int) $active['id'] ?>/status" class="flex gap-1">
                    <?= csrf_field() ?>
                    <select name="status" class="form-input text-xs py-1.5" onchange="this.form.submit()">
                        <?php foreach (['open' => 'Open', 'answered' => 'Answered', 'closed' => 'Closed'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $active['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="mt-4 space-y-3 max-h-80 overflow-y-auto pr-1">
                <?php foreach ($replies as $r): ?>
                    <div class="p-3.5 rounded-xl text-sm <?= $r['role'] !== 'user' ? 'bg-violet-600/15 border border-violet-500/30 ml-8' : 'bg-white/[.03] border border-white/5 mr-8' ?>">
                        <p class="text-[10px] text-slate-500 mb-1"><?= e($r['email']) ?> <?= $r['role'] !== 'user' ? '<b class="text-neon-purple">(STAFF)</b>' : '' ?> · <?= e(waktu_lalu($r['created_at'])) ?></p>
                        <p class="text-slate-200"><?= nl2br(e($r['message'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($active['status'] !== 'closed'): ?>
                <form method="post" action="/admin/tickets/<?= (int) $active['id'] ?>/reply" class="mt-4 space-y-2">
                    <?= csrf_field() ?>
                    <textarea name="message" required minlength="2" rows="2" placeholder="Tulis balasan..." class="form-input w-full text-sm"></textarea>
                    <button class="w-full py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white">Kirim Balasan ➤</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
