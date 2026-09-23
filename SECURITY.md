# Kebijakan Keamanan

## Versi yang Didukung

| Versi | Didukung |
| --- | --- |
| 2.0.x | Ya |
| < 2.0 | Tidak |

## Melaporkan Kerentanan

Kirim laporan kerentanan secara **pribadi** ke:

- **mahakammoonlightstudio@gmail.com**

Jangan buat issue publik untuk kerentanan keamanan. Sertakan:

- Deskripsi kerentanan dan dampaknya;
- Langkah reproduksi (URL, parameter, payload);
- Versi/commit yang terdampak;
- Saran perbaikan bila ada.

Respons diupayakan dalam **7 hari kerja**. Setelah perbaikan dirilis, pelapor dipersilakan mengungkapkan temuan secara publik dengan kredit.

## Cakupan

Berlaku untuk kode di repositori ini:

- Injeksi SQL, XSS, CSRF, open redirect;
- Bypass validasi unggahan gambar atau eksekusi kode di `uploads/`;
- Kelemahan sesi, autentikasi, rate limit, atau honeypot;
- Kebocoran data antar pengguna.

Di luar cakupan: konfigurasi server/hosting (InfinityFree), serangan brute force skala besar, dan masalah pada pustaka pihak ketiga tanpa dampak spesifik ke forum ini.

## Panduan Deploy Aman

- Jalankan forum di atas HTTPS (InfinityFree menyediakan SSL gratis).
- `config.php` tidak boleh ikut ke repositori atau direktori publik lain.
- Ganti seluruh akun demo dari `database/seed-demo.sql` sebelum produksi.
