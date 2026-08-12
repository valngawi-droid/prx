<?php /** OWNER — Integrasi & API Keys: edit dari web + uji langsung */ ?>
<?php $v = $values; $isBool = fn(string $k): bool => in_array($k, $bools, true); ?>

<div class="flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="font-display text-xl font-bold text-white">🔑 Integrasi & API Keys</h1>
        <p class="text-xs text-slate-500 mt-1">Disimpan di database (mengalahkan .env) dan <b>berlaku seketika</b> — tanpa restart/server access.</p>
    </div>
    <div class="flex gap-2">
        <form method="post" action="/owner/integrations/test"><?= csrf_field() ?><input type="hidden" name="kind" value="email">
            <button class="px-4 py-2 rounded-xl bg-violet-600/20 border border-violet-500/40 text-violet-200 text-xs font-semibold hover:bg-violet-600/30 transition">🧪 Uji Email</button></form>
        <form method="post" action="/owner/integrations/test"><?= csrf_field() ?><input type="hidden" name="kind" value="discord">
            <button class="px-4 py-2 rounded-xl bg-indigo-600/20 border border-indigo-500/40 text-indigo-200 text-xs font-semibold hover:bg-indigo-600/30 transition">🧪 Uji Discord</button></form>
        <form method="post" action="/owner/integrations/test"><?= csrf_field() ?><input type="hidden" name="kind" value="payment">
            <button class="px-4 py-2 rounded-xl bg-emerald-600/20 border border-emerald-500/40 text-emerald-200 text-xs font-semibold hover:bg-emerald-600/30 transition">🧪 Uji Payment</button></form>
    </div>
</div>

<?php
// ── Panel konfigurasi AKTIF — jawaban instan atas "kok masih pakai yang lama?" ──
$drvNow = strtolower((string) ($live['MAIL_DRIVER']['value'] ?? 'smtp'));
[$drvLabel, $drvTone] = match ($drvNow) {
    'kirimemail' => ['📮 KIRIM.EMAIL API (HTTPS)', 'text-orange-300 border-orange-400/50 bg-orange-500/10'],
    'smtp'       => ['📧 SMTP KLASIK', 'text-sky-300 border-sky-400/50 bg-sky-500/10'],
    'log'        => ['📝 LOG FILE (mode dev)', 'text-slate-300 border-slate-400/50 bg-slate-500/10'],
    default      => ['❓ ' . strtoupper($drvNow), 'text-slate-300 border-slate-500/50 bg-slate-500/10'],
};
$keDom  = (string) ($live['KIRIMEMAIL_DOMAIN']['value'] ?? '');
$fromNow = (string) ($live['MAIL_FROM_ADDRESS']['value'] ?? '');
$fromMismatch = $drvNow === 'kirimemail' && $keDom !== '' && !str_ends_with(strtolower($fromNow), strtolower($keDom));
?>
<div class="glass-card p-4 mt-5 space-y-3">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h2 class="font-display font-bold text-white text-sm">🛰️ Konfigurasi Aktif Saat Ini</h2>
        <span class="px-2.5 py-1 rounded-full border text-[11px] font-bold <?= $drvTone ?>"><?= $drvLabel ?></span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <?php foreach ($live as $k => $info):
            $srcTone = $info['source'] === 'web (DB)' ? 'text-violet-300' : ($info['source'] === '.env' ? 'text-sky-300' : 'text-slate-500');
        ?>
        <div class="rounded-lg bg-white/[0.03] border border-white/10 px-2.5 py-2">
            <p class="text-[10px] text-slate-500 font-mono"><?= e($k) ?></p>
            <p class="text-[11px] text-slate-200 font-mono truncate" title="<?= e($info['value']) ?>"><?= e($info['value'] !== '' ? $info['value'] : '(kosong)') ?></p>
            <p class="text-[9px] <?= $srcTone ?> mt-0.5">sumber: <?= e($info['source']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($fromMismatch): ?>
        <p class="text-[11px] text-amber-300 bg-amber-500/10 border border-amber-400/30 rounded-lg px-3 py-2">⚠️ Driver <b>kirimemail</b> aktif, tapi MAIL_FROM_ADDRESS (<b><?= e($fromNow) ?></b>) bukan alamat @<?= e($keDom) ?> — API akan menolak. Ubah ke <b>otp@<?= e($keDom) ?></b> lalu Simpan.</p>
    <?php elseif ($drvNow === 'smtp' && str_contains($fromNow, 'gmail.com')): ?>
        <p class="text-[11px] text-amber-300 bg-amber-500/10 border border-amber-400/30 rounded-lg px-3 py-2">⚠️ Pengirim masih alamat <b>Gmail</b> — wajar masuk Spam! Ganti MAIL_DRIVER → <b>kirimemail</b> + MAIL_FROM_ADDRESS → <b>otp@domainmu</b> agar terkirim lewat domain terverifikasi (SPF/DKIM).</p>
    <?php endif; ?>
</div>

<form method="post" action="/owner/integrations" class="mt-6 space-y-6">
    <?= csrf_field() ?>

    <!-- UMUM -->
    <div class="glass-card p-6 space-y-4">
        <h2 class="font-display font-bold text-white text-sm">🌐 Umum</h2>
        <div>
            <label class="text-xs text-slate-400">APP_URL <span class="text-amber-400">(dipakai untuk tautan magic-login di email — arahkan ke alamat publik/tunnel kamu!)</span></label>
            <input name="APP_URL" value="<?= e((string) $v['APP_URL']) ?>" class="form-input w-full text-sm mt-1 font-mono" placeholder="http://localhost:8009 atau https://xxxx.trycloudflare.com">
        </div>
    </div>

    <!-- EMAIL / SMTP -->
    <div class="glass-card p-6 space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h2 class="font-display font-bold text-white text-sm">📧 Email / SMTP</h2>
            <div class="flex flex-wrap gap-1.5">
                <span class="text-[10px] text-slate-500 self-center mr-1">Isi cepat:</span>
                <button type="button" onclick="smtpPreset('kirimemail')" class="px-2.5 py-1 rounded-lg bg-orange-500/10 border border-orange-400/40 text-orange-300 text-[11px] font-bold hover:bg-orange-500/20 transition">⚡ Kirim.Email</button>
                <button type="button" onclick="smtpPreset('brevo')" class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-400/40 text-emerald-300 text-[11px] font-bold hover:bg-emerald-500/20 transition">⚡ Brevo</button>
                <button type="button" onclick="smtpPreset('gmail')" class="px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-400/40 text-red-300 text-[11px] font-bold hover:bg-red-500/20 transition">⚡ Gmail</button>
                <button type="button" onclick="smtpPreset('mailjet')" class="px-2.5 py-1 rounded-lg bg-sky-500/10 border border-sky-400/40 text-sky-300 text-[11px] font-bold hover:bg-sky-500/20 transition">⚡ Mailjet</button>
            </div>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <div><label class="text-xs text-slate-400">MAIL_DRIVER</label>
                <select name="MAIL_DRIVER" class="form-input w-full text-sm mt-1">
                    <?php foreach (['smtp' => 'SMTP (kirim sungguhan)', 'kirimemail' => 'Kirim.Email API — HTTPS 443, anti blokir port! 🇮🇩', 'log' => 'Log (tulis ke storage/logs/mail.log)'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $v['MAIL_DRIVER'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="text-xs text-slate-400">SMTP_HOST</label><input name="SMTP_HOST" value="<?= e((string) $v['SMTP_HOST']) ?>" class="form-input w-full text-sm mt-1 font-mono"></div>
            <div><label class="text-xs text-slate-400">SMTP_PORT <span class="text-slate-600">(587 / 465 / 2525)</span></label><input name="SMTP_PORT" value="<?= e((string) $v['SMTP_PORT']) ?>" class="form-input w-full text-sm mt-1 font-mono"></div>
            <div><label class="text-xs text-slate-400">SMTP_ENCRYPTION</label>
                <select name="SMTP_ENCRYPTION" class="form-input w-full text-sm mt-1">
                    <?php foreach (['auto' => 'auto — ikuti port (disarankan)', 'tls' => 'tls (STARTTLS)', 'ssl' => 'ssl', 'none' => 'none (tanpa enkripsi)'] as $enc => $lbl): ?>
                        <option value="<?= $enc ?>" <?= $v['SMTP_ENCRYPTION'] === $enc ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="text-xs text-slate-400">SMTP_USERNAME <span class="text-slate-600">(Login SMTP)</span></label><input name="SMTP_USERNAME" value="<?= e((string) $v['SMTP_USERNAME']) ?>" class="form-input w-full text-sm mt-1 font-mono"></div>
            <div><label class="text-xs text-slate-400">SMTP_PASSWORD <span class="text-slate-600">(SMTP Key — spasi otomatis dihapus)</span></label><input name="SMTP_PASSWORD" value="<?= e((string) $v['SMTP_PASSWORD']) ?>" class="form-input w-full text-sm mt-1 font-mono"></div>
            <div><label class="text-xs text-slate-400">MAIL_FROM_ADDRESS</label><input name="MAIL_FROM_ADDRESS" value="<?= e((string) $v['MAIL_FROM_ADDRESS']) ?>" class="form-input w-full text-sm mt-1 font-mono"></div>
            <div><label class="text-xs text-slate-400">MAIL_FROM_NAME</label><input name="MAIL_FROM_NAME" value="<?= e((string) $v['MAIL_FROM_NAME']) ?>" class="form-input w-full text-sm mt-1"></div>
        </div>
        <div class="rounded-xl border border-emerald-400/25 bg-emerald-500/5 p-4 text-xs text-slate-300 leading-relaxed">
            <p class="font-bold text-emerald-300">📬 Panduan Brevo — gratis 300 email/hari, anti-ribet (direkomendasikan):</p>
            <ol class="list-decimal ml-4 mt-1.5 space-y-1 text-slate-400">
                <li>Daftar gratis di <b class="text-slate-200">brevo.com</b> → verifikasi email pendaftaranmu.</li>
                <li>Buka menu <b class="text-slate-200">SMTP &amp; API</b> → tab <b class="text-slate-200">SMTP</b> → <b class="text-slate-200">Generate a new SMTP key</b>, beri nama bebas.</li>
                <li>Tekan tombol <b class="text-emerald-300">⚡ Brevo</b> di atas → isi <code class="text-emerald-300">SMTP_USERNAME</code> dengan <b class="text-slate-200">Login</code> yang ditampilkan Brevo (biasanya email akunmu), dan <code class="text-emerald-300">SMTP_PASSWORD</code> dengan <b class="text-slate-200">SMTP key</b> (diawali <code>xsmtpsib-…</code>).</li>
                <li><b class="text-amber-300">MAIL_FROM_ADDRESS WAJIB = email yang dipakai daftar Brevo</b> (sender bawaan yang sudah terverifikasi). Jika beda, Brevo akan menolak kiriman.</li>
                <li><b class="text-slate-200">Simpan</b> → tekan <b class="text-slate-200">🧪 Uji Email</b> di pojok kanan atas. Gagal? Pesan error kini menampilkan <b class="text-slate-200">alasan pastinya</b>. Port 587 diblokir operator? Coba <b class="text-slate-200">465</b> atau <b class="text-slate-200">2525</b> (Brevo mendukung ketiganya).</li>
            </ol>
        </div>
        <script>
        function smtpPreset(p) {
            const map = {
                kirimemail: ['smtp.kirimemail.com', '587'],
                brevo:   ['smtp-relay.brevo.com', '587'],
                gmail:   ['smtp.gmail.com', '587'],
                mailjet: ['in-v3.mailjet.com', '587'],
            };
            const m = map[p]; if (!m) return;
            const h = document.querySelector('[name="SMTP_HOST"]');
            const q = document.querySelector('[name="SMTP_PORT"]');
            const e2 = document.querySelector('[name="SMTP_ENCRYPTION"]');
            if (h) h.value = m[0];
            if (q) q.value = m[1];
            if (e2) e2.value = 'auto';
            [h, q].forEach(el => { if (el) { el.classList.add('ring-2', 'ring-emerald-400'); setTimeout(() => el.classList.remove('ring-2', 'ring-emerald-400'), 1400); } });
        }
        </script>
    </div>

    <!-- KIRIM.EMAIL API -->
    <div class="glass-card p-6 space-y-4" style="border-color: rgba(251,146,60,.25);">
        <div class="flex items-center gap-2 flex-wrap">
            <h2 class="font-display font-bold text-white text-sm">📮 Kirim.Email — Transactional API v4</h2>
            <span class="px-2 py-0.5 rounded-full bg-orange-500/15 border border-orange-400/40 text-orange-300 text-[10px] font-bold">Termux-friendly 🇮🇩</span>
        </div>
        <p class="text-xs text-slate-400 leading-relaxed">Jalur alternatif <b class="text-slate-200">tanpa port SMTP</b>: berjalan lewat HTTPS (port 443) yang hampir tak pernah diblokir operator seluler — cocok untuk HP/Termux. Aktifkan dengan memilih <code class="text-orange-300">MAIL_DRIVER = kirimemail</code> pada kartu Email/SMTP di atas. Tombol <b>🧪 Uji Email</b> otomatis memakai jalur ini saat driver-nya aktif.</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div><label class="text-xs text-slate-400">KIRIMEMAIL_API_KEY <span class="text-slate-600">(key_…)</span></label><input name="KIRIMEMAIL_API_KEY" value="<?= e((string) $v['KIRIMEMAIL_API_KEY']) ?>" class="form-input w-full text-sm mt-1 font-mono" placeholder="key_xxxxxxxxxxxxxxxx"></div>
            <div><label class="text-xs text-slate-400">KIRIMEMAIL_API_SECRET</label><input name="KIRIMEMAIL_API_SECRET" value="<?= e((string) $v['KIRIMEMAIL_API_SECRET']) ?>" class="form-input w-full text-sm mt-1 font-mono" placeholder="64 karakter hex"></div>
            <div><label class="text-xs text-slate-400">KIRIMEMAIL_DOMAIN</label><input name="KIRIMEMAIL_DOMAIN" value="<?= e((string) $v['KIRIMEMAIL_DOMAIN']) ?>" class="form-input w-full text-sm mt-1 font-mono" placeholder="chiperx.cyou"></div>
        </div>
        <div class="rounded-xl border border-orange-400/25 bg-orange-500/5 p-4 text-xs text-slate-300 leading-relaxed">
            <p class="font-bold text-orange-300">📮 Setup Kirim.Email (sesuai kredensial kamu):</p>
            <ol class="list-decimal ml-4 mt-1.5 space-y-1 text-slate-400">
                <li><b class="text-slate-200">Jalur API (disarankan):</b> isi 3 kolom di atas (API key <code>key_…</code> + secret dari dashboard app.kirim.email → Transactional → API), domain <code class="text-orange-300">chiperx.cyou</code>, lalu set <code class="text-orange-300">MAIL_DRIVER = kirimemail</code>.</li>
                <li><b class="text-slate-200">MAIL_FROM_ADDRESS</b> (kartu Email/SMTP) wajib memakai alamat di domain tersebut — milikmu: <code class="text-orange-300">otp@chiperx.cyou</code>.</li>
                <li><b class="text-slate-200">Jalur SMTP (cadangan):</b> tekan <b class="text-orange-300">⚡ Kirim.Email</b> di kartu SMTP (host <code>smtp.kirimemail.com:587</code>), username <code>otp@chiperx.cyou</code>, password = SMTP password dari dashboard, lalu set <code>MAIL_DRIVER = smtp</code>.</li>
                <li>Email gagal masuk? Pastikan <b class="text-slate-200">SPF/DKIM domain sudah terverifikasi hijau</b> di dashboard Kirim.Email — kalau belum, email berisiko nyasar ke Spam.</li>
            </ol>
        </div>
    </div>

    <!-- DISCORD -->
    <div class="glass-card p-6 space-y-4">
        <h2 class="font-display font-bold text-white text-sm">💬 Discord Webhook</h2>
        <div class="grid md:grid-cols-2 gap-4 items-end">
            <div><label class="text-xs text-slate-400">DISCORD_WEBHOOK_URL</label><input name="DISCORD_WEBHOOK_URL" value="<?= e((string) $v['DISCORD_WEBHOOK_URL']) ?>" class="form-input w-full text-sm mt-1 font-mono"></div>
            <label class="flex items-center gap-2 text-sm text-slate-300 pb-2">
                <input type="checkbox" name="DISCORD_ENABLED" <?= $v['DISCORD_ENABLED'] !== 'false' ? 'checked' : '' ?> class="accent-violet-500 w-4 h-4"> Aktifkan pengiriman log ke Discord
            </label>
        </div>
    </div>

    <!-- PAYMENT -->
    <div class="glass-card p-6 space-y-5">
        <h2 class="font-display font-bold text-white text-sm">💳 Payment Gateway</h2>
        <div class="max-w-xs">
            <label class="text-xs text-slate-400">Driver aktif</label>
            <select name="PAYMENT_DRIVER" class="form-input w-full text-sm mt-1">
                <?php foreach (['pakasir', 'duitku', 'tripay', 'midtrans', 'paydisini'] as $d): ?>
                    <option value="<?= $d ?>" <?= $v['PAYMENT_DRIVER'] === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php
        $groups = [
            '🟢 Pakasir (paling gampang)' => ['PAKASIR_SLUG', 'PAKASIR_API_KEY'],
            '🔵 Duitku'                  => ['DUITKU_MERCHANT_CODE', 'DUITKU_API_KEY'],
            '🟣 Tripay'                  => ['TRIPAY_API_KEY', 'TRIPAY_PRIVATE_KEY', 'TRIPAY_MERCHANT_CODE'],
            '🔷 Midtrans'                => ['MIDTRANS_SERVER_KEY', 'MIDTRANS_CLIENT_KEY'],
            '🟠 Paydisini'               => ['PAYDISINI_API_KEY'],
        ];
        $sandboxOf = [
            '🔵 Duitku'    => 'DUITKU_SANDBOX',
            '🟣 Tripay'    => 'TRIPAY_SANDBOX',
            '🔷 Midtrans'  => 'MIDTRANS_SANDBOX',
            '🟠 Paydisini' => 'PAYDISINI_SANDBOX',
        ];
        foreach ($groups as $label => $keys):
            $sandboxKey = $sandboxOf[$label] ?? null;
        ?>
        <div class="rounded-xl border border-white/10 p-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <p class="text-sm font-semibold text-slate-200"><?= $label ?></p>
                <?php if ($sandboxKey): ?>
                <label class="flex items-center gap-2 text-xs text-slate-400">
                    <input type="checkbox" name="<?= $sandboxKey ?>" <?= $v[$sandboxKey] !== 'false' ? 'checked' : '' ?> class="accent-violet-500 w-4 h-4"> Mode Sandbox
                </label>
                <?php endif; ?>
            </div>
            <div class="grid md:grid-cols-3 gap-3 mt-3">
                <?php foreach ($keys as $key): ?>
                <div><label class="text-[11px] text-slate-500 font-mono"><?= $key ?></label>
                    <input name="<?= $key ?>" value="<?= e((string) $v[$key]) ?>" class="form-input w-full text-sm mt-1 font-mono" autocomplete="off"></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <button class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 font-semibold text-white hover:opacity-90 transition shadow-lg shadow-violet-600/30">
        💾 Simpan Semua & Langsung Berlaku
    </button>
</form>
