# Mamoon Forum 🌴

> Forum diskusi anonymous yang **ringan, cepat, dan tanpa iklan** — dibuat dengan PHP murni + MySQL, tanpa framework, tanpa build step.

![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.4-777bb3) ![MySQL](https://img.shields.io/badge/DB-MySQL%20%2F%20MariaDB-4479a1) ![License](https://img.shields.io/badge/License-MIT-green) ![Size](https://img.shields.io/badge/halaman-~25KB-success)

Mamoon Forum adalah forum gaya imageboard/Reddit yang dioptimalkan untuk **kecepatan**: setiap halaman berukuran puluhan KB, tanpa CDN eksternal, tanpa JavaScript framework. Cocok dihosting di shared hosting gratisan seperti InfinityFree, 000webhost, atau VPS kecil.

---

## ✨ Fitur

| Fitur | Keterangan |
|---|---|
| 🔒 **Hybrid auth** | Bisa posting anonim, atau daftar/masuk untuk fitur tambahan |
| 🗂️ **Kategori** | Umum, Teknologi, Gaming, Hiburan, Bantu Saya (mudah diganti) |
| 🔍 **Pencarian** | Cari thread dari judul & isi, tersedia di navbar |
| 📌 **Sticky & Locked** | Pin thread penting, kunci thread yang sudah selesai |
| 📄 **Pagination** | 20 thread/halaman di index, 15 balasan/halaman di thread |
| 🖼️ **Kompresi gambar otomatis** | Upload di-resize maks 1280px → WebP/JPEG ~puluhan KB |
| ⬆️ **Voting thread & balasan** | Upvote/downvote, klik ulang = batal, arah beda = pindah vote (tabel `votes`) |
| 📶 **Sort Terbaru / Teratas** | Tab urutan thread: terbaru, atau teratas berdasar skor vote |
| 🏅 **Reputasi otomatis** | Skor vote (up − down) dari semua thread & balasan milik user, tampil di header |
| 🛡️ **Keamanan** | Prepared statements, CSRF token, honeypot anti-bot, rate limit, validasi MIME gambar, eksekusi PHP dimatikan di `uploads/` |
| 🌙 **Tema gelap/terang** | Ikut preferensi sistem, tersimpan di localStorage |
| 🔔 **Toast notification** | Notifikasi sukses/error, auto-hide |
| ♻️ **Moderasi** | Moderator/admin bisa pin, kunci, hapus thread & balasan |

## ⚡ Kenapa ringan?

- **0 dependensi eksternal** — ikon pakai SVG inline, font pakai system font. Tidak ada Font Awesome / Google Fonts / jQuery.
- **1 CSS (~6 KB) + 1 JS (~1 KB)** untuk seluruh situs, dimuat dengan `defer`.
- **Gambar dikompres server-side** via GD saat upload — thread tetap kecil walau user upload foto besar.
- **Cache yang benar**: aset statis di-cache 1 bulan, halaman PHP `no-store` — tidak ada lagi halaman "stuck" yang tidak mau update.
- **gzip** aktif via `.htaccess`.

## 🚀 Instalasi

### 1. Clone / upload

```bash
git clone https://github.com/NAMA_KAMU/mamoon-forum.git
```

Lalu upload semua file ke folder `htdocs/` hosting kamu (via FTP / File Manager / `git push` jika hosting mendukung).

### 2. Buat database

**Instalasi baru** → import `database/schema.sql` lewat phpMyAdmin.
**Upgrade dari versi lama** → import `database/upgrade.sql`, lalu `database/upgrade-votes.sql` (aman untuk data yang sudah ada).

### 3. Konfigurasi

```bash
cp config.example.php config.php
```

Lalu isi `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` sesuai kredensial hosting kamu. `config.php` sudah ada di `.gitignore` — jangan pernah di-commit.

### 4. Selesai!

Buka domain kamu — forum siap dipakai. 🎉

### Menjalankan secara lokal (XAMPP/Laragon)

```bash
php -S localhost:8000
```

(letakkan proyek di folder `htdocs`, lalu akses `http://localhost:8000`)

## 📁 Struktur proyek

```
mamoon-forum/
├── index.php          # Daftar thread + form thread baru
├── thread.php         # Detail thread + balasan
├── post_thread.php    # Handler POST thread
├── post_reply.php     # Handler POST balasan
├── vote.php           # Endpoint vote thread/balasan (POST)
├── mod_thread.php     # Aksi moderator (pin/lock/delete)
├── login.php          # Login (opsional)
├── register.php       # Daftar akun (opsional)
├── logout.php         # Logout
├── db.php             # Koneksi database
├── config.example.php # Contoh konfigurasi (config.php = lokal saja)
├── .htaccess          # Gzip, cache, keamanan
├── assets/
│   ├── style.css      # Seluruh styling (tema terang/gelap)
│   └── app.js         # Tema, upload UI, toast
├── lib/
│   ├── helpers.php    # Session, CSRF, flash, rate limit, honeypot
│   ├── auth.php       # Login/register/logout
│   ├── images.php     # Kompresi gambar via GD
│   ├── votes.php      # Skor vote, toggle/switch, widget vote box
│   └── layout.php     # Header/footer + ikon SVG
├── database/
│   ├── schema.sql         # Skema untuk instalasi baru
│   ├── upgrade.sql        # Upgrade dari database lama
│   └── upgrade-votes.sql  # Tambah fitur voting (database lama)
└── uploads/           # Gambar hasil upload (auto-created)
```

## 🛠️ Teknologi

- **PHP ≥ 7.4** (diuji hingga PHP 8.x) — tanpa framework, tanpa composer
- **MySQL / MariaDB** dengan `utf8mb4` penuh
- **GD** untuk kompresi gambar (bawaan hampir semua hosting PHP)
- Vanilla CSS + JavaScript

## 🔐 Catatan keamanan

- Semua query memakai **prepared statements** (anti SQL injection).
- Semua output di-escape (`htmlspecialchars`) (anti XSS).
- Form POST dilindungi **CSRF token** per-session.
- Upload gambar divalidasi dari **isi file** (bukan ekstensi), di-resize ulang via GD (membuang payload berbahaya), dan folder `uploads/` **tidak bisa mengeksekusi PHP**.
- **Rate limit** per-IP untuk posting thread, balasan, vote, login, dan register (tabel `rate_limits`).
- **Vote** wajib login dan satu vote per user per target — dicegah duplikasi di level database (unique key).
- **Reputasi** dihitung live dari vote yang diterima konten user (tidak bisa dimanipulasi lewat kolom manual).
- **Honeypot field** menggagalan spam bot sederhana.
- Password disimpan sebagai `password_hash()` bcrypt.

> Rekomendasi: sebelum dipakai publik, jalankan di HTTPS (InfinityFree menyediakan SSL gratis).

## 🗺️ Roadmap

- [x] ~~Voting thread/balasan~~ (selesai — upvote/downvote dengan toggle)
- [ ] Balasan bertingkat (nested comments, tabel `comments`)
- [ ] Panel admin (kelola kategori & user)
- [ ] Umpan RSS per-kategori
- [ ] WebP fallback detection

## 🤝 Kontribusi

PR, issue, dan saran dipersilakan! Cukup fork → branch → commit → PR.

## 📄 Lisensi

[Distributed under the MIT License](LICENSE) — bebas dipakai, dimodifikasi, dan didistribusikan.
