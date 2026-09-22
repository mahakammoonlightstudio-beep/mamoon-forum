<?php
/**
 * Logout: hanya via POST + CSRF (dipanggil dari tombol di header).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
    } catch (RuntimeException $e) {
        redirect('index.php?' . flash('error', $e->getMessage()));
    }
    logout_user();
}

redirect('index.php?' . flash('success', 'Kamu sudah keluar.'));
