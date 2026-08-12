# 🌐 Publikasikan ChiperX dengan Subdomain Sendiri (Cloudflare Tunnel)

Target: `http://localhost:8009` di Termux → **`https://app.chiperx.cyou`** bisa diakses siapa pun.

- **100% GRATIS** — jalur CLI murni di bawah TIDAK butuh kartu/PayPal dan TIDAK perlu
  mendaftar Zero Trust. (Layar "tambah metode pembayaran" di dashboard Zero Trust itu
  cuma onboarding upsell — jalur CLI melewatinya total.)
- HP seluler berada di belakang **CGNAT** — port forwarding/IP publik mustahil.
  Tunnel bekerja *outbound* (HP yang menelpon Cloudflare), jadi tembus di jaringan apa pun.
- CNAME subdomain & HTTPS otomatis. Tanpa VPS, tanpa biaya.

---

## 1️⃣ Install cloudflared (Termux)

```bash
pkg install cloudflared -y
```

## 2️⃣ Login (satu-satunya ketukan browser)

```bash
cloudflared tunnel login
```
Muncul URL panjang → buka di Chrome → login Cloudflare → pilih **chiperx.cyou** → **Authorize**.
Sertifikat tersimpan di `~/.cloudflared/cert.pem`. Selesai — sisanya full terminal.

## 3️⃣ Buat tunnel + pasang subdomain (CMD)

```bash
cloudflared tunnel create chiperx
cloudflared tunnel route dns chiperx app.chiperx.cyou
```
Langkah kedua otomatis membuat CNAME `app.chiperx.cyou` di DNS Cloudflare-mu ✅
*(subdomain bebas: `app`, `panel`, `www`, dll.)*

## 4️⃣ Config file

`nano ~/.cloudflared/config.yml` — isi (ganti UUID sesuai output langkah 3):

```yaml
tunnel: UUID-TUNNELMU
credentials-file: /data/data/com.termux/files/home/.cloudflared/UUID-TUNNELMU.json
ingress:
  - hostname: app.chiperx.cyou
    service: http://localhost:8009
  - service: http_status:404
```

## 5️⃣ Jalankan (satu perintah, setiap kali)

```bash
bash ~/prx/bin/start-public.sh
```
Otomatis: wake-lock → MariaDB (dinyalakan bila mati) → PHP server 8 worker → tunnel connect.
Biarkan jendela Termux terbuka; jangan swipe-close aplikasinya.

> Cuma mau demokan cepat tanpa akun? `bash bin/start-public.sh --quick`
> (URL acak `*.trycloudflare.com`, BUKAN domain sendiri — untuk iseng saja.)

## 6️⃣ WAJIB disetel setelah publik

| Pengaturan | Nilai | Cara |
|---|---|---|
| `APP_URL` | `https://app.chiperx.cyou` | Web: Owner → 🧩 Integrasi → APP_URL → Simpan |
| `APP_DEBUG` | `false` | `sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env` |

- **APP_URL** dipakai untuk magic-link di email — kalau masih `localhost`, orang lain tak bisa klik.
- **APP_DEBUG=false** penting! Mode debug menampilkan info internal (path server, dsb.) — jangan tampilkan ke publik.

## ✅ Verifikasi

- Buka `https://app.chiperx.cyou` dari HP lain/kuota teman → landing page muncul.
- Daftar akun → email magic-link sekarang berisi tautan `https://app.chiperx.cyou/...`.
- Gerbang admin tetap rahasia: `https://app.chiperx.cyou/zszdgj/login`.

## 🧯 Troubleshooting

| Gejala | Obat |
|---|---|
| `cloudflared: command not found` | `pkg install cloudflared -y` |
| `cloudflared update` otomatis error di Termux | Sudah ditangani — skrip mengekspor `NO_AUTOUPDATE=true` |
| Tunnel terputus saat HP tidur | `termux-wake-lock` + jangan bersihkan Termux dari recent apps |
| `route dns` gagal | Berarti `cert.pem` belum ada → ulangi langkah 2 (`tunnel login`) |
| Subdomain lain | Ulangi langkah 3–4 dengan hostname berbeda (boleh banyak ingress) |
| Login loop / 419 di domain publik | Pastikan akses via `https://` & APP_URL sudah https |

