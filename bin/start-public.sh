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

# ── Mode tunnel bernama: AUTO-PERBAIKI config.yml ──
# Bila file kredensial *.json sudah ada (dari 'tunnel create') tapi config.yml
# belum ada/masih berisi placeholder UUID-TUNNELMU → tulis ulang otomatis.
CRED_JSON="$(find "$CF_DIR" -maxdepth 1 -name '*.json' 2>/dev/null | head -n1)"
if [ -n "$CRED_JSON" ]; then
    T_UUID="$(basename "$CRED_JSON" .json)"
    PUB_HOST="${TUNNEL_HOSTNAME:-app.chiperx.cyou}"
    if [ ! -f "$CF_DIR/config.yml" ] || grep -q 'UUID-TUNNELMU' "$CF_DIR/config.yml" 2>/dev/null; then
        cat > "$CF_DIR/config.yml" <<YAML
tunnel: $T_UUID
credentials-file: $CRED_JSON
ingress:
  - hostname: $PUB_HOST
    service: http://localhost:8009
  - service: http_status:404
YAML
        echo "🛠️  config.yml dibuat otomatis → UUID ${T_UUID} | hostname ${PUB_HOST}"
        echo "    (ganti subdomain: TUNNEL_HOSTNAME=panel.chiperx.cyou bash bin/start-public.sh)"
    fi
    # Pastikan DNS route terpasang (aman diulang; butuh cert.pem dari 'tunnel login')
    if [ -f "$CF_DIR/cert.pem" ]; then
        cloudflared tunnel route dns --overwrite-dns "$T_UUID" "$PUB_HOST" 2>&1 | tail -n1 || true
    fi
    echo "🚇 Cloudflare Tunnel → https://${PUB_HOST} : menghubungkan..."
    exec cloudflared tunnel run
fi

if [ -f "$CF_DIR/config.yml" ]; then
    TUNNEL_NAME="${1:-chiperx}"
    echo "🚇 Cloudflare Tunnel '${TUNNEL_NAME}' (subdomain sendiri): menghubungkan..."
    exec cloudflared tunnel run "${TUNNEL_NAME}"
fi

# ── Belum setup: panduan CLI murni (GRATIS, tanpa Zero Trust dashboard) ──
cat <<'EOF'

⚠️  Tunnel belum disetup. Setup SEKALI saja — full CMD, GRATIS:

   1) cloudflared tunnel login
      → buka URL-nya di Chrome, login Cloudflare, pilih chiperx.cyou, "Authorize".

   2) cloudflared tunnel create chiperx

   3) bash bin/start-public.sh   ← jalankan lagi: config.yml + DNS route
      ditulis OTOMATIS (tidak perlu edit nano sama sekali!).

   Subdomain bawaan: app.chiperx.cyou — ganti dengan:
   TUNNEL_HOSTNAME=panel.chiperx.cyou bash bin/start-public.sh

   Jangan lupa setelah live:
   - Web Owner → 🧩 Integrasi → APP_URL = https://app.chiperx.cyou → Simpan
   - sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

   (Mau coba-coba dulu tanpa akun?  bash bin/start-public.sh --quick)

EOF
echo "ℹ️  Situs LOKAL tetap jalan di http://localhost:8009 (di background)."
exit 2
