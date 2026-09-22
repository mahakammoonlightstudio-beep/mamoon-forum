<?php
/**
 * Handler POST balasan.
 * Validasi: CSRF, honeypot, rate limit, thread ada & tidak terkunci.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/images.php';

/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$threadId = (int)($_POST['thread_id'] ?? 0);
$back     = 'thread.php?id=' . $threadId;

try {
    csrf_check();
} catch (RuntimeException $e) {
    redirect($back . '&' . flash('error', $e->getMessage()));
}

if (!honeypot_ok()) {
    redirect($back . '&' . flash('success', 'Balasan terkirim.'));
}

if (!rate_limit($conn, 'post_reply', 10, 10)) {
    redirect($back . '&' . flash('error', 'Kamu terlalu sering membalas. Tunggu beberapa menit ya.'));
}

// Thread harus ada.
$stmt = $conn->prepare('SELECT locked FROM threads WHERE id = ? LIMIT 1');
bind_and_execute($stmt, 'i', [$threadId]);
$thread = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if ($thread === null) {
    redirect('index.php?' . flash('error', 'Thread tidak ditemukan.'));
}
if ((int)$thread['locked'] === 1) {
    redirect($back . '&' . flash('error', 'Thread ini dikunci, tidak bisa dibalas.'));
}

$content = trim((string)($_POST['content'] ?? ''));
if ($content === '') {
    redirect($back . '&' . flash('error', 'Balasan tidak boleh kosong.'));
}
if (mb_strlen($content) > MAX_POST_LEN) {
    redirect($back . '&' . flash('error', 'Balasan maks ' . MAX_POST_LEN . ' karakter.'));
}

$imagePath = null;
try {
    $imagePath = process_image_upload($_FILES['image'] ?? []);
} catch (RuntimeException $e) {
    redirect($back . '&' . flash('error', $e->getMessage()));
}

$stmt = $conn->prepare('INSERT INTO posts (thread_id, content, image_path) VALUES (?, ?, ?)');
bind_and_execute($stmt, 'iss', [$threadId, $content, $imagePath]);
$stmt->close();

$stmt = $conn->prepare('UPDATE threads SET bump_at = NOW() WHERE id = ?');
bind_and_execute($stmt, 'i', [$threadId]);
$stmt->close();

// Arahkan ke halaman terakhir supaya balasan barunya langsung terlihat.
$total    = (int)$conn->query('SELECT COUNT(*) AS c FROM posts WHERE thread_id = ' . $threadId)->fetch_assoc()['c'];
$lastPage = max(1, (int)ceil($total / 15));

redirect('thread.php?id=' . $threadId . '&p=' . $lastPage . '&' . flash('success', 'Balasan terkirim!'));
