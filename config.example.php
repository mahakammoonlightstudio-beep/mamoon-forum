<?php
/**
 * Konfigurasi Mamoon Forum — CONTOH.
 *
 * 1. Salin file ini menjadi:  config.php
 * 2. Isi kredensial databasemu (lihat panel hosting).
 * 3. config.php ada di .gitignore, jadi tidak akan ikut ke GitHub.
 */
declare(strict_types=1);

// --- Database ---
const DB_HOST = 'localhost';            // contoh InfinityFree: sqlXXX.infinityfree.com
const DB_USER = 'username_database';
const DB_PASS = 'password_database';
const DB_NAME = 'nama_database';

// --- Konten ---
const SITE_NAME     = 'Mamoon Forum';
const MAX_TITLE_LEN = 150;   // panjang maksimum judul thread
const MAX_POST_LEN  = 2000;  // panjang maksimum isi thread/balasan

// --- Upload gambar ---
const MAX_IMAGE_BYTES = 3145728; // 3 MB sebelum dikompres
const MAX_IMAGE_DIM   = 1280;    // sisi terpanjang setelah resize (px)
const IMAGE_QUALITY   = 82;      // kualitas JPEG/WebP hasil kompresi
