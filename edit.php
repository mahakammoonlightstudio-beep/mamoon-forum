<?php
/**
 * Edit thread atau balasan oleh pembuatnya (atau moderator).
 * Dipakai: edit.php?type=thread&id=123  |  edit.php?type=post&id=45
 * GET menampilkan form, POST menyimpan. Dilindungi CSRF + rate limit.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/images.php';

/** @var mysqli $conn */

$user = current_user($conn);
if ($user === null) {
    redirect('login.php?' . flash('error', 'Masuk dulu untuk mengedit.'));
}

$type = (string)($_REQUEST['type'] ?? '');
$id   = (int)($_REQUEST['id'] ?? 0);

if (!in_array($type, ['thread', 'post'], true) || $id <= 0) {
    redirect('index.php?' . flash('error', 'Target edit tidak valid.'));
}

$isMod = is_mod($user);

// ---------- Ambil data target ----------
if ($type === 'thread') {
    $stmt = $conn->prepare('SELECT id, user_id, title, content, image_path, locked FROM threads WHERE id = ? LIMIT 1');
} else {
    $stmt = $conn->prepare('SELECT p.id, p.user_id, p.thread_id, p.content, p.image_path, t.locked
                            FROM posts p JOIN threads t ON t.id = p.thread_id
                            WHERE p.id = ? LIMIT 1');
}
bind_and_execute($stmt, 'i', [$id]);
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if ($row === null) {
    redirect('index.php?' . flash('error', 'Yang mau diedit sudah tidak ada.'));
}

// Hanya pembuat atau moderator yang boleh mengedit.
$ownsIt = $row['user_id'] !== null && (int)$row['user_id'] === (int)$user['id'];
if (!$ownsIt && !$isMod) {
    redirect('index.php?' . flash('error', 'Kamu tidak berhak mengedit ini.'));
}

if ((int)($row['locked'] ?? 0) === 1 && !$isMod) {
    redirect('index.php?' . flash('error', 'Thread ini dikunci.'));
}

$back = $type === 'thread'
    ? 'thread.php?id=' . $id
    : 'thread.php?id=' . (int)$row['thread_id'] . '#p' . $id;

// ---------- Simpan perubahan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
    } catch (RuntimeException $e) {
        redirect($back . '&' . flash('error', $e->getMessage()));
    }

    if (!rate_limit($conn, 'edit_content', 20, 10)) {
        redirect($back . '&' . flash('error', 'Terlalu sering mengedit. Tunggu beberapa menit ya.'));
    }

    $content = trim((string)($_POST['content'] ?? ''));
    if ($content === '') {
        redirect($back . '&' . flash('error', 'Isi tidak boleh kosong.'));
    }
    if (mb_strlen($content) > MAX_POST_LEN) {
        redirect($back . '&' . flash('error', 'Maks ' . MAX_POST_LEN . ' karakter.'));
    }

    // Gambar opsional: kosongkan dengan checkbox remove_image, atau ganti dengan upload baru.
    $imagePath = $row['image_path'];
    if (!empty($_POST['remove_image'])) {
        if (is_string($imagePath) && strpos($imagePath, 'uploads/') === 0
            && strpos($imagePath, '..') === false && is_file(__DIR__ . '/' . $imagePath)) {
            @unlink(__DIR__ . '/' . $imagePath);
        }
        $imagePath = null;
    }
    try {
        $newImage = process_image_upload($_FILES['image'] ?? []);
        if ($newImage !== null) {
            if (is_string($imagePath) && strpos($imagePath, 'uploads/') === 0
                && strpos($imagePath, '..') === false && is_file(__DIR__ . '/' . $imagePath)) {
                @unlink(__DIR__ . '/' . $imagePath);
            }
            $imagePath = $newImage;
        }
    } catch (RuntimeException $e) {
        redirect($back . '&' . flash('error', $e->getMessage()));
    }

    if ($type === 'thread') {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            redirect($back . '&' . flash('error', 'Judul tidak boleh kosong.'));
        }
        if (mb_strlen($title) > MAX_TITLE_LEN) {
            redirect($back . '&' . flash('error', 'Judul maks ' . MAX_TITLE_LEN . ' karakter.'));
        }
        $stmt = $conn->prepare('UPDATE threads SET title = ?, content = ?, image_path = ?, edited_at = NOW() WHERE id = ?');
        bind_and_execute($stmt, 'sssi', [$title, $content, $imagePath, $id]);
    } else {
        $stmt = $conn->prepare('UPDATE posts SET content = ?, image_path = ?, edited_at = NOW() WHERE id = ?');
        bind_and_execute($stmt, 'ssi', [$content, $imagePath, $id]);
    }
    $stmt->close();

    redirect($back . '&' . flash('success', 'Perubahan disimpan.'));
}

// ---------- Tampilkan form ----------
render_header($conn, 'Edit ' . ($type === 'thread' ? 'thread' : 'balasan'));
?>
<p class="back-link"><a href="<?= e($back) ?>"><?= icon('back') ?> Kembali</a></p>

<div class="panel panel-pad" style="max-width:720px">
  <h2 style="margin:0 0 14px"><?= icon('user') ?> Edit <?= $type === 'thread' ? 'thread' : 'balasan' ?> #<?= $id ?></h2>
  <form action="edit.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">

    <?php if ($type === 'thread'): ?>
      <div class="field">
        <label for="eTitle">Judul</label>
        <input type="text" id="eTitle" name="title" required maxlength="<?= MAX_TITLE_LEN ?>" value="<?= e((string)$row['title']) ?>">
      </div>
    <?php endif; ?>

    <div class="field">
      <label for="eContent">Isi</label>
      <textarea id="eContent" name="content" required maxlength="<?= MAX_POST_LEN ?>"><?= e((string)$row['content']) ?></textarea>
    </div>

    <?php if (!empty($row['image_path'])): ?>
      <div class="field">
        <label>Gambar saat ini</label>
        <img src="<?= e((string)$row['image_path']) ?>" alt="" style="max-width:240px;display:block;margin-bottom:8px">
        <label style="font-weight:400"><input type="checkbox" name="remove_image" value="1"> Hapus gambar ini</label>
      </div>
    <?php endif; ?>

    <div class="file-upload-wrapper">
      <label class="file-upload-btn" for="fileInput"><?= icon('image') ?> Ganti gambar</label>
      <input type="file" name="image" id="fileInput" accept="image/*">
      <span class="file-name" id="fileName">Tidak ada file</span>
      <button type="button" class="file-clear" id="fileClear">&times;</button>
    </div>

    <button type="submit" class="btn">Simpan perubahan</button>
    <p class="form-hint">Perubahan akan ditandai sebagai &ldquo;(diedit)&rdquo;.</p>
  </form>
</div>

<?php render_footer(); ?>
