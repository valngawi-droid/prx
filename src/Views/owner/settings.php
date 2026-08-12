<?php /** OWNER — Pengaturan situs + RTP game (win-rate RNG) */ ?>
<h1 class="font-display text-2xl font-bold text-white">⚙️ Pengaturan Sistem & RTP</h1>
<p class="text-sm text-slate-500 mt-1">Total probabilitas setiap game <b>wajib 100%</b> — sistem menolak penyimpanan jika tidak valid.</p>

<form method="post" action="/owner/settings" enctype="multipart/form-data" class="space-y-6 mt-6">
    <?= csrf_field() ?>

    <!-- 🖼️ GAMBAR SITUS — tempel link ATAU upload dari HP -->
    <div class="glass-card p-6 border-violet-500/25">
        <h2 class="font-display font-bold text-neon-purple mb-1">🖼️ Gambar Website</h2>
        <p class="text-[11px] text-slate-500 mb-5">Logo navbar & gambar preview share (OG) — tempel <b>link</b> atau <b>upload dari HP</b>, dua-duanya bisa.</p>
        <div class="grid md:grid-cols-2 gap-5">
            <?php foreach ([
                'site_logo'     => ['🏷️ Logo Navbar', 'Tampil di samping tulisan CHIPERX (kotak, disarankan PNG transparan).'],
                'site_og_image' => ['🔗 Gambar OG (Share Preview)', 'Gambar yang muncul saat link situs dibagikan ke WA/Discord (1200×630 ideal).'],
            ] as $imgKey => [$imgLabel, $imgHint]):
                $cur = trim((string) ($settings[$imgKey] ?? '')); ?>
                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-white"><?= $imgLabel ?></p>
                        <?php if ($cur !== ''): ?>
                            <img src="<?= e($cur) ?>" alt="" class="h-9 max-w-[7rem] object-contain rounded-lg border border-white/10 bg-black/30 p-1">
                        <?php endif; ?>
                    </div>
                    <p class="text-[10px] text-slate-500"><?= $imgHint ?></p>
                    <input name="<?= e($imgKey) ?>_link" maxlength="500" value="<?= e($cur) ?>" placeholder="https://… (link gambar)" class="form-input w-full text-xs font-mono">
                    <label class="flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-violet-500/40 bg-violet-500/5 px-3 py-2.5 text-[11px] text-violet-200 cursor-pointer hover:bg-violet-500/10 transition">
                        📱 <span id="<?= e($imgKey) ?>_lbl">Upload dari HP…</span>
                        <input type="file" name="<?= e($imgKey) ?>_file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
                               onchange="document.getElementById('<?= e($imgKey) ?>_lbl').textContent=this.files[0]?('✅ '+this.files[0].name.slice(0,20)):'Upload dari HP…'">
                    </label>
                    <?php if ($cur !== ''): ?>
                        <label class="flex items-center gap-2 text-[11px] text-slate-400 cursor-pointer"><input type="checkbox" name="<?= e($imgKey) ?>_clear" class="accent-red-500"> Hapus gambar ini</label>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 🚧 MAINTENANCE + ⚡ EVENT + 🤬 AUTOMOD -->
    <div class="grid md:grid-cols-3 gap-4">
        <div class="glass-card p-6 border-amber-500/25">
            <h2 class="font-display font-bold text-amber-300 mb-1">🚧 Mode Pemeliharaan</h2>
            <p class="text-[11px] text-slate-500 mb-4">Aktif → pengunjung melihat halaman "sedang maintenance" cantik; owner & admin tetap bisa masuk.</p>
            <label class="flex items-center gap-3 cursor-pointer select-none">
                <input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> class="accent-amber-500 w-4 h-4">
                <span class="text-sm font-semibold <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'text-amber-300' : 'text-slate-300' ?>">Tutup situs sementara</span>
            </label>
            <input name="maintenance_note" value="<?= e($settings['maintenance_note'] ?? '') ?>" maxlength="190" placeholder="Catatan utk pengunjung, mis: Upgrade v3 — kembali 30 menit lagi!" class="form-input w-full text-xs mt-3">
        </div>
        <div class="glass-card p-6 border-emerald-500/25">
            <h2 class="font-display font-bold text-emerald-300 mb-1">⚡ Event Pengali Koin</h2>
            <p class="text-[11px] text-slate-500 mb-4">Semua bonus harian, quest & hadiah game dikali ini. 1 = normal · 2 = double (otomatis muncul banner event di dashboard member!).</p>
            <div class="flex items-center gap-3">
                <span class="text-2xl">🪙</span>
                <input name="coin_multiplier" type="number" step="0.5" min="0.5" max="10" value="<?= e($settings['coin_multiplier'] ?? '1') ?>" class="form-input w-28 text-center text-lg font-bold text-amber-300">
                <span class="text-xs text-slate-500">× lipat<br>(0.5 – 10)</span>
            </div>
        </div>
        <div class="glass-card p-6 border-red-500/25">
            <h2 class="font-display font-bold text-red-300 mb-1">🤬 AutoMod Kanal</h2>
            <p class="text-[11px] text-slate-500 mb-4">Kata-kata ini otomatis disensor 🌟 di semua kanal (gaya Discord AutoMod). Pisahkan dengan koma.</p>
            <textarea name="banned_words" rows="3" maxlength="300" placeholder="kata1, kata2, kata3" class="form-input w-full text-xs"><?= e($settings['banned_words'] ?? '') ?></textarea>
            <p class="text-[10px] text-slate-600 mt-1.5">⚠️ Disimpan apa adanya — sensor berlaku utuh per kata (bukan potongan kata).</p>
        </div>
    </div>

    <!-- SITUS -->
    <div class="glass-card p-6">
        <h2 class="font-display font-bold text-neon-cyan mb-4">🌐 Umum</h2>
        <div class="grid sm:grid-cols-3 gap-4">
            <div><label class="text-xs text-slate-500">Tagline situs</label>
                <input name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>" maxlength="120" class="form-input w-full text-sm mt-1"></div>
            <div><label class="text-xs text-slate-500">Bonus koin registrasi</label>
                <input name="register_bonus" type="number" min="0" value="<?= e($settings['register_bonus'] ?? '25') ?>" class="form-input w-full text-sm mt-1"></div>
            <div><label class="text-xs text-slate-500">Tiket main harian</label>
                <input name="daily_tickets" type="number" min="1" max="10" value="<?= e($settings['daily_tickets'] ?? '3') ?>" class="form-input w-full text-sm mt-1"></div>
            <div><label class="text-xs text-slate-500">Bonus login harian (koin)</label>
                <input name="daily_bonus" type="number" min="0" value="<?= e($settings['daily_bonus'] ?? '15') ?>" class="form-input w-full text-sm mt-1"></div>
            <div><label class="text-xs text-slate-500">Bonus referral (koin)</label>
                <input name="referral_bonus" type="number" min="0" value="<?= e($settings['referral_bonus'] ?? '50') ?>" class="form-input w-full text-sm mt-1"></div>

            <div class="md:col-span-2 mt-2 rounded-xl border border-cyan-500/30 bg-cyan-500/5 p-4">
                <p class="text-sm font-semibold text-white mb-1">🎨 Tampilan Web (berlaku instan untuk semua pengunjung)</p>
                <div class="grid md:grid-cols-3 gap-4">
                    <div><label class="text-xs text-slate-400">Aksen Ungu</label>
                        <input type="color" name="theme_purple" value="<?= e($settings['theme_purple'] ?? '#a78bfa') ?>" class="w-full h-10 rounded-lg bg-transparent cursor-pointer"></div>
                    <div><label class="text-xs text-slate-400">Aksen Cyan</label>
                        <input type="color" name="theme_cyan" value="<?= e($settings['theme_cyan'] ?? '#22d3ee') ?>" class="w-full h-10 rounded-lg bg-transparent cursor-pointer"></div>
                    <div><label class="text-xs text-slate-400">Aksen Hijau</label>
                        <input type="color" name="theme_green" value="<?= e($settings['theme_green'] ?? '#4ade80') ?>" class="w-full h-10 rounded-lg bg-transparent cursor-pointer"></div>
                </div>
                <div class="mt-4">
                    <p class="text-xs text-slate-400 mb-2">⚡ Preset instan — ketuk kartu lalu tekan <b>Simpan</b>:</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                        <?php foreach ([
                            'Ungu Nebula'  => ['#a78bfa', '#22d3ee', '#4ade80'],
                            'Samudra Cyan' => ['#22d3ee', '#818cf8', '#34d399'],
                            'Matrix Hijau' => ['#4ade80', '#22d3ee', '#a3e635'],
                            'Magma Merah'  => ['#f87171', '#fb923c', '#facc15'],
                            'Royal Emas'   => ['#fbbf24', '#f472b6', '#4ade80'],
                            'Sakura Pink'  => ['#f472b6', '#c084fc', '#fda4af'],
                        ] as $pname => [$pp, $pc, $pg]): ?>
                            <button type="button" onclick="applyThemePreset('<?= $pp ?>', '<?= $pc ?>', '<?= $pg ?>', this)"
                                    class="rounded-xl border border-white/10 p-2.5 text-[10px] font-bold text-white text-center hover:scale-[1.05] hover:border-white/30 transition"
                                    style="background:linear-gradient(135deg,<?= $pp ?>2e,<?= $pc ?>2e);">
                                <span class="flex justify-center gap-1 mb-1.5">
                                    <i class="w-3.5 h-3.5 rounded-full inline-block" style="background:<?= $pp ?>"></i>
                                    <i class="w-3.5 h-3.5 rounded-full inline-block" style="background:<?= $pc ?>"></i>
                                    <i class="w-3.5 h-3.5 rounded-full inline-block" style="background:<?= $pg ?>"></i>
                                </span><?= e($pname) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-3"><label class="text-xs text-slate-400">Deskripsi hero beranda</label>
                    <input name="hero_desc" value="<?= e($settings['hero_desc'] ?? '') ?>" maxlength="300" placeholder="Kosongkan untuk teks bawaan" class="form-input w-full text-sm mt-1"></div>
            </div>

            <div class="md:col-span-2 mt-2 rounded-xl border border-violet-500/30 bg-violet-500/5 p-4">
                <p class="text-sm font-semibold text-white mb-1">🛡️ Gerbang Rahasia Panel (<code class="text-neon-cyan">/zszdgj/login</code>)</p>
                <p class="text-xs text-slate-500 mb-3">Kosongkan yang tidak ingin diubah. Password disimpan sebagai <b>hash bcrypt</b> — nilai mentah tidak pernah tersimpan.</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div><label class="text-xs text-slate-400">Username gerbang</label>
                        <input name="admin_gate_user" value="<?= e($settings['admin_gate_user'] ?? 'ChiperX') ?>" maxlength="60" autocomplete="off" class="form-input w-full text-sm mt-1"></div>
                    <div><label class="text-xs text-slate-400">Password gerbang baru</label>
                        <input name="admin_gate_pass" type="password" placeholder="•••  (biarkan kosong bila tidak diganti)" maxlength="200" autocomplete="new-password" class="form-input w-full text-sm mt-1"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RTP TIAP GAME -->
    <?php
    $rtpMeta = [
        'gacha'       => ['🎰 Gacha Spin (Roda Keberuntungan)', 'gacha_', ['zonk' => 'Zonk', 'coin_10' => '10 Coin', 'coin_50' => '50 Coin', 'coin_100' => '100 Coin']],
        'mystery_box' => ['📦 Mystery Box', 'box_',  ['zonk' => 'Zonk', 'coin_25' => '25 Coin', 'coin_75' => '75 Coin', 'coin_150' => '150 Coin']],
        'card_flip'   => ['🃏 Card Flip', 'card_', ['zonk' => 'Zonk', 'coin_20' => '20 Coin', 'coin_60' => '60 Coin', 'coin_120' => '120 Coin']],
    ];
    foreach ($rtpMeta as $game => [$label, $prefix, $prizes]): ?>
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display font-bold text-neon-purple"><?= e($label) ?></h2>
                <span class="text-xs text-slate-500">Total: <b class="rtp-total text-neon-cyan" data-game="<?= $game ?>">100%</b></span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php foreach ($prizes as $key => $prizeLabel): ?>
                    <div>
                        <label class="text-xs <?= $key === 'zonk' ? 'text-red-400' : 'text-emerald-300' ?>"><?= e($prizeLabel) ?> (%)</label>
                        <input type="number" step="0.01" min="0" max="100"
                               name="<?= e($prefix . $key) ?>" data-rtp="<?= $game ?>"
                               value="<?= e($settings[$prefix . $key] ?? '0') ?>"
                               class="form-input w-full text-sm mt-1 <?= $key === 'zonk' ? 'border-red-500/30' : '' ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <button class="w-full sm:w-auto px-10 py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">
        💾 Simpan Semua Pengaturan
    </button>
</form>

<script>
    // Preset tema 1-klik: isi 3 color picker lalu user menekan Simpan
    function applyThemePreset(p, c, g, btn) {
        const set = (n, v) => {
            const el = document.querySelector('[name="' + n + '"]');
            if (el) { el.value = v; el.classList.add('ring-2', 'ring-cyan-400'); setTimeout(() => el.classList.remove('ring-2', 'ring-cyan-400'), 900); }
        };
        set('theme_purple', p); set('theme_cyan', c); set('theme_green', g);
        document.querySelectorAll('[onclick^="applyThemePreset"]').forEach(b => b.classList.remove('border-neon-cyan'));
        btn.classList.add('border-neon-cyan');
    }
    // Penghitung total RTP real-time per game
    document.querySelectorAll('[data-rtp]').forEach(inp => inp.addEventListener('input', recalc));
    function recalc() {
        ['gacha', 'mystery_box', 'card_flip'].forEach(g => {
            const total = [...document.querySelectorAll(`[data-rtp="${g}"]`)].reduce((s, i) => s + (parseFloat(i.value) || 0), 0);
            const el = document.querySelector(`.rtp-total[data-game="${g}"]`);
            el.textContent = (Math.round(total * 100) / 100) + '%';
            el.className = 'rtp-total font-bold ' + (Math.abs(total - 100) < 0.01 ? 'text-emerald-300' : 'text-red-400');
        });
    }
    recalc();
</script>
