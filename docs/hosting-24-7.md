# 🌐 Hosting ChiperX 24/7 Tanpa Termux (Pindah ke VPS)

> **Konsepnya:** Termux = "komputer mini di HP" yang ikut mati kalau HP mati/kuota habis.
> Solusinya: sewa **VPS** (server online yang nyala 24 jam di gedung data center),
> pindahkan ChiperX ke sana. HP-mu tinggal dipakai untuk **mengontrol** via SSH —
> boleh mati, web tetap hidup. 🔥

Repo ini sudah menyiapkan **stack produksi penuh** (Nginx + PHP-FPM 8.2 + MySQL 8)
via `docker-compose.yml` dengan `restart: unless-stopped` — artinya **container
otomatis nyala lagi** setiap VPS restart/crash. Itulah mesin 24/7-nya.

---

## 🆓 / 💰 Pilih Server (Opsi)

| Opsi | Harga | Spek | Catatan |
|---|---|---|---|
| **Oracle Cloud "Always Free"** | **GRATIS SELAMANYA** | ARM 4 CPU, **24 GB RAM** (!) | Masih tersedia di 2026, tapi butuh kartu kredit untuk verifikasi & kuota ARM kadang penuh (coba tengah malam) |
| **IDCloudHost Cloud VPS** | mulai ± **Rp 40–50 rb/bln** | 1 CPU, 1 GB RAM | Server Jakarta/Singapura, support Indonesia 24/7 |
| **DigitalOcean Droplet** | ± **$4–6/bln** (± Rp 65–95 rb) | 1 CPU, 512 MB–1 GB | Stabil, dokumentasi terbaik |
| **Contabo / Hetzner** | ± **€3–5/bln** | 2 CPU, 4 GB RAM | Spek besar-murah, lokasi Eropa (agak jauh pingnya) |

> ⚠️ **Spek minimal: 1 GB RAM + Ubuntu 22.04/24.04.**
> Installer otomatis menambah swap 2 GB bila RAM pas-pasan.
> 💡 **Shared hosting (cPanel) TIDAK BISA** dipakai — butuh akses root/Docker.

---

## 🚀 Langkah 1 — Buat VPS

1. Daftar di salah satu provider di atas, pilih **Ubuntu 22.04 LTS** (atau 24.04).
2. Catat: **IP server** + **password root** (contoh IP: `103.150.20.30`).

## 🚀 Langkah 2 — Masuk VPS dari HP (Termux tetap berguna sebagai remote!)

```bash
# di Termux:
pkg install openssh -y
ssh root@103.150.20.30        # ganti IP-mu, ketik password root
```

Mulai titik ini **semua perintah dijalankan di VPS** (jendela SSH).

## 🚀 Langkah 3 — Instal ChiperX (1 perintah!)

```bash
git clone -b arena/019ff184-prx https://github.com/valngawi-droid/prx.git /opt/chiperx
cd /opt/chiperx && bash bin/install-vps.sh
```

Installer otomatis: instal Docker → buat `.env` (password DB & APP_KEY **acak-kuat**,
`APP_DEBUG=false`) → build 3 container → pasang cron → cek kesehatan via `doctor.php`.
Web lalu jalan di `http://127.0.0.1:8080` dan **auto-nyala tiap reboot**. ✅

## 🚇 Langkah 4 — Pindahkan Cloudflare Tunnel (app.chiperx.cyou tetap sama!)

Kredensial tunnel-mu masih di HP. Pindahkan (di Termux):

```bash
scp ~/.cloudflared/*.json root@103.150.20.30:/root/
```

Lalu di VPS (sesuaikan nama file):

```bash
bash bin/setup-tunnel.sh /root/UUID-TUNNEL.json
```

Tunnel jadi **service systemd** → auto-nyala tiap reboot. Uji:
`curl -I https://app.chiperx.cyou` → harus jawab `200 OK`. 🎉

> Setelah itu Termux **boleh dimatikan total**. VPS yang bertugas sekarang.

## 📦 Langkah 5 (opsional) — Pindah DATA dari HP ke VPS

Mau user, koin, postingan, redeem code lama ikut? Dump DB MariaDB Termux:

```bash
# di Termux (MariaDB harus nyala):
mariadb-dump -u root chiperx > /sdcard/chiperx-data.sql
scp /sdcard/chiperx-data.sql root@103.150.20.30:/root/
```

Di VPS — impor ke MySQL container (dump MariaDB kompatibel untuk skema kita):

```bash
cd /opt/chiperx
docker cp /root/chiperx-data.sql chiperx_db:/tmp/
docker exec chiperx_db bash -c \
  'mysql -u root -p"$(cat /root/.chiperx_db_root)" chiperx < /tmp/chiperx-data.sql'
docker exec chiperx_app php bin/migrate.php   # rapikan migrasi
```

Jangan lupa **file upload** (avatar, foto feed, dll):

```bash
# di Termux:
cd ~/prx && tar czf /sdcard/uploads.tar.gz storage/uploads
scp /sdcard/uploads.tar.gz root@103.150.20.30:/root/

# di VPS:
cd /opt/chiperx
tar xzf /root/uploads.tar.gz -C /root/
docker cp /root/storage/uploads/. chiperx_app:/var/www/html/storage/uploads/
docker exec chiperx_app chown -R www-data:www-data storage/uploads
```

> ⚠️ Impor data **akan menimpa** data bawaan (seeder) — lakukan SEKALI di awal,
> sebelum user mulai ramai di server baru.

## 🔄 Update ChiperX di Masa Depan (1 perintah)

```bash
cd /opt/chiperx && bash bin/vps-update.sh
```

Menarik kode terbaru dari GitHub → rebuild → migrasi DB otomatis → cek doctor. `.env`
& data aman. HP bisa dipakai lewat SSH dari Termux kapan pun.

## 🛠️ Troubleshooting Cepat

| Gejala | Cek |
|---|---|
| Web tak bisa dibuka | `docker compose ps` (harus 3 container "Up"), `curl -I http://127.0.0.1:8080` |
| Tunnel mati | `systemctl status cloudflared`, log: `journalctl -u cloudflared -f` |
| Error DB | `docker logs chiperx_db --tail 50` |
| Error app | `docker logs chiperx_app --tail 50` |
| Cek semua | `docker exec chiperx_app php bin/doctor.php` |
| Restart total | `cd /opt/chiperx && docker compose restart` |

**Perintah survival harian:**

```bash
docker compose ps                 # status 3 container
docker stats --no-stream          # pemakaian RAM/CPU
df -h                             # sisa disk (uploads lama-lama nambah)
docker system prune -f            # bersihkan sisa build (aman)
```

## 🔒 Keamanan (sudah otomatis dari installer)

- ✅ MySQL **tidak terbuka ke internet** (hanya `127.0.0.1`)
- ✅ Web **tidak terbuka langsung** — hanya lewat Cloudflare Tunnel (DDoS protection gratis)
- ✅ Password DB & APP_KEY **acak 32 karakter**, root pass tersimpan di `/root/.chiperx_db_root` (chmod 600)
- ✅ `APP_DEBUG=false` — error teknis tidak bocor ke pengunjung
- ✅ HTTP security headers dari Nginx (lihat `docker/nginx/default.conf`)

🔥 Extra hardening opsional (firewall, hanya izinkan SSH):

```bash
ufw allow OpenSSH && ufw --force enable
```

> 💡 **Tips failover**: backup otomatis DB tiap malam —
> tambahkan ke `/etc/cron.d/chiperx`:
> `0 3 * * * root docker exec chiperx_db bash -c 'mysqldump -u root -p"$(cat /root/.chiperx_db_root)" --single-transaction chiperx | gzip > /root/backup-$(date +\%F).sql.gz'`
