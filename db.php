<?php
/**
 * Koneksi database (mysqli).
 * Kredensial dibaca dari config.php — lihat config.example.php.
 */
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Konfigurasi hilang: salin config.example.php menjadi config.php terlebih dahulu.');
}
require_once $configFile;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    exit('Koneksi database gagal. Coba beberapa saat lagi.');
}
