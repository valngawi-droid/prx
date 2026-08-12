<?php /** DASHBOARD USER: profil, koin, saldo, riwayat, tiket support */ ?>
<h1 class="font-display text-2xl font-bold text-white">👋 Halo, <?= e($user['name']) ?>!</h1>
<p class="text-sm text-slate-500 mt-1">Member sejak <?= e(date('d M Y', strtotime($user['created_at']))) ?> · Login terakhir <?= e($user['last_login'] ? waktu_lalu($user['last_login']) : '-') ?></p>

<!-- Kartu statistik -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
    <div class="glass-card p-5">
        <p class="text-xs text-slate-500">🪙 ChiperX Coin</p>
        <p class="mt-1 font-display text-3xl font-bold text-amber-300"><?= e(number_format((int) $user['coin_balance'])) ?></p>
    </div>
    <div class="glass-card p-5">
        <p class="text-xs text-slate-500">💳 Saldo</p>
        <p class="mt-1 font-display text-3xl font-bold text-emerald-300"><?= e(rupiah((int) $user['balance'])) ?></p>
    </div>
    <div class="glass-card p-5">
        <p class="text-xs text-slate-500">🎟️ Tiket Main</p>
        <p class="mt-1 font-display text-3xl font-bold text-neon-cyan"><?= (int) $user['play_tickets'] ?><span class="text-base text-slate-500">/<?= (int) ($maxTickets ?? 3) ?></span></p>
        <p class="text-[10px] text-slate-600 mt-1">reset 00:00 WIB</p>
    </div>
    <div class="glass-card p-5">
        <p class="text-xs text-slate-500">🛍️ Transaksi</p>
        <p class="mt-1 font-display text-3xl font-bold text-white"><?= count($history) ?></p>
    </div>
</div>

<!-- BONUS HARIAN & REFERRAL -->
<div class="grid md:grid-cols-2 gap-4 mt-6">
    <div class="glass-card p-6 bg-gradient-to-br from-amber-500/10 to-transparent border-amber-500/20">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-display font-bold text-white">🎁 Bonus Login Harian</h2>
                <p class="text-xs text-slate-500 mt-1">Klaim <b class="text-amber-300">+<?= (int) $dailyBonus ?> Coin</b> gratis setiap hari, reset 00:00 WIB.</p>
            </div>
            <span class="text-3xl"><?= $dailyClaimed ? '✅' : '🎁' ?></span>
        </div>
        <form method="post" action="/dashboard/claim-daily" class="mt-4">
            <?= csrf_field() ?>
            <button <?= $dailyClaimed ? 'disabled' : '' ?>
                    class="w-full py-3 rounded-xl text-sm font-bold transition
                        <?= $dailyClaimed ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 cursor-default' : 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-900 hover:opacity-90 shadow-lg shadow-amber-500/25' ?>">
                <?= $dailyClaimed ? 'Sudah Diklaim — Sampai Jumpa Besok! ⏰' : 'Klaim +' . (int) $dailyBonus . ' Coin Sekarang ⚡' ?>
            </button>
        </form>
    </div>
    <div class="glass-card p-6 bg-gradient-to-br from-violet-600/10 to-transparent border-violet-500/20">
        <h2 class="font-display font-bold text-white">👥 Undang Teman, Dapat Koin</h2>
        <p class="text-xs text-slate-500 mt-1">Setiap teman yang daftar lewat link Anda → <b class="text-neon-purple">+<?= (int) $referralBonus ?> Coin</b> untuk Anda.</p>
        <div class="mt-4 flex gap-2">
            <input id="refLink" readonly value="<?= e($refLink) ?>" class="form-input flex-1 text-xs font-mono py-2.5">
            <button onclick="navigator.clipboard.writeText(document.getElementById('refLink').value).then(()=>{this.textContent='✅';setTimeout(()=>this.textContent='📋 Salin',1500)})"
                    class="px-4 rounded-xl bg-violet-600/30 border border-violet-500/40 text-xs font-semibold text-white hover:bg-violet-600/50 transition whitespace-nowrap">📋 Salin</button>
        </div>
        <p class="text-[10px] text-slate-600 mt-2">Kode Anda: <b class="text-slate-400 font-mono"><?= e($user['referral_code']) ?></b></p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 mt-6">
    <!-- Riwayat pembelian / redeem -->
    <div class="glass-card p-6 lg:col-span-2">
        <h2 class="font-display font-bold text-white mb-4">🧾 Riwayat Pembelian & Penukaran</h2>
        <?php if (empty($history)): ?>
            <p class="text-sm text-slate-500 py-6 text-center">Belum ada transaksi. <a href="/redeem" class="text-neon-cyan">Tukar koin</a> atau <a href="/store" class="text-neon-purple">kunjungi store</a>!</p>
        <?php endif; ?>
        <div class="space-y-2">
            <?php foreach ($history as $t): ?>
                <div class="flex flex-wrap items-center gap-3 p-3.5 rounded-xl bg-white/[.03] border border-white/5 text-sm">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-white truncate"><?= e($t['product_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= e(waktu_lalu($t['created_at'])) ?> · <?= e(strtoupper($t['payment_method'])) ?></p>
                    </div>
                    <span class="text-xs font-bold <?= $t['payment_method'] === 'coin' ? 'text-amber-300' : 'text-neon-cyan' ?>">
                        <?= $t['payment_method'] === 'coin' ? '🪙 ' . number_format((int) $t['amount']) : e(rupiah((int) $t['amount'])) ?>
                    </span>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold
                        <?= $t['status'] === 'paid' ? 'bg-emerald-500/15 text-emerald-300' : ($t['status'] === 'pending' ? 'bg-amber-500/15 text-amber-300' : 'bg-red-500/15 text-red-300') ?>">
                        <?= strtoupper(e($t['status'])) ?>
                    </span>
                    <?php if ($t['status'] === 'paid'): ?>
                        <a href="/download/<?= (int) $t['id'] ?>" class="px-3 py-1.5 rounded-lg bg-violet-600/30 border border-violet-500/40 text-xs text-white hover:bg-violet-600/50 transition">⬇️ Unduh</a>
                    <?php elseif ($t['status'] === 'pending'): ?>
                        <a href="/store/checkout/<?= e(rawurlencode((string) $t['gateway_ref'])) ?>" class="px-3 py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/40 text-xs text-amber-200 hover:bg-amber-500/30 transition">💳 Bayar</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Leaderboard all-time -->
        <div class="glass-card p-6">
            <h2 class="font-display font-bold text-white mb-4">🏆 Top Koin (Sepanjang Masa)</h2>
            <?php foreach ($leaderboard as $i => $lb): ?>
                <div class="flex items-center gap-3 py-2 border-b border-white/5 last:border-0 text-sm">
                    <span class="w-6 text-center"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></span>
                    <span class="flex-1 truncate text-slate-300"><?= e($lb['name']) ?></span>
                    <b class="text-amber-300"><?= e(singkat((int) $lb['coin_balance'])) ?></b>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Leaderboard mingguan -->
        <div class="glass-card p-6 border-cyan-500/20">
            <h2 class="font-display font-bold text-white mb-1">⚡ Top Minggu Ini</h2>
            <p class="text-[10px] text-slate-600 mb-4">Top 3 tiap Senin mendapat bonus mingguan otomatis!</p>
            <?php if (empty($weeklyTop)): ?>
                <p class="text-xs text-slate-500 py-3 text-center">Belum ada pemenang koin minggu ini — jadilah yang pertama! 🚀</p>
            <?php endif; ?>
            <?php foreach ($weeklyTop as $i => $lb): ?>
                <div class="flex items-center gap-3 py-2 border-b border-white/5 last:border-0 text-sm">
                    <span class="w-6 text-center"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></span>
                    <span class="flex-1 truncate text-slate-300"><?= e($lb['name']) ?></span>
                    <b class="text-neon-cyan">+<?= e(singkat((int) $lb['earned'])) ?></b>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Hadiah mingguan saya -->
        <?php if (!empty($myRewards)): ?>
            <div class="glass-card p-6 border-emerald-500/20">
                <h2 class="font-display font-bold text-white mb-4">🎖️ Hadiah Mingguan Saya</h2>
                <?php foreach ($myRewards as $r): ?>
                    <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0 text-sm">
                        <span class="text-slate-400"><?= e($r['period']) ?> · Rank #<?= (int) $r['rank'] ?></span>
                        <b class="text-emerald-300">+<?= (int) $r['bonus'] ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <!-- Edit profil -->
        <div class="glass-card p-6">
            <h2 class="font-display font-bold text-white mb-4">✏️ Edit Profil</h2>
            <form method="post" action="/dashboard/profile" class="space-y-3">
                <?= csrf_field() ?>
                <input name="name" value="<?= e($user['name']) ?>" maxlength="80" class="form-input w-full text-sm" placeholder="Nama tampilan">
                <button class="w-full py-2.5 rounded-xl bg-white/10 border border-white/15 text-sm font-semibold hover:bg-white/15 transition">Simpan</button>
            </form>
        </div>
    </div>
</div>

<!-- BADGE / ACHIEVEMENT -->
<div class="glass-card p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-display font-bold text-white">🏅 Koleksi Badge</h2>
        <span class="text-xs text-slate-500"><b class="text-neon-purple"><?= (int) $badgeCount ?></b>/<?= (int) $badgeTotal ?> terbuka</span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php foreach ($badges as $b): $owned = $b['unlocked_at'] !== null; ?>
            <div class="p-4 rounded-xl border text-center transition <?= $owned ? 'bg-gradient-to-b from-violet-600/15 to-transparent border-violet-500/40' : 'bg-white/[.02] border-white/5 opacity-50' ?>"
                 title="<?= e($b['description'] ?? '') ?>">
                <div class="text-3xl <?= $owned ? '' : 'grayscale' ?>"><?= e($b['icon']) ?></div>
                <p class="mt-2 text-xs font-semibold <?= $owned ? 'text-white' : 'text-slate-500' ?>"><?= e($b['name']) ?></p>
                <p class="mt-0.5 text-[10px] <?= $owned ? 'text-emerald-300' : 'text-slate-600' ?>">
                    <?= $owned ? '✓ ' . e(waktu_lalu($b['unlocked_at'])) : '🔒 +' . (int) $b['reward_coins'] . ' koin' ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Riwayat game -->
<div class="glass-card p-6 mt-6">
    <h2 class="font-display font-bold text-white mb-4">🎮 Riwayat Main Game</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 text-sm">
        <?php if (empty($games)): ?><p class="text-slate-500 text-sm col-span-full py-4 text-center">Belum pernah main. <a href="/games" class="text-neon-purple">Coba sekarang →</a></p><?php endif; ?>
        <?php foreach ($games as $g): ?>
            <div class="flex justify-between items-center p-3 rounded-xl bg-white/[.03] border border-white/5">
                <span class="text-slate-400"><?= e(ucwords(str_replace('_', ' ', $g['game']))) ?></span>
                <b class="<?= (int) $g['reward'] > 0 ? 'text-emerald-300' : 'text-red-400/70' ?>"><?= (int) $g['reward'] > 0 ? '+' . (int) $g['reward'] : 'Zonk' ?></b>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Tiket support -->
<div id="support" class="grid lg:grid-cols-2 gap-6 mt-6">
    <div class="glass-card p-6">
        <h2 class="font-display font-bold text-white mb-4">🎫 Buat Tiket Bantuan</h2>
        <form method="post" action="/dashboard/tickets" class="space-y-3">
            <?= csrf_field() ?>
            <input name="subject" required maxlength="160" placeholder="Subjek masalah" class="form-input w-full text-sm">
            <select name="category" class="form-input w-full text-sm">
                <option value="umum">Umum</option><option value="pembayaran">Pembayaran</option>
                <option value="bug">Bug / Error</option><option value="lainnya">Lainnya</option>
            </select>
            <textarea name="message" required minlength="10" rows="3" placeholder="Jelaskan kendala Anda..." class="form-input w-full text-sm"></textarea>
            <button class="w-full py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-sm font-semibold text-white hover:opacity-90 transition">Kirim Tiket</button>
        </form>
    </div>
    <div class="glass-card p-6">
        <h2 class="font-display font-bold text-white mb-4">📬 Tiket Saya</h2>
        <?php if (empty($tickets)): ?><p class="text-sm text-slate-500 py-6 text-center">Belum ada tiket.</p><?php endif; ?>
        <div class="space-y-2 text-sm">
            <?php foreach ($tickets as $t): ?>
                <div class="p-3.5 rounded-xl bg-white/[.03] border border-white/5">
                    <div class="flex justify-between items-center">
                        <p class="font-semibold text-white truncate"><?= e($t['subject']) ?></p>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold
                            <?= $t['status'] === 'open' ? 'bg-amber-500/15 text-amber-300' : ($t['status'] === 'answered' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-500/15 text-slate-400') ?>">
                            <?= strtoupper(e($t['status'])) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1"><?= e($t['category']) ?> · <?= e(waktu_lalu($t['updated_at'])) ?></p>
                    <?php if ($t['status'] !== 'closed'): ?>
                        <form method="post" action="/dashboard/tickets/<?= (int) $t['id'] ?>/reply" class="mt-2 flex gap-2">
                            <?= csrf_field() ?>
                            <input name="message" required minlength="2" placeholder="Balas..." class="form-input flex-1 text-xs py-2">
                            <button class="px-3 rounded-lg bg-white/10 text-xs hover:bg-white/15">➤</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
