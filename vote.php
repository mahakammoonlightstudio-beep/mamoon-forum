<?php
/**
 * Endpoint vote (POST saja): thread & balasan.
 * - Wajib login (vote tidak berlaku untuk anonim agar anti-manipulasi).
 * - CSRF + rate limit (maks 60 vote / 10 menit per IP).
 * - Toggle bila arah sama, switch bila berbeda.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/votes.php';
require_once __DIR__ . '/lib/auth.php';

/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$user = current_user($conn);
if ($user === null) {
    redirect('login.php?' . flash('error', 'Masuk dulu untuk memberi vote.'));
}

try {
    csrf_check();
} catch (RuntimeException $e) {
    redirect('index.php?' . flash('error', $e->getMessage()));
}

if (!rate_limit($conn, 'vote', 60, 10)) {
    $back = back_url('index.php');
    redirect($back . (strpos($back, '?') !== false ? '&' : '?') . flash('error', 'Terlalu banyak vote. Coba lagi nanti.'));
}

$type = (string)($_POST['type'] ?? '');
$id   = (int)($_POST['id'] ?? 0);
$dir  = (string)($_POST['dir'] ?? '');

if (!vote_type_valid($type) || $id <= 0 || !in_array($dir, ['up', 'down'], true)) {
    redirect('index.php?' . flash('error', 'Vote tidak valid.'));
}

// Pastikan target benar-benar ada.
if ($type === 'thread') {
    $stmt = $conn->prepare('SELECT id FROM threads WHERE id = ? LIMIT 1');
} else {
    $stmt = $conn->prepare('SELECT id FROM posts WHERE id = ? LIMIT 1');
}
bind_and_execute($stmt, 'i', [$id]);
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($exists === null) {
    redirect('index.php?' . flash('error', 'Yang mau divote sudah tidak ada.'));
}

apply_vote($conn, (int)$user['id'], $type, $id, $dir);

redirect(back_url('index.php'));
