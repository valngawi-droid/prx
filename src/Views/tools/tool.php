<?php /** TOOL RUNNER — form input + hasil (media/teks otomatis terdeteksi) */ ?>
<section class="max-w-2xl mx-auto space-y-6">
    <header class="flex items-center gap-3" data-anim="fade-up">
        <a href="/tools" class="text-slate-400 hover:text-white transition px-1 py-1">←</a>
        <span class="text-3xl"><?= $tool['icon'] ?></span>
        <div>
            <h1 class="font-display text-xl sm:text-2xl font-bold text-white"><?= e($tool['name']) ?></h1>
            <p class="text-[11px] text-slate-500"><?= e($tool['cat']) ?> · API alwayscodex</p>
        </div>
    </header>

    <!-- FORM -->
    <form method="post" action="/tools/<?= e($key) ?>" enctype="multipart/form-data" class="glass-card p-5 sm:p-6 space-y-4" data-anim="fade-up" id="toolForm">
        <?= csrf_field() ?>
        <?php if (empty($tool['fields'])): ?>
            <p class="text-sm text-slate-400">Alat ini tidak butuh input — langsung tekan tombol di bawah! 👇</p>
        <?php endif; ?>
        <?php foreach ($tool['fields'] as $f): [$name, $label, $type, $required] = $f; $ph = $f[4] ?? ''; $opts = $f[5] ?? []; $up = $f[6] ?? null; ?>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1.5"><?= e($label) ?> <?= $required ? '<span class="text-red-400">*</span>' : '<span class="text-slate-600">(opsional)</span>' ?></label>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?= e($name) ?>" rows="3" <?= $required ? 'required' : '' ?> placeholder="<?= e($ph) ?>" class="form-input w-full text-sm resize-none"></textarea>
                <?php elseif ($type === 'select'): ?>
                    <select name="<?= e($name) ?>" class="form-input w-full text-sm">
                        <?php foreach ($opts as $o): ?><option value="<?= e($o) ?>"><?= e(ucfirst($o)) ?></option><?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'file'): ?>
                    <input type="file" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> class="form-input w-full text-sm file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-violet-600 file:text-white file:text-xs file:font-bold hover:file:bg-violet-500">
                <?php elseif ($up !== null): ?>
                    <!-- DUAL MODE: tempel LINK atau UPLOAD dari HP 📱 -->
                    <input type="url" name="<?= e($name) ?>" placeholder="<?= e($ph) ?>" class="form-input w-full text-sm">
                    <div class="flex items-center gap-3 my-2"><span class="h-px flex-1 bg-white/10"></span><span class="text-[10px] font-bold text-slate-500 tracking-widest">ATAU UPLOAD DARI HP</span><span class="h-px flex-1 bg-white/10"></span></div>
                    <label class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-violet-500/40 bg-violet-500/5 px-4 py-3.5 text-xs font-semibold text-violet-200 cursor-pointer hover:bg-violet-500/10 hover:border-violet-400/60 transition">
                        📱 <span id="<?= e($name) ?>_lbl">Pilih <?= $up === 'video' ? 'video (MP4/WEBM)' : 'foto (JPG/PNG/WEBP/GIF)' ?> dari galeri…</span>
                        <input type="file" name="<?= e($name) ?>_file" accept="<?= $up === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif' ?>" class="hidden"
                               onchange="document.getElementById('<?= e($name) ?>_lbl').textContent=this.files[0]?('✅ '+this.files[0].name.slice(0,24)):'Pilih dari galeri…'">
                    </label>
                    <p class="text-[10px] text-slate-600 mt-1.5">💡 Upload diproses lewat server ChiperX lalu diteruskan otomatis ke API — file sementara dihapus berkala.</p>
                <?php else: ?>
                    <input type="<?= $type === 'url' ? 'url' : 'text' ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> placeholder="<?= e($ph) ?>" class="form-input w-full text-sm">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button id="runBtn" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white font-bold text-sm hover:opacity-90 transition shadow-lg shadow-violet-600/25">
            ✨ Proses Sekarang
        </button>
        <p id="runHint" class="hidden text-center text-xs text-neon-cyan animate-pulse">⏳ Sedang diproses server — video/HD bisa butuh 10-30 detik, jangan tutup halaman…</p>
    </form>

    <!-- HASIL -->
    <?php if ($res !== null): ?>
        <div class="glass-card p-5 sm:p-6 space-y-4" data-anim="fade-up">
            <h2 class="font-display font-bold text-white flex items-center gap-2">✅ Hasil</h2>

            <?php if (!empty($media)): ?>
                <div class="space-y-3">
                    <?php foreach ($media as $m): ?>
                        <?php if ($m['type'] === 'image'): ?>
                            <img src="<?= e($m['url']) ?>" alt="hasil" class="w-full rounded-xl border border-white/10" loading="lazy">
                            <a href="<?= e($m['url']) ?>" target="_blank" download class="block text-center text-xs text-neon-cyan hover:underline">⬇️ Unduh / buka ukuran penuh</a>
                        <?php elseif ($m['type'] === 'video'): ?>
                            <video src="<?= e($m['url']) ?>" controls playsinline class="w-full rounded-xl border border-white/10 bg-black"></video>
                            <a href="<?= e($m['url']) ?>" target="_blank" download class="block text-center text-xs text-neon-cyan hover:underline">⬇️ Unduh video</a>
                        <?php elseif ($m['type'] === 'audio'): ?>
                            <audio src="<?= e($m['url']) ?>" controls class="w-full"></audio>
                            <a href="<?= e($m['url']) ?>" target="_blank" download class="block text-center text-xs text-neon-cyan hover:underline">⬇️ Unduh audio</a>
                        <?php else: ?>
                            <a href="<?= e($m['url']) ?>" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-xl bg-white/5 border border-white/10 px-4 py-3 text-sm text-neon-cyan hover:bg-white/10 transition break-all">
                                🔗 <span class="truncate"><?= e($m['url']) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($teks !== null): ?>
                <div class="rounded-xl bg-white/[0.04] border border-white/10 p-4">
                    <p class="text-sm text-slate-200 whitespace-pre-line break-words"><?= e($teks) ?></p>
                </div>
                <button type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.dataset.t).then(()=>this.textContent='✅ Tersalin!')" data-t="<?= e($teks) ?>" class="text-xs px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition">📋 Salin teks</button>
            <?php endif; ?>

            <details class="text-xs">
                <summary class="cursor-pointer text-slate-500 hover:text-slate-300">Lihat respons mentah (JSON)</summary>
                <pre class="mt-2 p-3 rounded-xl bg-black/40 border border-white/10 text-slate-400 overflow-x-auto text-[11px] max-h-72 overflow-y-auto"><?= e(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
            </details>
        </div>
    <?php endif; ?>
</section>

<script>
document.getElementById('toolForm')?.addEventListener('submit', () => {
    const b = document.getElementById('runBtn');
    if (b) { b.disabled = true; b.textContent = '⏳ Memproses…'; b.classList.add('opacity-60'); }
    document.getElementById('runHint')?.classList.remove('hidden');
});
</script>
