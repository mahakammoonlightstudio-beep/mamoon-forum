<?php
/**
 * Edit profil: ganti avatar & ganti password.
 * POST saja, wajib login, dilindungi CSRF + rate limit.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/images.php';

/** @var mysqli $conn */

$user = current_user($conn);
if ($user === null) {
    redirect('login.php?' . flash('error', 'Masuk dulu untuk mengedit profil.'));
}

$back = 'profile.php?u=' . rawurlencode((string)$user['username']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($back);
}

try {
    csrf_check();
} catch (RuntimeException $e) {
    redirect($back . '&' . flash('error', $e->getMessage()));
}

if (!rate_limit($conn, 'edit_profile', 10, 10)) {
    redirect($back . '&' . flash('error', 'Terlalu sering. Tunggu beberapa menit ya.'));
}

$action = (string)($_POST['action'] ?? '');
$userId = (int)$user['id'];

if ($action === 'avatar') {
    // ----- Ganti avatar -----
    try {
        $avatarPath = process_image_upload($_FILES['avatar'] ?? [], 256);
    } catch (RuntimeException $e) {
        redirect($back . '&' . flash('error', $e->getMessage()));
    }

    if ($avatarPath === null) {
        redirect($back . '&' . flash('error', 'Pilih gambar dulu.'));
    }

    // Hapus avatar lama (bukan avatar bawaan).
    if (!empty($user['avatar_url']) && strpos((string)$user['avatar_url'], 'uploads/') === 0
        && strpos((string)$user['avatar_url'], '..') === false && is_file(__DIR__ . '/' . $user['avatar_url'])) {
        @unlink(__DIR__ . '/' . $user['avatar_url']);
    }

    $stmt = $conn->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
    bind_and_execute($stmt, 'si', [$avatarPath, $userId]);
    $stmt->close();

    redirect($back . '&' . flash('success', 'Avatar diperbarui.'));
}

if ($action === 'password') {
    // ----- Ganti password -----
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    bind_and_execute($stmt, 'i', [$userId]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null || !password_verify($current, (string)$row['password_hash'])) {
        redirect($back . '&' . flash('error', 'Password saat ini salah.'));
    }
    if (strlen($new) < 8) {
        redirect($back . '&' . flash('error', 'Password baru minimal 8 karakter.'));
    }
    if ($new !== $confirm) {
        redirect($back . '&' . flash('error', 'Konfirmasi password tidak cocok.'));
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    bind_and_execute($stmt, 'si', [$hash, $userId]);
    $stmt->close();

    redirect($back . '&' . flash('success', 'Password berhasil diganti.'));
}

redirect($back);
