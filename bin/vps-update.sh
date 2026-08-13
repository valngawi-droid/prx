#!/usr/bin/env bash
# ============================================================
# ChiperX — UPDATE 1 PERINTAH DI VPS
# Tarik kode terbaru dari GitHub → rebuild container → migrasi
# DB otomatis ikut (auto-migrator jalan saat web diakses).
#
#   bash bin/vps-update.sh
#
# Setara ngapain: git fetch → reset --hard → compose up -d --build
# .env dan data DB TIDAK tersentuh. Aman dijalankan berkali-kali.
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.." || exit 1
SUDO=""
[ "$(id -u)" -ne 0 ] && SUDO="sudo"
BRANCH="${CHIPERX_BRANCH:-arena/019ff184-prx}"

echo "🔄 Update ChiperX dari origin/$BRANCH ..."
git fetch origin
git reset --hard "origin/$BRANCH"
git log --oneline -1

# Password compose mungkin ikut kereset? Sinkronkan ulang dari .env:
DB_PASS_NOW="$(grep -oP '^DB_PASS=\K.*' .env 2>/dev/null || echo "")"
ROOT_PASS_NOW="$( [ -f /root/.chiperx_db_root ] && cat /root/.chiperx_db_root || echo root_secret )"
[ -n "$DB_PASS_NOW" ] && sed -i "s|MYSQL_PASSWORD: .*|MYSQL_PASSWORD: $DB_PASS_NOW|" docker-compose.yml
sed -i "s|MYSQL_ROOT_PASSWORD: .*|MYSQL_ROOT_PASSWORD: $ROOT_PASS_NOW|" docker-compose.yml
sed -i "s|-proot_secret|-p$ROOT_PASS_NOW|" docker-compose.yml
# Pertahankan hardening localhost bila sebelumnya terpasang
grep -q '127.0.0.1:8080:80' docker-compose.yml || sed -i 's|- "8080:80"|- "127.0.0.1:8080:80"|' docker-compose.yml
grep -q '127.0.0.1:3306:3306' docker-compose.yml || sed -i 's|- "3306:3306"|- "127.0.0.1:3306:3306"|' docker-compose.yml

echo "🏗️  Rebuild & restart container..."
$SUDO docker compose up -d --build

echo "🩺 Cek kesehatan..."
sleep 12
$SUDO docker exec chiperx_app php bin/doctor.php || true

echo ""
echo "✅ Update selesai — $(git log --oneline -1 | cut -c1-60)"
