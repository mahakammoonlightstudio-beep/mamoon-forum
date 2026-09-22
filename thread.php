<?php
/**
 * Halaman detail thread: OP + balasan (pagination), status locked,
 * dan kontrol moderator (pin / kunci / hapus).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

/** @var mysqli $conn */

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('index.php');
}

// ---------- Data thread ----------
$stmt = $conn->prepare(
    'SELECT t.*, c.name AS cat_name, c.color AS cat_color
     FROM threads t LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.id = ? LIMIT 1'
);
bind_and_execute($stmt, 'i', [$id]);
$thread = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if ($thread === null) {
    http_response_code(404);
    render_header($conn, 'Thread tidak ditemukan', false);
    echo '<div class="empty-state panel panel-pad" style="margin-top:24px">' . icon('warn') .
         '<p>Thread tidak ditemukan atau sudah dihapus.</p>' .
         '<a class="btn" href="index.php">Kembali ke forum</a></div>';
    render_footer();
    exit;
}

$user  = current_user($conn);
$isMod = is_mod($user);
$lock  = (int)$thread['locked'] === 1;

// ---------- Pagination balasan ----------
$perPage    = 15;
$total      = (int)$conn->query('SELECT COUNT(*) AS c FROM posts WHERE thread_id = ' . $id)->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['p'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $conn->prepare('SELECT id, content, image_path, created_at FROM posts WHERE thread_id = ? ORDER BY created_at ASC, id ASC LIMIT ? OFFSET ?');
bind_and_execute($stmt, 'iii', [$id, $perPage, $offset]);
$replies = $stmt->get_result();
$stmt->close();

render_header($conn, (string)$thread['title']);
?>

<p class="back-link"><a href="index.php"><?= icon('back') ?> Kembali ke forum</a></p>

<article class="op-post">
  <h2>
    <?php if ((int)$thread['sticky']): ?><span class="badge badge-pin"><?= icon('pin') ?>Pin</span><?php endif; ?>
    <?php if ($lock): ?><span class="badge badge-lock"><?= icon('lock') ?>Terkunci</span><?php endif; ?>
    <?php if ($thread['cat_name'] !== null): ?>
      <a class="chip" style="background:<?= e((string)$thread['cat_color']) ?>" href="index.php?cat=<?= e((string)($thread['cat_slug'] ?? '')) ?>"><?= e((string)$thread['cat_name']) ?></a>
    <?php endif; ?>
    <?= e((string)$thread['title']) ?>
  </h2>
  <p style="white-space:pre-wrap;overflow-wrap:anywhere"><?= nl2br(e((string)$thread['content'])) ?></p>
  <?php if (!empty($thread['image_path'])): ?>
    <a href="<?= e((string)$thread['image_path']) ?>" target="_blank" rel="noopener">
      <img src="<?= e((string)$thread['image_path']) ?>" alt="Gambar thread" loading="lazy" style="max-width:420px">
    </a>
  <?php endif; ?>
  <div class="thread-meta">
    <span class="item"><span class="avatar" style="width:22px;height:22px;font-size:11px">A</span> Anonim</span>
    <span class="item"><?= icon('clock') ?> <?= e(time_ago((string)$thread['created_at'])) ?></span>
    <span class="item">#<?= $id ?></span>
    <?php if ($isMod): ?>
      <span class="mod-actions">
        <form method="post" action="mod_thread.php" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="thread_id" value="<?= $id ?>">
          <input type="hidden" name="action" value="toggle_sticky">
          <button class="link-btn"><?= (int)$thread['sticky'] ? 'Lepas pin' : 'Pin' ?></button>
        </form>
        <form method="post" action="mod_thread.php" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="thread_id" value="<?= $id ?>">
          <input type="hidden" name="action" value="toggle_lock">
          <button class="link-btn"><?= $lock ? 'Buka' : 'Kunci' ?></button>
        </form>
        <form method="post" action="mod_thread.php" class="inline-form" onsubmit="return confirm('Hapus thread ini beserta semua balasannya?')"><?= csrf_field() ?>
          <input type="hidden" name="thread_id" value="<?= $id ?>">
          <input type="hidden" name="action" value="delete_thread">
          <button class="link-btn">Hapus</button>
        </form>
      </span>
    <?php endif; ?>
  </div>
</article>

<h3 style="margin:18px 0 10px"><?= $total ?> balasan</h3>

<?php while ($post = $replies->fetch_assoc()): ?>
  <article class="reply" id="p<?= (int)$post['id'] ?>">
    <div class="reply-head">
      <span class="avatar">A</span>
      <span class="reply-author">Anonim</span>
      <span class="date">#<?= (int)$post['id'] ?> &middot; <?= e(time_ago((string)$post['created_at'])) ?></span>
      <?php if ($isMod): ?>
        <span class="mod-actions">
          <form method="post" action="mod_thread.php" class="inline-form" onsubmit="return confirm('Hapus balasan ini?')"><?= csrf_field() ?>
            <input type="hidden" name="thread_id" value="<?= $id ?>">
            <input type="hidden" name="reply_id" value="<?= (int)$post['id'] ?>">
            <input type="hidden" name="action" value="delete_reply">
            <button class="link-btn">Hapus</button>
          </form>
        </span>
      <?php endif; ?>
    </div>
    <p><?= nl2br(e((string)$post['content'])) ?></p>
    <?php if (!empty($post['image_path'])): ?>
      <a href="<?= e((string)$post['image_path']) ?>" target="_blank" rel="noopener">
        <img src="<?= e((string)$post['image_path']) ?>" alt="Gambar balasan" loading="lazy" style="max-width:320px">
      </a>
    <?php endif; ?>
  </article>
<?php endwhile; ?>

<?php if ($totalPages > 1): ?>
  <nav class="pagination" aria-label="Navigasi balasan">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="thread.php?id=<?= $id ?>&p=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>

<?php if ($lock): ?>
  <div class="notice"><?= icon('lock') ?> Thread ini dikunci &mdash; balasan baru tidak diterima.</div>
<?php else: ?>
  <form action="post_reply.php" method="post" enctype="multipart/form-data" class="panel panel-pad" style="margin-top:16px">
    <?= csrf_field() . honeypot_field() ?>
    <input type="hidden" name="thread_id" value="<?= $id ?>">
    <label for="rContent">Tulis balasan</label>
    <textarea id="rContent" name="content" required maxlength="<?= MAX_POST_LEN ?>" placeholder="Tulis balasan&hellip;"></textarea>
    <div class="file-upload-wrapper">
      <label class="file-upload-btn" for="fileInput"><?= icon('image') ?> Tambah gambar</label>
      <input type="file" name="image" id="fileInput" accept="image/*">
      <span class="file-name" id="fileName">Tidak ada file</span>
      <button type="button" class="file-clear" id="fileClear">&times;</button>
    </div>
    <button type="submit" class="btn">Kirim balasan</button>
  </form>
<?php endif; ?>

<?php render_footer(); ?>
