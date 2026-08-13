#!/usr/bin/env bash
# ============================================================
# ChiperX — INSTALASI 1 PERINTAH DI VPS (Ubuntu 22.04/24.04, Debian 11+)
#
# Cara pakai (di VPS, sebagai root):
#   git clone -b arena/019ff184-prx https://github.com/valngawi-droid/prx.git /opt/chiperx
#   cd /opt/chiperx && bash bin/install-vps.sh
#
# Yang dilakukan skrip ini (semua otomatis):
#   1) Install Docker + Compose plugin
#   2) Buat .env produksi (password DB & APP_KEY ACAK-kuat)
#   3) Samakan password MySQL di docker-compose.yml dengan .env
#   4) Amankan port (MySQL & web hanya di 127.0.0.1 — tunnel saja dari luar)
#   5) docker compose up -d --build  (restart:unless-stopped = 24/7!)
#   6) Swap 2 GB otomatis bila RAM < 1.5 GB (VPS murah aman)
#   7) Cron housekeeping tiap 5 menit
#   8) Cek kesehatan: docker exec chiperx_app php bin/doctor.php
#
# Re-run AMAN (idempoten): .env lama tidak ditimpa, password tidak berubah.
# ============================================================
set -euo pipefail

SUDO=""
[ "$(id -u)" -ne 0 ] && SUDO="sudo"

APP_URL="${APP_URL:-https://app.chiperx.cyou}"
BRANCH="${CHIPERX_BRANCH:-arena/019ff184-prx}"
REPO_URL="https://github.com/valngawi-droid/prx.git"
INSTALL_DIR_DEFAULT="/opt/chiperx"

echo "🚀 ChiperX VPS Installer — 24/7 tanpa Termux"
echo "──────────────────────────────────────────────"

# ── Tentukan direktori repo: pakai cwd bila ini repo, kalau tidak clone ──
if [ -f docker-compose.yml ] && [ -d src ]; then
    REPO_DIR="$(pwd)"
    echo "📁 Mode in-repo: $REPO_DIR"
else
    REPO_DIR="$INSTALL_DIR_DEFAULT"
    if [ -d "$REPO_DIR/.git" ]; then
        echo "📁 Repo sudah ada di $REPO_DIR — update..."
        cd "$REPO_DIR" && git fetch origin && git reset --hard "origin/$BRANCH"
    else
        echo "📥 Clone repo (branch $BRANCH) ke $REPO_DIR ..."
        command -v git >/dev/null 2>&1 || { $SUDO apt-get update -y && $SUDO apt-get install -y git; }
        $SUDO git clone -b "$BRANCH" "$REPO_URL" "$REPO_DIR"
    fi
    cd "$REPO_DIR"
fi

# ── 1. Docker ──
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    echo "🐳 Docker: sudah terinstal ($(docker --version | cut -d' ' -f3 | tr -d ','))"
else
    echo "🐳 Docker: menginstal (get.docker.com resmi)..."
    $SUDO apt-get update -y
    curl -fsSL https://get.docker.com | $SUDO sh
    $SUDO systemctl enable --now docker
fi
docker compose version >/dev/null 2>&1 || { echo "❌ docker compose plugin belum ada — jalankan: $SUDO apt-get install -y docker-compose-plugin"; exit 1; }

# ── 2. Swap 2 GB untuk VPS kecil (RAM < 1.5 GB) ──
MEM_KB=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
if [ "$MEM_KB" -lt 1500000 ] && ! swapon --show | grep -q '/swapfile'; then
    echo "🧠 RAM ${MEM_KB} KB < 1.5 GB → membuat swap 2 GB biar build stabil..."
    $SUDO fallocate -l 2G /swapfile || $SUDO dd if=/dev/zero of=/swapfile bs=1M count=2048
    $SUDO chmod 600 /swapfile
    $SUDO mkswap /swapfile >/dev/null
    $SUDO swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' | $SUDO tee -a /etc/fstab >/dev/null
fi

# ── 3. .env produksi (tidak ditimpa bila sudah ada) ──
ROOT_PASS_FILE="/root/.chiperx_db_root"
if [ ! -f .env ]; then
    echo "🔑 Membuat .env produksi (secret acak-kuat)..."
    cp .env.example .env
    DB_PASS_GEN="$(openssl rand -hex 16)"
    ROOT_PASS_GEN="$(openssl rand -hex 16)"
    APP_KEY_GEN="$(openssl rand -hex 32)"
    sed -i "s|^DB_PASS=.*|DB_PASS=$DB_PASS_GEN|" .env
    sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY_GEN|" .env
    sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    echo "$ROOT_PASS_GEN" | ( [ -w $(dirname "$ROOT_PASS_FILE") ] 2>/dev/null && cat > "$ROOT_PASS_FILE" || $SUDO tee "$ROOT_PASS_FILE" >/dev/null )
    [ -f "$ROOT_PASS_FILE" ] && { (chmod 600 "$ROOT_PASS_FILE" 2>/dev/null || $SUDO chmod 600 "$ROOT_PASS_FILE"); }
    echo "   ✅ APP_URL = $APP_URL | APP_DEBUG=false | APP_KEY terisi"
else
    echo "🔑 .env sudah ada — dipakai ulang (tidak ditimpa) ✅"
    DB_PASS_GEN="$(grep -oP '^DB_PASS=\K.*' .env)"
    ROOT_PASS_GEN="$( [ -f "$ROOT_PASS_FILE" ] && cat "$ROOT_PASS_FILE" || echo "root_secret" )"
fi

# ── 4. Samakan password MySQL di docker-compose + kunci port ke localhost ──
echo "🛡️  Menyelaraskan password compose + kunci port (127.0.0.1)..."
sed -i "s|MYSQL_ROOT_PASSWORD: .*|MYSQL_ROOT_PASSWORD: $ROOT_PASS_GEN|" docker-compose.yml
sed -i "s|MYSQL_PASSWORD: .*|MYSQL_PASSWORD: $DB_PASS_GEN|" docker-compose.yml
sed -i "s|-proot_secret|-p$ROOT_PASS_GEN|" docker-compose.yml     # healthcheck ikut
sed -i 's|- "3306:3306"|- "127.0.0.1:3306:3306"|' docker-compose.yml  # MySQL tak diobral ke publik
sed -i 's|- "8080:80"|- "127.0.0.1:8080:80"|' docker-compose.yml      # web hanya lokal (tunnel yang meneruskan)

# ── 5. Build & nyalakan (restart:unless-stopped = auto-nyala tiap reboot) ──
echo "🏗️  Build & start container (pertama kali bisa 3–6 menit)..."
$SUDO docker compose up -d --build

# ── 6. Cron housekeeping (system cron.php tiap 5 menit) ──
CRON_LINE="*/5 * * * * root docker exec chiperx_app php /var/www/html/bin/cron.php >> /var/log/chiperx-cron.log 2>&1"
if [ -d /etc/cron.d ]; then
    echo "$CRON_LINE" | $SUDO tee /etc/cron.d/chiperx >/dev/null
    echo "⏰ Cron /etc/cron.d/chiperx terpasang (tiap 5 menit)"
fi

# ── 7. Verifikasi ──
echo "⏳ Menunggu DB sehat..."
sleep 15
echo "🩺 Hasil pemeriksaan:"
$SUDO docker exec chiperx_app php bin/doctor.php || echo "⚠️  Doctor lapor masalah — baca pesan di atas."

cat <<DONE

──────────────────────────────────────────────
🎉 SELESAI! Web jalan lokal di http://127.0.0.1:8080
   (auto-nyala tiap reboot — inilah kunci 24/7)

LANGKAH TERAKHIR — pindahkan tunnel app.chiperx.cyou ke VPS:

  Di TERMUX (HP), kirim kredensial tunnel lama:
    scp ~/.cloudflared/*.json root@IP_VPS_KAMU:/root/

  Di VPS (sesuaikan nama file .json):
    bash bin/setup-tunnel.sh /root/NAMA-UUID-TUNNEL.json

Pindahkan DATA dari HP (user, koin, postingan)? Lihat:
  docs/hosting-24-7.md  → bagian "Pindah Data"
──────────────────────────────────────────────
DONE
