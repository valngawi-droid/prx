#!/usr/bin/env bash
# ============================================================
# ChiperX — Upload/sync ke shared hosting via FTP dari TERMUX
# (untuk SmarterASP/site4now & hosting lain tanpa SSH)
#
# Pakai:
#   FTP_HOST=WIN6049.SITE4NOW.NET FTP_USER=valval-001 \
#   FTP_PASS='password-panelmu' bash bin/ftp-sync.sh          → sinkron kode
#
#   ... bash bin/ftp-sync.sh --env                            → upload .env saja
#   ... bash bin/ftp-sync.sh --file web.config                → upload 1 file
#
# Host bisa juga IP:  FTP_HOST=45.58.159.49
# Remote dir default: /site1  (ganti: FTP_DIR=public_html)
#
# Yang di-sync: public/, src/, vendor/, database/ + file root
# (web.config, composer.*, .env.example) — hanya file LEBIH BARU.
# storage/ & .env TIDAK disentuh (data server aman).
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.." || exit 1

FTP_HOST="${FTP_HOST:?set FTP_HOST dulu (contoh: WIN6049.SITE4NOW.NET)}"
FTP_USER="${FTP_USER:?set FTP_USER dulu}"
FTP_DIR="${FTP_DIR:-/site1}"

# Password: dari env FTP_PASS, atau TANYA interaktif (disembunyikan,
# tidak masuk history shell — jauh lebih aman!)
if [ -z "${FTP_PASS:-}" ]; then
    read -rsp "🔑 FTP password: " FTP_PASS
    echo ""
fi

command -v lftp >/dev/null 2>&1 || { echo "⬇️  pasang lftp..."; pkg install lftp -y; }

LFTP_OPTS="set ssl:verify-certificate no; set ftp:ssl-allow yes; set net:timeout 25; set net:max-retries 2; set ftp:passive-mode on;"

case "${1:-}" in
  --env)
    [ -f .env ] || { echo "❌ .env belum ada — copy dari .env.example & edit dulu!"; exit 1; }
    echo "🔑 Upload .env → ${FTP_DIR}/ ..."
    lftp -u "${FTP_USER},${FTP_PASS}" "$FTP_HOST" -e "${LFTP_OPTS} cd ${FTP_DIR} && put .env && bye"
    echo "✅ .env terkirim."
    ;;
  --file)
    F="${2:?contoh: bash bin/ftp-sync.sh --file web.config}"
    echo "📄 Upload $F → ${FTP_DIR}/ ..."
    lftp -u "${FTP_USER},${FTP_PASS}" "$FTP_HOST" -e "${LFTP_OPTS} cd ${FTP_DIR} && put $F && bye"
    echo "✅ $F terkirim."
    ;;
  *)
    echo "🔄 Sinkron kode (file lebih baru saja) → ftp://${FTP_HOST}${FTP_DIR}"
    lftp -u "${FTP_USER},${FTP_PASS}" "$FTP_HOST" -e "
      ${LFTP_OPTS}
      cd ${FTP_DIR} || exit 1
      mirror -R -n --no-perms public public
      mirror -R -n --no-perms src src
      mirror -R -n --no-perms vendor vendor
      mirror -R -n --no-perms database database
      put web.config
      put composer.json
      put .env.example
      bye"
    echo "✅ Sinkron selesai — storage/ & .env di server TIDAK diubah."
    ;;
esac
