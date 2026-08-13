#!/usr/bin/env bash
# ============================================================
# ChiperX — Buat ZIP siap-upload ke shared hosting (SmarterASP & kawan-kawan)
# Jalankan di TERMUX:
#   cd ~/prx && bash bin/build-shared-zip.sh
# Hasil: ~/prx-upload.zip  → upload & extract via File Manager panel hosting.
#
# Isi zip = kode terbaru GitHub + vendor (PHPMailer) versi --no-dev,
# TANPA .env (rahasia), .git, dan isi storage (log/upload lama).
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.." || exit 1
BRANCH="${CHIPERX_BRANCH:-arena/019ff184-prx}"
OUT="${1:-$HOME/prx-upload.zip}"

echo "🔄 Tarik kode terbaru origin/$BRANCH ..."
git fetch origin
git reset --hard "origin/$BRANCH"

echo "📦 Composer install --no-dev (PHPMailer dkk.) ..."
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

command -v zip >/dev/null 2>&1 || { echo "⬇️  pasang zip dulu: pkg install zip -y"; pkg install zip -y; }

echo "🗜️  Membuat $OUT ..."
rm -f "$OUT"
zip -r "$OUT" . \
    -x '.git/*' '.env' '.env.local' \
       'storage/logs/*' 'storage/cache/*' 'storage/backups/*' 'storage/uploads/*' \
       'tests/*' 'docs/*' '*.md' 'bin/*' \
    >/dev/null

echo ""
du -h "$OUT"
echo "✅ SELESAI! Upload $OUT lewat File Manager panel → Extract."
echo "   Lanjutkan panduan: docs/hosting-smarterasp.md"
