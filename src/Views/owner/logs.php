<?php /** OWNER — Audit Log viewer */ ?>
<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-display text-2xl font-bold text-white">📜 Audit Logs</h1>
    <form method="get" class="flex gap-2">
        <input name="action" value="<?= e($filter) ?>" placeholder="Filter aksi (login, file.edit…)" class="form-input text-sm w-64">
        <button class="px-4 rounded-xl bg-white/10 text-sm">🔍</button>
    </form>
</div>

<div class="glass-card mt-6 p-4 space-y-1.5 text-xs font-mono max-h-[40rem] overflow-y-auto">
    <?php if (empty($logs)): ?><p class="p-6 text-center text-slate-500">Tidak ada log.</p><?php endif; ?>
    <?php foreach ($logs as $log): ?>
        <div class="grid sm:grid-cols-[90px,1fr,180px,110px,110px] gap-x-3 gap-y-1 p-3 rounded-lg bg-white/[.02] border border-white/5 hover:bg-white/[.04]">
            <span class="font-bold <?= $log['level'] === 'critical' ? 'text-red-400' : ($log['level'] === 'warning' ? 'text-amber-300' : 'text-emerald-300') ?>">[<?= strtoupper(e($log['level'])) ?>]</span>
            <span class="text-slate-200"><?= e($log['action']) ?>
                <?php if ($log['metadata']): ?><span class="text-slate-600"><?= e($log['metadata']) ?></span><?php endif; ?>
            </span>
            <span class="text-slate-500 truncate"><?= e($log['email'] ?? '— sistem —') ?></span>
            <span class="text-slate-600"><?= e($log['ip_address'] ?? '-') ?></span>
            <span class="text-slate-600 text-right"><?= e(waktu_lalu($log['created_at'])) ?></span>
        </div>
    <?php endforeach; ?>
</div>
