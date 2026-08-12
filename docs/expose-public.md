# 🌐 Publikasikan ChiperX dengan Subdomain Sendiri (Cloudflare Tunnel)

Target: `http://localhost:8009` di Termux → **`https://app.chiperx.cyou`** bisa diakses siapa pun.

Kenapa Cloudflare Tunnel? HP seluler berada di belakang **CGNAT** — port forwarding/IP publik mustahil.
Tunnel bekerja *outbound* (HP yang menelpon Cloudflare), jadi tembus di jaringan apa pun,
plus HTTPS gratis dan DNS subdomain dibuat otomatis. Tanpa VPS, tanpa biaya.

---

## 1️⃣ Install cloudflared (Termux)

```bash
pkg install cloudflared -y
```

## 2️⃣ Buat tunnel di dashboard Cloudflare (via Chrome HP)

1. Buka <https://dash.cloudflare.com> → login → pojok kiri pilih **Zero Trust**
2. **Networks → Tunnels → Create a tunnel** → pilih **Cloudflared**
3. Nama bebas, mis. `chiperx-termux` → **Save**
4. Salin **TOKEN** panjang (diawali `eyJ...`) — ada di perintah `--token eyJ...`
   (cukup salin bagian tokennya saja)
5. Lanjut ke tab **Public Hostname** → **Add a public hostname**:
   - **Subdomain**: `app` *(atau `panel`/`www` — bebas)*
   - **Domain**: `chiperx.cyou`
   - **Service**: Type `HTTP` → URL `localhost:8009`
   - **Save** → Cloudflare otomatis membuat CNAME `app.chiperx.cyou` ✅

## 3️⃣ Jalankan semuanya dari Termux (satu perintah)

```bash
export TUNNEL_TOKEN='eyJ...tempel-token-di-sini...'
bash ~/prx/bin/start-public.sh
```

Skrip ini otomatis: menyalakan MariaDB bila mati, menyalakan server PHP (8 worker),
lalu menjalankan tunnel di foreground. Biarkan jendela Termux ini tetap terbuka.
Tips: jalankan `termux-wake-lock` dulu agar Android tidak membunuh prosesnya.

> Agar `TUNNEL_TOKEN` tidak hilang setiap buka Termux: `echo "export TUNNEL_TOKEN='eyJ...'" >> ~/.bashrc`

## 4️⃣ WAJIB disetel setelah publik

| Pengaturan | Nilai | Cara |
|---|---|---|
| `APP_URL` | `https://app.chiperx.cyou` | Web: Owner → 🧩 Integrasi → APP_URL → Simpan |
| `APP_DEBUG` | `false` | Edit `.env` : `sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env` |

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
| Tunnel terputus saat HP tidur | `termux-wake-lock` + jangan bersihkan Termux dari recent apps |
| ERR 1033 / tunnel tidak jalan | Cek token benar & tunnel status hijau di dashboard Zero Trust |
| Login loop / 419 di domain publik | Pastikan akses via `https://` (cookie secure) & APP_URL sudah https |
| Lambat banyak pengunjung | Sudah tertangani: skrip memakai `PHP_CLI_SERVER_WORKERS=8` |
