<?php /** Halaman 419 — Sesi/CSRF kedaluwarsa. STANDALONE: tanpa layout, tanpa DB, selalu berhasil dirender. */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>419 — Sesi Kedaluwarsa — ChiperX</title>
<style>
    * { margin: 0; box-sizing: border-box; }
    body {
        min-height: 100vh; display: grid; place-items: center; padding: 24px;
        background: #0f172a; color: #e2e8f0;
        font-family: 'Segoe UI', Inter, system-ui, sans-serif;
    }
    .card {
        max-width: 460px; width: 100%; text-align: center; padding: 40px 32px;
        background: rgba(30,41,59,.9); border: 1px solid rgba(139,92,246,.4);
        border-radius: 20px; box-shadow: 0 0 60px rgba(139,92,246,.15);
        animation: muncul .5s ease both;
    }
    @keyframes muncul { from { opacity: 0; transform: translateY(16px) scale(.97); } to { opacity: 1; transform: none; } }
    .code {
        font-size: 64px; font-weight: 800; letter-spacing: 4px;
        background: linear-gradient(90deg, #a78bfa, #22d3ee);
        -webkit-background-clip: text; background-clip: text; color: transparent;
        text-shadow: 0 0 40px rgba(167,139,250,.35);
    }
    h1 { font-size: 20px; margin: 8px 0 12px; color: #fff; }
    p { font-size: 14px; color: #94a3b8; line-height: 1.7; }
    .btn {
        display: inline-block; margin-top: 22px; padding: 13px 26px; border-radius: 12px;
        background: linear-gradient(90deg, #7c3aed, #06b6d4); color: #fff !important;
        font-weight: 600; font-size: 14px; text-decoration: none;
        box-shadow: 0 8px 24px rgba(124,58,237,.35); transition: transform .15s ease, opacity .15s ease;
    }
    .btn:hover { transform: translateY(-2px); opacity: .92; }
    .hint { margin-top: 16px; font-size: 12px; color: #64748b; }
    .hint b { color: #22d3ee; }
</style>
</head>
<body>
<div class="card">
    <div class="code">419</div>
    <h1>Sesi Kedaluwarsa ⏳</h1>
    <p>Demi keamanan, token formulir Anda sudah tidak berlaku — biasanya karena halaman dibuka terlalu lama sebelum dikirim. Tidak ada data yang hilang; silakan ulangi dari awal.</p>
    <a class="btn" href="/login">↺ Mulai Lagi dari Login</a>
    <p class="hint">Otomatis kembali ke halaman login dalam <b id="cd">8</b> detik…</p>

    <?php
    // Kotak forensik — hanya tampil saat APP_DEBUG=true, untuk diagnosis cepat
    if (isset($debug) && is_array($debug) && \ChiperX\Core\Config::isDebug()): ?>
    <pre style="margin-top:18px;text-align:left;font-size:11px;line-height:1.5;color:#fbbf24;background:#0b1120;border:1px solid rgba(251,191,36,.3);border-radius:10px;padding:12px;overflow-x:auto;white-space:pre-wrap;">DEBUG-419 <?= htmlspecialchars(json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
</div>
<script>
    (function () {
        var sisa = 8, el = document.getElementById('cd');
        var t = setInterval(function () {
            sisa--;
            if (el) el.textContent = sisa;
            if (sisa <= 0) { clearInterval(t); window.location.replace('/login'); }
        }, 1000);
        // Berhenti menghitung bila user berinteraksi (klik tombol)
        document.querySelector('.btn')?.addEventListener('click', function () { clearInterval(t); });
    })();
</script>
</body>
</html>
