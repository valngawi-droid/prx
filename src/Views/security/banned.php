<?php
/**
 * HALAMAN BLOKIR FIREWALL — standalone (CSS inline, tanpa aset situs,
 * karena IP pelaku diblok total dari seluruh request).
 * Variabel: $mode ('temp'|'perm'), $reason, $ip, $untilTs (?int), $strikes (int)
 */
$isPerm   = $mode === 'perm';
$secsLeft = (!$isPerm && $untilTs) ? max(0, $untilTs - time()) : 0;
$title    = $isPerm ? 'DIBLOKIR PERMANEN' : 'AKSES DITANGGUHKAN';
$icon     = $isPerm ? '💀' : '🛡️';
$accent   = $isPerm ? '#ef4444' : '#f59e0b';
$accent2  = $isPerm ? '#b91c1c' : '#f97316';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?= $isPerm ? '⛔' : '⚠️' ?> <?= e($title) ?> — ChiperX Security</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family:'Segoe UI',system-ui,-apple-system,sans-serif; min-height:100vh;
        background:#050810; color:#e2e8f0; display:grid; place-items:center; padding:24px;
        background-image:
            radial-gradient(600px 400px at 20% 10%, <?= $isPerm ? 'rgba(239,68,68,.14)' : 'rgba(245,158,11,.12)' ?>, transparent 60%),
            radial-gradient(500px 380px at 85% 85%, rgba(139,92,246,.12), transparent 60%);
        overflow-x:hidden;
    }
    /* grid siber halus */
    body::before {
        content:''; position:fixed; inset:0; pointer-events:none; opacity:.35;
        background-image:linear-gradient(rgba(148,163,184,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(148,163,184,.05) 1px,transparent 1px);
        background-size:44px 44px;
        mask-image:radial-gradient(ellipse at center, black 30%, transparent 75%);
    }
    .card {
        position:relative; max-width:520px; width:100%; text-align:center;
        background:rgba(15,23,42,.72); backdrop-filter:blur(18px);
        border:1px solid <?= $accent ?>44; border-radius:28px; padding:44px 30px 36px;
        box-shadow:0 0 60px <?= $accent ?>26, inset 0 1px 0 rgba(255,255,255,.06);
        animation:cardIn .5s cubic-bezier(.2,.9,.3,1.2);
    }
    @keyframes cardIn { from{opacity:0; transform:translateY(26px) scale(.96);} to{opacity:1; transform:none;} }
    .icon {
        font-size:64px; display:inline-block; filter:drop-shadow(0 0 22px <?= $accent ?>aa);
        animation:floaty 3s ease-in-out infinite;
    }
    @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-9px)} }
    .ring {
        position:absolute; inset:-14px; border-radius:50%; border:2px solid <?= $accent ?>55;
        animation:sonar 2.2s ease-out infinite;
    }
    @keyframes sonar { from{transform:scale(.7);opacity:1} to{transform:scale(1.5);opacity:0} }
    .iconwrap { position:relative; display:inline-block; margin-bottom:18px; }
    h1 {
        font-size:clamp(22px,5.5vw,32px); letter-spacing:.5px; font-weight:800;
        background:linear-gradient(90deg,<?= $accent ?>,<?= $accent2 ?>,<?= $accent ?>);
        -webkit-background-clip:text; background-clip:text; color:transparent;
        background-size:200% auto; animation:shine 3s linear infinite;
    }
    @keyframes shine { to{background-position:200% center;} }
    .sub { color:#94a3b8; font-size:14px; margin-top:10px; line-height:1.7; }
    .chip {
        display:inline-block; margin-top:16px; padding:8px 16px; border-radius:999px; font-size:12px; font-weight:700;
        background:<?= $accent ?>1a; border:1px solid <?= $accent ?>55; color:<?= $accent ?>;
    }
    .panel {
        margin-top:22px; display:grid; gap:10px; text-align:left; font-size:12.5px;
        background:rgba(2,6,23,.6); border:1px solid rgba(148,163,184,.14); border-radius:16px; padding:16px 18px;
    }
    .row { display:flex; justify-content:space-between; gap:14px; }
    .row b { color:#f1f5f9; font-family:ui-monospace,Consolas,monospace; font-size:12px; word-break:break-all; text-align:right; }
    .row span { color:#64748b; white-space:nowrap; }
    .timer {
        margin-top:20px; font-family:ui-monospace,Consolas,monospace; font-size:44px; font-weight:800;
        color:<?= $accent ?>; text-shadow:0 0 24px <?= $accent ?>88; letter-spacing:2px;
    }
    .bar { margin-top:10px; height:8px; border-radius:99px; background:rgba(148,163,184,.15); overflow:hidden; }
    .bar > i { display:block; height:100%; border-radius:99px; background:linear-gradient(90deg,<?= $accent ?>,<?= $accent2 ?>); transition:width 1s linear; box-shadow:0 0 14px <?= $accent ?>; }
    .note { margin-top:18px; font-size:11.5px; color:#64748b; line-height:1.7; }
    .note b { color:#94a3b8; }
    .foot { margin-top:26px; font-size:10.5px; color:#475569; letter-spacing:2.5px; text-transform:uppercase; }
    .foot b { color:#a78bfa; }
    .saw { height:2px; margin-top:20px; background:repeating-linear-gradient(90deg,<?= $accent ?> 0 12px,transparent 12px 20px); opacity:.5; border-radius:2px; }
</style>
</head>
<body>
<div class="card">
    <div class="iconwrap">
        <span class="ring"></span>
        <span class="icon"><?= $icon ?></span>
    </div>
    <h1><?= e($title) ?></h1>
    <p class="sub">
        <?php if ($isPerm): ?>
            Sistem keamanan mendeteksi <b style="color:#fca5a5;">percobaan deface/peretasan</b> dari koneksi Anda.<br>
            Sesuai kebijakan ChiperX, IP Anda <b style="color:#fca5a5;">diblokir SELAMANYA</b> dari seluruh layanan.
        <?php else: ?>
            <b style="color:#fcd34d;">PERINGATAN!</b> Aktivitas mencurigakan terdeteksi dari koneksi Anda.<br>
            IP Anda ditangguhkan selama <b style="color:#fcd34d;">10 menit</b>. Pelanggaran berulang = <b style="color:#f87171;">BLOKIR PERMANEN</b>.
        <?php endif; ?>
    </p>
    <span class="chip">⚡ <?= e($reason) ?></span>

    <?php if (!$isPerm && $secsLeft > 0): ?>
        <div class="timer" id="cd">--:--</div>
        <div class="bar"><i id="barFill" style="width:100%"></i></div>
    <?php endif; ?>

    <div class="panel">
        <div class="row"><span>🌐 Alamat IP</span><b><?= e($ip) ?></b></div>
        <div class="row"><span>⚖️ Status</span><b><?= $isPerm ? 'PERMANEN ⛔' : 'Sementara (10 menit)' ?></b></div>
        <div class="row"><span>🎯 Pelanggaran ke-</span><b><?= (int) $strikes ?> kali</b></div>
        <div class="row"><span>🕒 Waktu (WIB)</span><b><?= e(date('d M Y H:i:s')) ?></b></div>
    </div>

    <p class="note">
        Merasa ini kesalahan? Hubungi admin ChiperX melalui konten resmi setelah masa hukuman berakhir.<br>
        <b>Semua percobaan serangan tercatat & dilaporkan otomatis ke tim keamanan.</b> 🚨
    </p>
    <div class="saw"></div>
    <p class="foot">🛡️ <b>ChiperX</b> Web Application Firewall</p>
</div>

<?php if (!$isPerm && $secsLeft > 0): ?>
<script>
(function () {
    let left = <?= (int) $secsLeft ?>;
    const total = left, cd = document.getElementById('cd'), bar = document.getElementById('barFill');
    const fmt = s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    const tick = () => {
        cd.textContent = fmt(Math.max(0, left));
        bar.style.width = (left / total * 100) + '%';
        if (left <= 0) { cd.textContent = 'SELESAI ✓'; bar.style.width = '0%'; setTimeout(() => location.reload(), 1500); return; }
        left--; setTimeout(tick, 1000);
    };
    tick();
})();
</script>
<?php endif; ?>
</body>
</html>
