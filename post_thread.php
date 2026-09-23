<?php
/**
 * Handler POST thread baru.
 * Validasi: CSRF, honeypot, rate limit, panjang teks, kategori, gambar.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/images.php';

/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

try {
    csrf_check();
} catch (RuntimeException $e) {
    redirect('index.php?' . flash('error', $e->getMessage()));
}

// Honeypot terisi = bot; beri respons sukses palsu.
if (!honeypot_ok()) {
    redirect('index.php?' . flash('success', 'Thread terkirim.'));
}

if (!rate_limit($conn, 'post_thread', 5, 10)) {
    redirect('index.php?' . flash('error', 'Kamu terlalu sering bikin thread. Tunggu beberapa menit ya.'));
}

$title   = trim((string)($_POST['title'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));
$catId   = (int)($_POST['category_id'] ?? 0);

if ($title === '' || $content === '') {
    redirect('index.php?' . flash('error', 'Judul dan isi thread wajib diisi.'));
}
if (mb_strlen($title) > MAX_TITLE_LEN || mb_strlen($content) > MAX_POST_LEN) {
    redirect('index.php?' . flash('error', 'Terlalu panjang! Judul maks ' . MAX_TITLE_LEN . ', isi maks ' . MAX_POST_LEN . ' karakter.'));
}

// Kategori harus benar-benar ada bila diisi; selain itu NULL (tanpa kategori).
// Catatan: NULL, bukan 0 — kolom ini punya foreign key ke categories.
if ($catId > 0) {
    $stmt = $conn->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    bind_and_execute($stmt, 'i', [$catId]);
    $ok = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    if (!$ok) {
        $catId = null;
    }
} else {
    $catId = null;
}

// Gambar (otomatis dikompres).
$imagePath = null;
try {
    $imagePath = process_image_upload($_FILES['image'] ?? []);
} catch (RuntimeException $e) {
    redirect('index.php?' . flash('error', $e->getMessage()));
}

// Pembuat thread (bila login) untuk kredit reputasi.
$author  = current_user($conn);
$userId  = $author !== null ? (int)$author['id'] : null;

$stmt = $conn->prepare('INSERT INTO threads (title, content, image_path, category_id, user_id) VALUES (?, ?, ?, ?, ?)');
bind_and_execute($stmt, 'sssii', [$title, $content, $imagePath, $catId, $userId]);
$newId = (int)$conn->insert_id;
$stmt->close();

redirect('thread.php?id=' . $newId . '&' . flash('success', 'Thread terkirim!'));
