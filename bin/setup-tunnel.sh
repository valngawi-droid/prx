#!/usr/bin/env bash
# ============================================================
# ChiperX — Pasang Cloudflare Tunnel sebagai SERVICE systemd di VPS
# (auto-nyala tiap reboot — bersama docker, inilah combo 24/7)
#
# Cara pakai (di VPS, root):
#   bash bin/setup-tunnel.sh /root/UUID-TUNNEL.json
# atau mode token dashboard:
#   TUNNEL_TOKEN='eyJ...' bash bin/setup-tunnel.sh
# ============================================================
set -euo pipefail
SUDO=""
[ "$(id -u)" -ne 0 ] && SUDO="sudo"

# ── 1. Install cloudflared bila belum ada ──
if ! command -v cloudflared >/dev/null 2>&1; then
    echo "🚇 Menginstal cloudflared..."
    ARCH=$(dpkg --print-architecture 2>/dev/null || echo "amd64")
    URL="https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-${ARCH}.deb"
    curl -fsSL "$URL" -o /tmp/cloudflared.deb
    $SUDO dpkg -i /tmp/cloudflared.deb || { $SUDO apt-get update -y && $SUDO apt-get install -f -y; }
    rm -f /tmp/cloudflared.deb
fi

export NO_AUTOUPDATE=true

# ── 2A. Mode TOKEN (dashboard Zero Trust) ──
if [ -n "${TUNNEL_TOKEN:-}" ]; then
    echo "🚇 Memasang service (mode token)..."
    $SUDO cloudflared service install "$TUNNEL_TOKEN"
    $SUDO systemctl enable --now cloudflared
    echo "✅ Tunnel aktif 24/7 sebagai service. Cek: systemctl status cloudflared"
    exit 0
fi

# ── 2B. Mode FILE KREDENSIAL (pindahan dari Termux) ──
CRED_IN="${1:-}"
if [ -z "$CRED_IN" ] || [ ! -f "$CRED_IN" ]; then
    cat <<HELP
⚠️  File kredensial tidak ditemukan: '${CRED_IN}'

Cara ambil dari Termux (HP) — jalankan di HP:
    scp ~/.cloudflared/*.json root@IP_VPS_KAMU:/root/
Lalu di VPS:
    bash bin/setup-tunnel.sh /root/NAMA-FILE.json

(Atau mode token:  TUNNEL_TOKEN='eyJ...' bash bin/setup-tunnel.sh)
HELP
    exit 1
fi

UUID="$(basename "$CRED_IN" .json)"
echo "🚇 Tunnel UUID: $UUID"

$SUDO mkdir -p /etc/cloudflared
$SUDO cp "$CRED_IN" /etc/cloudflared/
$SUDO chmod 600 /etc/cloudflared/"$UUID".json

# Tulis config.yml (ingress → nginx lokal 127.0.0.1:8080)
HOSTNAME_PUB="${TUNNEL_HOSTNAME:-app.chiperx.cyou}"
$SUDO tee /etc/cloudflared/config.yml >/dev/null <<YAML
tunnel: $UUID
credentials-file: /etc/cloudflared/$UUID.json
ingress:
  - hostname: $HOSTNAME_PUB
    service: http://127.0.0.1:8080
  - service: http_status:404
YAML

echo "🧾 /etc/cloudflared/config.yml → https://$HOSTNAME_PUB → 127.0.0.1:8080"

# Route DNS (aman diulang) bila cert.pem juga dipindah & masih valid
if [ -f /root/.cloudflared/cert.pem ] || [ -f "$HOME/.cloudflared/cert.pem" ]; then
    cloudflared tunnel route dns --overwrite-dns "$UUID" "$HOSTNAME_PUB" 2>&1 | tail -n1 || true
fi

# ── 3. Jadikan service systemd (auto-nyala tiap reboot = 24/7!) ──
if systemctl list-unit-files | grep -q '^cloudflared'; then
    $SUDO systemctl restart cloudflared
else
    $SUDO cloudflared service install
fi
$SUDO systemctl enable --now cloudflared

echo ""
echo "✅ SELESAI! Tunnel aktif 24/7 sebagai service systemd."
echo "   Uji: curl -I https://$HOSTNAME_PUB"
echo "   Log: journalctl -u cloudflared -f"
echo ""
echo "💡 TERAKHIR: di Termux boleh dimatikan (ctrl+c tunnel & php) —"
echo "   VPS sekarang yang bertugas. HP bebas!"
