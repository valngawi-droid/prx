<?php
/** 🚧 Halaman MODE PEMELIHARAAN — standalone (tanpa layout, inline CSS) */
$note = trim((string) ($note ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>Sedang Pemeliharaan — ChiperX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh; display: grid; place-items: center; padding: 24px;
            background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
        }
        .glow { position: fixed; border-radius: 50%; filter: blur(120px); opacity: .25; pointer-events: none; }
        .glow.a { width: 420px; height: 420px; background: #7c3aed; top: -120px; right: -100px; animation: float 9s ease-in-out infinite; }
        .glow.b { width: 340px; height: 340px; background: #06b6d4; bottom: -120px; left: -90px; animation: float 11s ease-in-out infinite reverse; }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(26px); } }
        .card {
            position: relative; max-width: 430px; width: 100%; text-align: center;
            background: rgba(255,255,255,.04); border: 1px solid rgba(167,139,250,.25);
            border-radius: 28px; padding: 44px 30px;
            backdrop-filter: blur(18px); box-shadow: 0 30px 70px -20px rgba(0,0,0,.8), inset 0 1.5px 0 rgba(255,255,255,.12);
            animation: up .6s cubic-bezier(.22,1,.36,1);
        }
        @keyframes up { from { opacity: 0; transform: translateY(22px); } }
        .icon { font-size: 64px; display: inline-block; animation: bob 2.6s ease-in-out infinite; filter: drop-shadow(0 0 26px rgba(167,139,250,.8)); }
        @keyframes bob { 0%,100% { transform: translateY(0) rotate(-2deg); } 50% { transform: translateY(-12px) rotate(2deg); } }
        h1 { font-size: 26px; margin: 18px 0 10px; background: linear-gradient(90deg,#c4b5fd,#67e8f9); -webkit-background-clip: text; background-clip: text; color: transparent; }
        p { font-size: 14px; color: #94a3b8; line-height: 1.7; }
        .note { margin-top: 16px; padding: 12px 16px; border-radius: 14px; background: rgba(34,211,238,.07); border: 1px solid rgba(34,211,238,.25); color: #a5f3fc; font-size: 13px; }
        .bar { margin: 22px auto 0; height: 6px; width: 70%; border-radius: 99px; background: rgba(255,255,255,.08); overflow: hidden; }
        .bar i { display: block; height: 100%; width: 40%; border-radius: 99px; background: linear-gradient(90deg,#a78bfa,#22d3ee); animation: slide 1.6s ease-in-out infinite; }
        @keyframes slide { 0% { transform: translateX(-110%); } 100% { transform: translateX(280%); } }
        .refresh { font-size: 11px; color: #475569; margin-top: 18px; }
        .staff { display: inline-block; margin-top: 8px; font-size: 12px; color: #22d3ee; text-decoration: none; border-bottom: 1px dashed rgba(34,211,238,.4); }
    </style>
</head>
<body>
    <div class="glow a"></div><div class="glow b"></div>
    <div class="card">
        <span class="icon">🚧</span>
        <h1>Sedang Pemeliharaan</h1>
        <p>ChiperX sedang ditingkatkan oleh tim kami.<br>Sebentar lagi kembali — biasanya cuma beberapa menit! ⚡</p>
        <?php if ($note !== ''): ?>
            <p class="note">📢 <?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="bar"><i></i></div>
        <p class="refresh">Halaman memuat ulang otomatis tiap 60 detik…</p>
        <a class="staff" href="/login">🔐 Kamu staf? Masuk di sini</a>
    </div>
    <script>setTimeout(() => location.reload(), 60000);</script>
</body>
</html>
