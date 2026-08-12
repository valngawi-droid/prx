# 🚀 ChiperX — Platform Komunitas Digital Enterprise

> **NEBULA v2.8 "SOCIAL NEBULA"** — Lapisan sosial lengkap ala 4 aplikasi besar:
> **Discord** (Kanal teks/pengumuman, slowmode, pin pesan, reaksi emoji, reply, AutoMod kata terlarang, siapa-online, edit pesan, ping @mention),
> **WhatsApp** (centang ✓✓ biru, typing indicator, foto di chat, hapus-untuk-semua, pin pesan, blokir user, last seen, format *tebal* _miring_ ~coret~),
> **Instagram** (double-tap ❤️, lightbox, postingan video, halaman Jelajahi, insight penonton Story, balas komentar, arsip postingan, daftar penyuka),
> **Telegram** (polling di kanal, pengumuman terjadwal, pencarian global, Pesan Tersimpan, pesan self-destruct, forward, ekspor chat .txt, tandai semua dibaca, reaksi pengumuman).

Website **ChiperX** full-stack: autentikasi **Email OTP** (tanpa password), **Mini Games** dengan
ChiperX Coin, **Redeem Center**, **Store + Auto-Payment**, dan **Owner God Mode**
(File Manager berbasis Monaco Editor + Discord Audit Log).

Seluruh antarmuka **100% Bahasa Indonesia**, tema **dark mode + glassmorphism**, dan
elemen **3D interaktif** (Three.js) di beranda.

---

## 🧰 Tech Stack

| Lapisan   | Teknologi |
|-----------|-----------|
| Backend   | **PHP 8.2+** — custom MVC ringan (Router/Middleware/Service/Model) |
| Frontend  | HTML5, CSS3, JavaScript ES6+, **Tailwind CSS**, **Three.js**, **GSAP** |
| Database  | **MySQL 8** / MariaDB 10.6+ (PDO prepared statements, FK penuh) |
| Auth      | Email **OTP 6 digit** via **PHPMailer + SMTP Gmail** (hash Argon2id, TTL 5 mnt, cooldown 60 dtk) |
| Security  | CSRF token, XSS escaping, SQL injection-proof PDO, anti path-traversal, session fingerprint |
| Payment   | **Tripay / Midtrans / Paydisini** (webhook terverifikasi signature) |
| Logging   | Tabel `logs` + **Discord Webhook** Rich Embed |

---

## 📁 Struktur Direktori

```
prx/
├── composer.json                # Dependensi: PHPMailer
├── .env.example                 # Template konfigurasi
├── docker-compose.yml           # MySQL lokal untuk development
├── README.md
├── database/
│   ├── schema.sql               # DDL lengkap + Foreign Keys
│   ├── seed.sql                 # Data awal (owner, settings RTP, links, produk)
│   └── migrations/              # Migrasi incremental (002_referral_daily_bonus.sql)
├── docker/                      # php/Dockerfile (PHP-FPM 8.2) + nginx/default.conf
├── tests/run.php                # Test suite mandiri (RNG, CSRF, anti-traversal)
├── public/                      # ← web root (satu-satunya folder publik)
│   ├── index.php                # Front controller (semua request masuk sini)
│   ├── .htaccess
│   └── assets/
│       ├── css/app.css
│       └── js/{three-hero.js, landing.js, games.js, file-manager.js, panel.js}
├── src/
│   ├── Core/                    # Env, Config, Database(PDO), Router, Request, Response, Session, Csrf, View, helpers
│   ├── Middleware/              # GuestMiddleware, AuthMiddleware, AdminMiddleware, OwnerMiddleware
│   ├── Models/                  # User, OtpCode, SiteLink, Product, Transaction, AppLog, Setting, GameHistory, Changelog, Announcement, Ticket, Feedback
│   ├── Services/
│   │   ├── MailService.php      # PHPMailer + template OTP estetis
│   │   ├── OtpService.php       # Generate/verifikasi OTP (cooldown, attempt limit)
│   │   ├── DiscordWebhook.php   # Rich Embed logger (reusable)
│   │   ├── AuditLogger.php      # Audit DB terpadu
│   │   ├── GameService.php      # RNG weighted + mesin mini games
│   │   ├── PlayTicketService.php# Tiket harian, reset 00:00 WIB
│   │   ├── FileManagerService.php # Tree/read/save/create/delete + backup + anti-traversal
│   │   └── Payments/            # PaymentManager + Tripay/Midtrans/Paydisini drivers
│   ├── Controllers/             # Auth, Home, Dashboard, Game, Redeem, Store, Download, Webhook, Admin, Owner
│   ├── Middleware/
│   ├── routes/web.php           # Semua definisi rute
│   └── Views/                   # layouts/, home/, info/, links/, auth/, games/, redeem/, store/, dashboard/, admin/, owner/, errors/, partials/
└── storage/                     # uploads/ (file produk), backups/ (file manager)
```

---

## ⚙️ Instalasi & Deploy

### 1) Kebutuhan
- PHP **8.2+** (ext: `pdo_mysql`, `curl`, `mbstring`, `openssl`) + Composer + MySQL 8
- **ATAU** cukup Docker & Docker Compose (tanpa install apa pun lagi)

### 2) 🚀 Quick Start TERCEPAT — coba dulu tanpa Gmail/DB (2 menit)
```bash
git clone https://github.com/valngawi-droid/prx.git && cd prx
git checkout arena/019ff184-prx
composer install
cp .env.example .env
php -S 0.0.0.0:8000 -t public public/index.php
```
Buka `http://localhost:8000` — beranda 3D langsung tampil (statistik 0 karena DB belum ada).

### 3) Quick Start PENUH (semua fitur jalan, termasuk login OTP)
```bash
# Edit .env — isi kredensial MySQL Anda, lalu:
MAIL_DRIVER=log          # ← TANPA Gmail: OTP ditulis ke storage/logs/mail.log

docker compose up -d --build        # Opsi Docker: selesai, buka :8080
# — atau manual: —
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql     # ← GANTI email owner di file ini DULU!
php -S 0.0.0.0:8000 -t public public/index.php
```
Login sebagai owner: masukkan email yang Anda tulis di `seed.sql` → baca kode OTP
di `storage/logs/mail.log` → masuk. Semua panel terbuka.

### 4) Mode PRODUKSI
Set `MAIL_DRIVER=smtp` + isi `SMTP_USERNAME`/`SMTP_PASSWORD` (Google App Password),
isi `DISCORD_WEBHOOK_URL`, dan kredensial payment gateway pilihan Anda.
Web root = `public/` (Nginx conf siap pakai di `docker/nginx/default.conf`).

### 📱 Menjalankan di Termux (Android)
```bash
pkg update && pkg install php mariadb composer -y
composer install && cp .env.example .env

# Nyalakan MariaDB (JALANKAN INI DULU — error 2002 muncul kalau server belum hidup):
mysql_install_db          # hanya pertama kali
mysqld_safe &             # biarkan berjalan di background

# Import database (root Termux default TANPA password):
mysql -u root < database/schema.sql
mysql -u root < database/seed.sql        # ganti email owner dulu!

php -S 0.0.0.0:8000 -t public public/index.php
```
Di `.env` untuk Termux: `DB_HOST=127.0.0.1`, `DB_USER=root`, `DB_PASS=` (kosong).
Tekan Enter saja saat diminta password mysql.

### 5) Webhook URL Payment Gateway (daftarkan di dashboard gateway Anda)
```
Pakasir   → https://DOMAIN-ANDA/webhook/pakasir     (paling gampang, QRIS instan)
Duitku    → https://DOMAIN-ANDA/webhook/duitku
Tripay    → https://DOMAIN-ANDA/webhook/tripay
Midtrans  → https://DOMAIN-ANDA/webhook/midtrans
Paydisini → https://DOMAIN-ANDA/webhook/paydisini
```
Driver dipilih lewat `PAYMENT_DRIVER` di `.env`. Rekomendasi mulai cepat: **Pakasir**
(daftar → dapat slug project + api key → QRIS langsung jalan) atau **Duitku**
(sandbox resmi, metode lengkap).

### 6) Menjalankan Test Suite
```bash
composer test          # atau: php tests/run.php
```
Mencakup: akurasi distribusi RNG/RTP (200.000 putaran, toleransi ±0,6%),
normalisasi bobot, batas pekan leaderboard, anti path traversal & null-byte,
validasi token CSRF & API token, regresi helper auth, escape XSS.

### 7) Migrasi Tambahan (untuk instalasi lama)
```bash
mysql -u root chiperx < database/migrations/002_referral_daily_bonus.sql
mysql -u root chiperx < database/migrations/003_leaderboard_achievements_api.sql
mysql -u root chiperx < database/migrations/004_username_password_magic.sql  # username+password & magic-link login
```

---

## 👑 Role & Panel

| Role  | Akses |
|-------|-------|
| **OWNER** | `/owner` — file manager source code (Monaco), tambah/ban/hapus admin & user, atur link, atur RTP game, audit log, tes Discord webhook |
| **ADMIN** | `/admin` — upload produk & file project, kelola user, transaksi, tiket bantuan, pengumuman, moderasi ulasan |
| **USER**  | `/dashboard` — koin, saldo, riwayat pembelian, riwayat game, unduh file, tiket support |

## 🎮 Ekonomi ChiperX Coin
- Koin **hanya** didapat dari Mini Games (3 tiket/hari, reset 00:00 WIB), **bonus login harian**,
  **bonus referral**, & bonus registrasi — nominal bisa diatur Owner di `/owner/settings`.
- Koin ditukar di **Redeem Center** → akses unduhan terbuka otomatis.
- Produk premium dibeli via **Store** (payment gateway), BUKAN dengan koin.

## 👥 Referral & Bonus Harian
- Setiap user punya **kode referral unik** — link: `APP_URL/login?ref=KODE`.
- Saat anggota baru login OTP perdana lewat link referral → pengundang otomatis
  menerima `referral_bonus` koin (tercatat di audit log + Discord).
- **Bonus login harian**: tombol klaim 1x/hari di dashboard, direset 00:00 WIB,
  kredit via UPDATE atomik (anti double-claim dari double-click).

## 🏅 Achievement & Leaderboard Mingguan
- 7 badge bawaan (tabel `achievements`): total main, total menang, jackpot, kolektor koin,
  redeem perdana, perekrut referral — masing-masing berhadiah koin, dicek otomatis setiap
  main game / redeem / referral, dengan toast perayaan di frontend.
- **Leaderboard mingguan** (Senin 00:00 WIB): top 3 peraih koin menerima bonus otomatis
  (`weekly_reward_1/2/3` — bisa diatur di settings).
- Distribusi hadiah via cron (idempoten, aman dijalankan ulang):
  ```bash
  # crontab — setiap Senin 00:05 WIB
  5 0 * * 1  php /var/www/chiperx/bin/cron.php weekly-rewards
  ```

## 📡 Public API (untuk Bot Discord)
Buat token di panel Owner → `/owner/api-tokens` (plaintext hanya tampil sekali,
tersimpan sebagai hash SHA-256 di DB). Lalu:

```bash
curl -H "Authorization: Bearer cx_TOKEN" https://domain-anda/api/v1/stats
curl -H "Authorization: Bearer cx_TOKEN" https://domain-anda/api/v1/leaderboard
curl -H "Authorization: Bearer cx_TOKEN" https://domain-anda/api/v1/user/nama@gmail.com
```

| Scope | Endpoint | Isi |
|-------|----------|-----|
| `stats` | `GET /api/v1/stats` | total user, transaksi lunas, peredaran koin |
| `leaderboard` | `GET /api/v1/leaderboard` | top 10 all-time + top 5 mingguan |
| `user` | `GET /api/v1/user/{email}` | koin, tiket, role, tanggal daftar user |

## 🔐 Catatan Keamanan
- OTP disimpan **hanya sebagai hash Argon2id** — kode mentah tidak pernah menyentuh DB.
- Semua query via **PDO prepared statement** (emulasi dimatikan).
- File Manager memvalidasi `realpath` agar tidak bisa keluar dari root proyek, mem-backup
  setiap perubahan ke `storage/backups/`, dan melaporkannya ke Discord.
- Webhook payment menolak request tanpa **signature valid**.
