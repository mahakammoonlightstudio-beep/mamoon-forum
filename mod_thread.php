<?php
/**
 * Aksi moderator: pin/kunci/hapus thread, hapus balasan.
 * Wajib POST + CSRF + peran moderator/admin.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

/** @var mysqli $conn */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

try {
    csrf_check();
} catch (RuntimeException $e) {
    redirect('index.php?' . flash('error', $e->getMessage()));
}

if (!is_mod(current_user($conn))) {
    redirect('index.php?' . flash('error', 'Kamu tidak punya akses moderasi.'));
}

$threadId = (int)($_POST['thread_id'] ?? 0);
$replyId  = (int)($_POST['reply_id'] ?? 0);
$action   = (string)($_POST['action'] ?? '');
$back     = 'thread.php?id=' . $threadId;

switch ($action) {
    case 'toggle_sticky':
        $conn->query('UPDATE threads SET sticky = 1 - sticky WHERE id = ' . $threadId);
        redirect($back . '&' . flash('success', 'Status pin diubah.'));
        // no break (redirect exit)

    case 'toggle_lock':
        $conn->query('UPDATE threads SET locked = 1 - locked WHERE id = ' . $threadId);
        redirect($back . '&' . flash('success', 'Status kunci diubah.'));

    case 'delete_thread':
        $stmt = $conn->prepare('DELETE FROM threads WHERE id = ?');
        bind_and_execute($stmt, 'i', [$threadId]);
        $stmt->close();
        redirect('index.php?' . flash('success', 'Thread dihapus.'));

    case 'delete_reply':
        if ($replyId > 0) {
            $stmt = $conn->prepare('DELETE FROM posts WHERE id = ? AND thread_id = ?');
            bind_and_execute($stmt, 'ii', [$replyId, $threadId]);
            $stmt->close();
        }
        redirect($back . '&' . flash('success', 'Balasan dihapus.'));

    default:
        redirect($back . '&' . flash('error', 'Aksi tidak dikenal.'));
}
