#!/usr/bin/env bash
# ============================================================
# ChiperX — Start Public (Termux)
# Menyalakan: MariaDB → PHP server (:8009) → Cloudflare Tunnel
#
# 3 mode:
#   bash bin/start-public.sh              → tunnel bernama "chiperx" (subdomain sendiri, GRATIS tanpa dashboard Zero Trust)
#   bash bin/start-public.sh nama-tunnel  → tunnel bernama lain
#   bash bin/start-public.sh --quick      → trycloudflare URL acak (tanpa akun sama sekali, bukan domain sendiri)
#   TUNNEL_TOKEN='eyJ...' bash bin/start-public.sh → mode token dashboard
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
command -v cloudflared >/dev/null 2>&1 || { echo "❌ cloudflared belum terinstal: pkg install cloudflared -y"; exit 1; }
CF_DIR="$HOME/.cloudflared"
# Matikan auto-update via ENV (posisi flag --no-autoupdate berbeda-beda antar
# versi cloudflared — di sebagian versi flag itu milik 'tunnel', bukan 'run')
export NO_AUTOUPDATE=true

if [ "${1:-}" = "--quick" ]; then
    echo "🚇 Mode QUICK tunnel — URL acak trycloudflare.com (bukan domain sendiri)..."
    exec cloudflared tunnel --url http://localhost:8009
fi

if [ -n "${TUNNEL_TOKEN:-}" ]; then
    echo "🚇 Cloudflare Tunnel (mode token): menghubungkan..."
    exec cloudflared tunnel run --token "${TUNNEL_TOKEN}"
fi

if [ -f "$CF_DIR/config.yml" ]; then
    TUNNEL_NAME="${1:-chiperx}"
    echo "🚇 Cloudflare Tunnel '${TUNNEL_NAME}' (subdomain sendiri): menghubungkan..."
    exec cloudflared tunnel run "${TUNNEL_NAME}"
fi

# ── Belum setup: panduan CLI murni (GRATIS, tanpa Zero Trust dashboard) ──
cat <<'EOF'

⚠️  Tunnel belum disetup. Setup SEKALI saja — full CMD, GRATIS,
   tanpa kartu/PayPal, tanpa masuk dashboard Zero Trust:

   1) cloudflared tunnel login
      → muncul URL; buka di Chrome, login Cloudflare,
        pilih chiperx.cyou, tekan "Authorize". (satu-satunya ketukan browser)

   2) cloudflared tunnel create chiperx
      → catat UUID + path credentials json yang ditampilkan

   3) cloudflared tunnel route dns chiperx app.chiperx.cyou
      → CNAME subdomain otomatis terpasang di DNS-mu ✅

   4) nano ~/.cloudflared/config.yml   — isi:

        tunnel: UUID-DARI-LANGKAH-2
        credentials-file: /data/data/com.termux/files/home/.cloudflared/UUID-DARI-LANGKAH-2.json
        ingress:
          - hostname: app.chiperx.cyou
            service: http://localhost:8009
          - service: http_status:404

   5) bash bin/start-public.sh

   Jangan lupa setelah live:
   - Web Owner → 🧩 Integrasi → APP_URL = https://app.chiperx.cyou → Simpan
   - sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

   (Mau coba-coba dulu tanpa akun?  bash bin/start-public.sh --quick)

EOF
echo "ℹ️  Situs LOKAL tetap jalan di http://localhost:8009 (di background)."
exit 2
