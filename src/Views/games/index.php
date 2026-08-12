<?php /** HALAMAN MINI GAMES — 3 game dalam tab, ditenagai endpoint JSON /games/play */ ?>
<section class="max-w-6xl mx-auto px-6 py-16">
    <header class="text-center" data-anim="fade-up">
        <h1 class="font-display text-4xl sm:text-5xl font-bold text-white">🎮 Mini <span class="text-neon-purple">Games</span></h1>
        <p class="mt-3 text-slate-400">Menangkan <b class="text-amber-300">ChiperX Coin</b> GRATIS setiap hari!</p>
        <div class="mt-6 inline-flex items-center gap-6 glass-card px-6 py-3 text-sm">
            <span>🎟️ Tiket hari ini: <b id="ticketCount" class="text-neon-cyan text-lg"><?= (int) $tickets ?></b>/<?= (int) $maxTickets ?></span>
            <span class="text-slate-600">|</span>
            <span>🪙 Koin Anda: <b id="coinCount" class="text-amber-300 text-lg"><?= e(number_format((int) $user['coin_balance'])) ?></b></span>
        </div>
        <p class="mt-3 text-xs text-slate-600">⏰ Tiket di-reset otomatis setiap 00:00 WIB</p>
    </header>

    <!-- TAB GAME -->
    <div class="flex justify-center gap-3 mt-12" data-anim="fade-up">
        <?php foreach (['gacha' => '🎰 Gacha Spin', 'mystery_box' => '📦 Mystery Box', 'card_flip' => '🃏 Card Flip'] as $key => $label): ?>
            <button data-tab="<?= $key ?>"
                    class="game-tab px-5 py-2.5 rounded-xl text-sm font-semibold border transition
                           <?= $key === 'gacha' ? 'bg-violet-600/30 border-violet-500/50 text-white' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white' ?>">
                <?= $label ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- ========== GAME A: GACHA SPIN (roda 3D) ========== -->
    <div id="tab-gacha" class="game-panel mt-10">
        <div class="glass-card p-8 max-w-xl mx-auto text-center">
            <div class="relative w-72 h-72 sm:w-80 sm:h-80 mx-auto" style="perspective: 900px">
                <!-- Pointer -->
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 z-20 text-3xl text-neon-cyan drop-shadow-[0_0_12px_rgba(34,211,238,.9)]">▼</div>
                <canvas id="gachaWheel" width="640" height="640"
                        class="w-full h-full rounded-full border-4 border-violet-500/40 shadow-[0_0_60px_rgba(139,92,246,.35)]"
                        style="transform-style: preserve-3d"></canvas>
                <button id="spinBtn"
                        class="absolute inset-0 m-auto w-20 h-20 rounded-full bg-gradient-to-br from-violet-600 to-cyan-500 font-display font-bold text-white text-sm
                               shadow-[0_0_30px_rgba(139,92,246,.7)] hover:scale-110 active:scale-95 transition disabled:opacity-40 disabled:cursor-not-allowed z-10">
                    SPIN
                </button>
            </div>
            <p class="mt-6 text-sm text-slate-400">Hadiah: <b class="text-emerald-300">10 · 50 · 100 Coin</b> atau <b class="text-red-400">Zonk</b></p>
            <p id="gachaResult" class="mt-2 font-display text-xl font-bold min-h-[1.75rem]"></p>
        </div>
    </div>

    <!-- ========== GAME B: MYSTERY BOX ========== -->
    <div id="tab-mystery_box" class="game-panel hidden mt-10">
        <div class="glass-card p-8 max-w-2xl mx-auto text-center">
            <p class="text-sm text-slate-400 mb-8">Pilih <b class="text-white">1 dari 3</b> kotak misterius di bawah ini…</p>
            <div class="flex justify-center gap-5 sm:gap-8" id="boxRow">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <button class="mystery-box group relative w-24 h-24 sm:w-32 sm:h-32" data-box="<?= $i ?>">
                        <span class="box-body absolute inset-0 rounded-2xl bg-gradient-to-br from-violet-700 to-slate-800 border border-violet-400/40
                                     shadow-[0_10px_30px_rgba(139,92,246,.3)] group-hover:scale-105 group-hover:shadow-[0_0_40px_rgba(139,92,246,.55)] transition duration-300 grid place-items-center text-4xl">🎁</span>
                        <span class="box-lid absolute -top-2 inset-x-0 h-5 rounded-t-2xl bg-gradient-to-r from-fuchsia-500 to-violet-500 transition duration-500 origin-left"></span>
                        <span class="box-prize absolute inset-0 grid place-items-center text-3xl opacity-0 transition duration-300">✨</span>
                    </button>
                <?php endfor; ?>
            </div>
            <p id="boxResult" class="mt-8 font-display text-xl font-bold min-h-[1.75rem]"></p>
        </div>
    </div>

    <!-- ========== GAME C: CARD FLIP ========== -->
    <div id="tab-card_flip" class="game-panel hidden mt-10">
        <div class="glass-card p-8 max-w-2xl mx-auto text-center">
            <p class="text-sm text-slate-400 mb-8">Balik <b class="text-white">1 dari 4</b> kartu keberuntungan…</p>
            <div class="grid grid-cols-4 gap-3 sm:gap-5 max-w-lg mx-auto">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <button class="flip-card aspect-[3/4]" data-card="<?= $i ?>" style="perspective:700px">
                        <span class="flip-inner relative block w-full h-full transition-transform duration-700" style="transform-style:preserve-3d">
                            <span class="flip-face absolute inset-0 rounded-xl bg-gradient-to-br from-slate-800 to-violet-900 border border-violet-500/40 grid place-items-center text-2xl sm:text-3xl shadow-lg hover:shadow-[0_0_25px_rgba(139,92,246,.55)] transition" style="backface-visibility:hidden">✦</span>
                            <span class="flip-face flip-back absolute inset-0 rounded-xl bg-gradient-to-br from-cyan-600/30 to-emerald-600/30 border border-cyan-400/40 grid place-items-center text-[11px] sm:text-sm font-bold text-white px-1" style="backface-visibility:hidden;transform:rotateY(180deg)">?</span>
                        </span>
                    </button>
                <?php endfor; ?>
            </div>
            <p id="cardResult" class="mt-8 font-display text-xl font-bold min-h-[1.75rem]"></p>
        </div>
    </div>

    <!-- RIWAYAT MAIN -->
    <?php if (!empty($history)): ?>
        <div class="glass-card max-w-2xl mx-auto mt-12 p-6" data-anim="fade-up">
            <h3 class="font-display font-bold text-white text-sm mb-4">🕘 Riwayat Main Terakhir</h3>
            <div class="space-y-2 text-sm">
                <?php foreach ($history as $h): ?>
                    <div class="flex justify-between items-center py-1.5 border-b border-white/5 last:border-0">
                        <span class="text-slate-400"><?= e(['gacha' => '🎰 Gacha', 'mystery_box' => '📦 Mystery Box', 'card_flip' => '🃏 Card Flip'][$h['game']] ?? $h['game']) ?></span>
                        <span class="<?= (int) $h['reward'] > 0 ? 'text-emerald-300 font-semibold' : 'text-red-400/80' ?>">
                            <?= (int) $h['reward'] > 0 ? '+' . (int) $h['reward'] . ' Coin' : 'Zonk' ?>
                        </span>
                        <span class="text-xs text-slate-600"><?= e(waktu_lalu($h['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
    // Konfigurasi segmen roda dari RTP milik server (urutan tetap untuk visual)
    window.GACHA_SEGMENTS = <?= json_encode([
        ['key' => 'coin_10', 'label' => '10',  'color' => '#22d3ee'],
        ['key' => 'zonk',    'label' => 'ZONK','color' => '#ef4444'],
        ['key' => 'coin_50', 'label' => '50',  'color' => '#4ade80'],
        ['key' => 'zonk',    'label' => 'ZONK','color' => '#7f1d1d'],
        ['key' => 'coin_100','label' => '100', 'color' => '#fbbf24'],
        ['key' => 'zonk',    'label' => 'ZONK','color' => '#ef4444'],
    ], JSON_UNESCAPED_SLASHES) ?>;
    window.GAME_ENDPOINT = '/games/play';
</script>
<script src="<?= asset('js/games.js') ?>" defer></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= asset('js/landing.js') ?>" defer></script>
