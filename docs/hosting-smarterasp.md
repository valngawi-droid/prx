# 🪟 Hosting ChiperX di SmarterASP.net (site4now) — Shared Hosting Windows

> ⚠️ **Baca dulu jujurnya:** SmarterASP adalah *shared hosting Windows/IIS* yang
> fokus ke ASP.NET — **bukan VPS**. Tidak ada SSH, tidak ada Docker, dan
> **Cloudflare Tunnel tidak bisa dipasang** di sana. Paket gratisnya
> **trial 60 hari** saja (setelah itu ±$2,95/bln), dan domain gratis hanya
> temporer (`namakamu-001-siteX.site4now.net`).
>
> 👍 **Cocok untuk:** coba-coba/hosting sementara gratis tanpa kartu.
> 👑 **Untuk produksi selamanya** tetap saran: VPS (lihat `docs/hosting-24-7.md`).
>
> Kabar baik: PHP 8 + MySQL **didukung**, dan repo ini sekarang sudah membawa
> `web.config` IIS + cron versi web, jadi ChiperX **bisa jalan** di sini. ✅

---

## 🚀 Langkah 1 — Buat ZIP upload (di Termux HP)

```bash
cd ~/prx && bash bin/build-shared-zip.sh
```

Hasil: `~/prx-upload.zip` (kode terbaru + PHPMailer, tanpa rahasia `.env`).

## 🚀 Langkah 2 — Upload ke panel SmarterASP

1. Login [panel SmarterASP](https://member10.smarterasp.net) → menu **File Manager**.
2. Upload `prx-upload.zip` ke **root** (`/`).
3. Klik kanan zip → **Extract** di root.
4. Struktur akhir harus: `/public/`, `/src/`, `/web.config`, `/.env.example`, dst.
   (Kalau ter-extract ke subfolder `prx/`, **pindahkan semua isinya ke root**.)

## 🚀 Langkah 3 — Aktifkan PHP 8

Panel → **List Sites → Manage → PHP Version** → pilih **PHP 8.2/8.3 (64-bit)**,
pipeline **Integrated**. (Di dasbor-mu yang warna ungu ada bagian ini.)

## 🚀 Langkah 4 — Buat database MySQL

1. Panel → **Databases → MySQL** → **Add MySQL DB** (mis. nama `db_chiperx`).
2. Catat: **hostname** (contoh `mysql6001.site4now.net`), **db name**, **user**, **password**.

## 🚀 Langkah 5 — Isi `.env` (via File Manager)

1. Di File Manager: klik `.env.example` → **Save As / copy** jadi `.env`.
2. **Edit** `.env`, ubah 6 baris ini:

```ini
APP_URL=https://URL-TEMPORERMU.site4now.net
APP_DEBUG=false
APP_KEY=                  ← isi 64 huruf acak: ketik apa saja yg panjang & acak
DB_HOST=mysql6001.site4now.net   ← hostname dari Langkah 4
DB_NAME=db_chiperx
DB_USER=user_dbmu
DB_PASS=password_dbmu
MAIL_DRIVER=log           ← OTP tertulis ke storage/logs/mail.log (tes dulu)
```

> 💡 APP_KEY bebas asal **panjang acak 64 karakter** (huruf+angka). Ongak-ongek keyboard juga boleh 😄
> 💾 File ini hanya kamu yang bisa edit di panel. Web.config IIS otomatis memblokir akses `.env` dari browser.

## 🚀 Langkah 6 — Import database (phpMyAdmin)

1. Panel → MySQL → klik ikon **phpMyAdmin** di database-mu → login.
2. Tab **Import** → pilih file `database/schema.sql` dari zip (atau dari HP:
   `~/prx/database/schema.sql`) → **Go**.
3. Ulangi untuk `database/seed.sql`.
4. Selesai — tabel inti terisi. Migrasi tambahan (s.d. v2.8) **jalan otomatis**
   saat web pertama dibuka (auto-migrator). ✨

> 📦 **Mau pindah DATA dari Termux** (user/koin/postingan lama)?
> Di Termux: `mariadb-dump -u root chiperx > /sdcard/data.sql`
> lalu import file itu juga via phpMyAdmin (setelah schema+seed).
> File upload lama: zip folder `~/prx/storage/uploads` → upload → extract
> ke `/storage/uploads/` via File Manager.

## 🚀 Langkah 7 — Izinkan folder `storage/` ditulis

Panel → File Manager → klik kanan folder **`storage`** → **Permissions/Security**
→ centang **Write/Modify** untuk user application pool (IIS_IUSRS/akun situs).
Tanpa ini: log, sesi, upload foto akan gagal.

## 🚀 Langkah 8 — Buka web! 🎉

`https://URL-TEMPORERMU.site4now.net` → harus tampil beranda ChiperX.
Login owner lewat `/zszdgj/login` seperti biasa (data akun ada di seed/backup-mu).

Cek cepat kalau blank: buka `https://URLMU/cron-web.php?key=SALAH` → kalau
muncul "403 Forbidden", berarti PHP sudah jalan ✅ (masalahnya di `.env`/DB).

## ⏰ Langkah 9 — Cron (pengganti crontab, GRATIS)

Shared hosting tidak punya crontab → pakai layanan ping gratis:

1. Daftar [cron-job.org](https://cron-job.org) (gratis).
2. Create cronjob → URL:
   `https://URL-TEMPORERMU.site4now.net/cron-web.php?key=APP_KEY_KAMU`
3. Jadwal: **tiap 30 menit**.
4. Endpoint ini aman (butuh APP_KEY) dan hanya menjalankan tugas saat waktunya
   (bersih OTP harian, hadiah leaderboard tiap Senin). 

---

## 🧭 Perbandingan rute hosting (biar jelas pilih mana)

| | **SmarterASP (ini)** | **VPS (Ubuntu + Docker)** |
|---|---|---|
| Harga | Gratis 60 hari → $2,95/bln | Gratis (Oracle) / ±Rp 40rb/bln |
| Kartu kredit | Tidak perlu ✅ | Oracle perlu; VPS lokal sering terima transfer |
| Domain sendiri `app.chiperx.cyou` | ❌ hanya di paket berbayar | ✅ gratis via Cloudflare Tunnel |
| Cloudflare Tunnel / SSH / Docker | ❌ | ✅ |
| RAM untuk web | 800 MB (berbagi rame-rame) | 1–24 GB khusus kamu |
| Cron CLI | ❌ (pakai cron-web.php) | ✅ |
| Kapan pakai | coba-coba sementara | produksi 24/7 selamanya |

## 🛠️ Troubleshooting

| Gejala | Solusi |
|---|---|
| Error 500 sejak awal | Hapus blok `<security>` di `web.config` (section dikunci hosting), lalu coba lagi. Cek juga PHP version = 8.2+. |
| 404 semua halaman kecuali home | URL Rewrite module belum aktif — tiket support SmarterASP minta aktifkan, atau tanyakan "URL Rewrite for IIS". |
| Blank putih | Cek `storage/logs/php.log` via File Manager. |
| Gagal konek DB | DB_HOST harus hostname panjang (bukan `127.0.0.1`). |
| Upload foto gagal | Folder `storage` belum writable (Langkah 7). |
| OTP tidak terkirim | `MAIL_DRIVER=log` → baca OTP di `storage/logs/mail.log`; siap pakai? isi SMTP Brevo. |
| Cron tidak jalan | Tes manual URL cron-web di browser (harus ada output tugas). |

## 💾 WAJIB: backup sebelum trial 60 hari habis!

Sebelum trial berakhir: phpMyAdmin → **Export** database (`.sql`) + zip
`storage/uploads` → simpan di HP. Dengan dua file itu, ChiperX bisa
dibangkitkan lagi di mana pun (VPS, Termux, hosting lain). 🛡️
