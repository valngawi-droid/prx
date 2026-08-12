#!/usr/bin/env bash
# ============================================================
# ChiperX — Start Public (Termux)
# Menyalakan: MariaDB → PHP server (:8009) → Cloudflare Tunnel
# Pakai:
#   export TUNNEL_TOKEN='eyJ...'
#   bash bin/start-public.sh
# ============================================================
set -u
cd "$(dirname "$0")/.." || exit 1

echo "🔐 Mengunci daya (anti-tidur)..."
termux-wake-lock 2>/dev/null || true

# ── 1. MariaDB ──
if mysqladmin -u root ping >/dev/null 2>&1; then
    echo "🗄️  MariaDB: sudah hidup"
else
    echo "🗄️  MariaDB: menyalakan..."
    (mysqld_safe >/dev/null 2>&1 &)
    sleep 10
    mysqladmin -u root ping >/dev/null 2>&1 && echo "🗄️  MariaDB: OK" || { echo "❌ MariaDB gagal hidup — cek log"; exit 1; }
fi

# ── 2. PHP built-in server ──
if pgrep -f "php -S 0.0.0.0:8009" >/dev/null 2>&1; then
    echo "🐘 PHP server: sudah hidup di :8009"
else
    echo "🐘 PHP server: menyalakan di :8009 (8 worker)..."
    mkdir -p storage/logs
    PHP_CLI_SERVER_WORKERS=8 nohup php -S 0.0.0.0:8009 -t public public/index.php >> storage/logs/server.log 2>&1 &
    sleep 2
    pgrep -f "php -S 0.0.0.0:8009" >/dev/null 2>&1 && echo "🐘 PHP server: OK" || { echo "❌ PHP server gagal — baca storage/logs/server.log"; exit 1; }
fi

# ── 3. Cloudflare Tunnel ──
if [ -z "${TUNNEL_TOKEN:-}" ]; then
    echo ""
    echo "⚠️  TUNNEL_TOKEN belum diisi. Ambil di:"
    echo "    dash.cloudflare.com → Zero Trust → Networks → Tunnels → (tunnelmu) → Configure"
    echo "    Lalu jalankan:  export TUNNEL_TOKEN='eyJ...'  && bash bin/start-public.sh"
    echo ""
    echo "ℹ️  Situs LOKAL tetap jalan di http://localhost:8009 (Ctrl+C untuk berhenti di sini tidak perlu — server jalan di background)."
    exit 2
fi

command -v cloudflared >/dev/null 2>&1 || { echo "❌ cloudflared belum terinstal: pkg install cloudflared -y"; exit 1; }
echo "🚇 Cloudflare Tunnel: menghubungkan (biarkan jendela ini terbuka)..."
exec cloudflared tunnel run --token "${TUNNEL_TOKEN}"
