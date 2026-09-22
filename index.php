<?php
/**
 * Halaman utama: daftar thread + form thread baru.
 * Fitur: kategori, pencarian, sticky, pagination, reply count.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

/** @var mysqli $conn */

// ---------- Filter kategori ----------
$catSlug = trim((string)($_GET['cat'] ?? ''));
$cat     = null;
if ($catSlug !== '') {
    $stmt = $conn->prepare('SELECT id, name, slug, description, color FROM categories WHERE slug = ? LIMIT 1');
    bind_and_execute($stmt, 's', [$catSlug]);
    $cat = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if ($cat === null) {
        redirect('index.php?' . flash('error', 'Kategori tidak ditemukan.'));
    }
}

// ---------- Pencarian ----------
$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 100);

// ---------- Urutan: terbaru / teratas (skor vote) ----------
$sort = ($_GET['sort'] ?? 'new') === 'top' ? 'top' : 'new';

// ---------- Pagination ----------
$perPage = 20;
$page    = max(1, (int)($_GET['p'] ?? 1));

$where = '1=1';
$types = '';
$args  = [];
if ($cat !== null) {
    $where .= ' AND t.category_id = ?';
    $types .= 'i';
    $args[] = (int)$cat['id'];
}
if ($q !== '') {
    $where .= ' AND (t.title LIKE ? OR t.content LIKE ?)';
    $types .= 'ss';
    $args[] = '%' . $q . '%';
    $args[] = '%' . $q . '%';
}

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM threads t WHERE $where");
bind_and_execute($stmt, $types, $args);
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// ---------- Daftar thread ----------
$voteScoreSql = "(SELECT COALESCE(SUM(CASE v.vote_type WHEN 'up' THEN 1 ELSE -1 END), 0)
                 FROM votes v WHERE v.target_type = 'thread' AND v.target_id = t.id) AS vote_score";
$order = $sort === 'top'
    ? 't.sticky DESC, vote_score DESC, t.bump_at DESC'
    : 't.sticky DESC, t.bump_at DESC';

$sql = "SELECT t.id, t.title, t.content, t.created_at, t.bump_at, t.sticky, t.locked,
        c.name AS cat_name, c.color AS cat_color, c.slug AS cat_slug,
        (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id) AS reply_count,
        $voteScoreSql
        FROM threads t
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE $where
        ORDER BY $order
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$types .= 'ii';
$args[] = $perPage;
$args[] = $offset;
bind_and_execute($stmt, $types, $args);
$threads = $stmt->get_result();
$stmt->close();

$rows = [];
while ($r = $threads->fetch_assoc()) {
    $rows[] = $r;
}

// ---------- Kategori untuk sidebar ----------
$cats = $conn->query(
    'SELECT c.id, c.name, c.slug, c.color,
        (SELECT COUNT(*) FROM threads t WHERE t.category_id = c.id) AS thread_count
     FROM categories c ORDER BY c.name'
);

/** URL pagination yang mempertahankan filter aktif. */
function page_url(int $p, string $q, ?array $cat, string $sort = 'new'): string
{
    $parts = [];
    if ($q !== '')          $parts[] = 'q=' . rawurlencode($q);
    if ($cat !== null)      $parts[] = 'cat=' . rawurlencode((string)$cat['slug']);
    if ($sort === 'top')    $parts[] = 'sort=top';
    if ($p > 1)             $parts[] = 'p=' . $p;
    return 'index.php' . ($parts ? '?' . implode('&', $parts) : '');
}

/** URL ganti urutan yang mempertahankan filter aktif. */
function sort_url(string $s, string $q, ?array $cat): string
{
    $parts = [];
    if ($q !== '')          $parts[] = 'q=' . rawurlencode($q);
    if ($cat !== null)      $parts[] = 'cat=' . rawurlencode((string)$cat['slug']);
    if ($s === 'top')       $parts[] = 'sort=top';
    return 'index.php' . ($parts ? '?' . implode('&', $parts) : '');
}

render_header($conn, $cat !== null ? (string)$cat['name'] : 'Diskusi');
?>

<div class="page-grid">
  <section>
    <?php if ($q !== '' || $cat !== null): ?>
      <p class="thread-meta" style="margin-bottom:10px">
        <?php if ($cat !== null): ?>
          <span class="chip" style="background:<?= e((string)$cat['color']) ?>"><?= e((string)$cat['name']) ?></span>
          <span><?= e((string)$cat['description']) ?></span>
        <?php endif; ?>
        <?php if ($q !== ''): ?><span><?= icon('search') ?> Hasil untuk &ldquo;<?= e($q) ?>&rdquo;</span><?php endif; ?>
        &middot; <a href="index.php">reset</a>
      </p>
    <?php endif; ?>

    <!-- Form thread baru: <details> biar tanpa JS -->
    <details class="panel panel-pad" style="margin-bottom:16px">
      <summary style="cursor:pointer;font-weight:700;display:flex;align-items:center;gap:8px">
        <?= icon('plus') ?> Buat thread baru
      </summary>
      <form action="post_thread.php" method="post" enctype="multipart/form-data" style="margin-top:14px">
        <?= csrf_field() . honeypot_field() ?>
        <div class="field">
          <label for="tTitle">Judul</label>
          <input type="text" id="tTitle" name="title" required maxlength="<?= MAX_TITLE_LEN ?>" placeholder="Judul thread&hellip;">
        </div>
        <div class="field">
          <label for="tCat">Kategori</label>
          <select id="tCat" name="category_id">
            <option value="0">Tanpa kategori</option>
            <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="field">
          <label for="tContent">Isi</label>
          <textarea id="tContent" name="content" required maxlength="<?= MAX_POST_LEN ?>" placeholder="Tulis isi thread&hellip;"></textarea>
        </div>
        <div class="file-upload-wrapper">
          <label class="file-upload-btn" for="fileInput"><?= icon('image') ?> Tambah gambar</label>
          <input type="file" name="image" id="fileInput" accept="image/*">
          <span class="file-name" id="fileName">Tidak ada file</span>
          <button type="button" class="file-clear" id="fileClear">&times;</button>
        </div>
        <button type="submit" class="btn">Kirim thread</button>
        <p class="form-hint">Gambar otomatis dikompres (maks 3 MB, jadi &le;1280px). Maks <?= MAX_POST_LEN ?> karakter.</p>
      </form>
    </details>

    <div class="sort-tabs">
      <a href="<?= e(sort_url('new', $q, $cat)) ?>" class="<?= $sort === 'new' ? 'active' : '' ?>"><?= icon('clock') ?> Terbaru</a>
      <a href="<?= e(sort_url('top', $q, $cat)) ?>" class="<?= $sort === 'top' ? 'active' : '' ?>"><?= icon('arrow-up') ?> Teratas</a>
    </div>

    <div class="panel">
      <?php if ($total === 0): ?>
        <div class="empty-state">
          <?= icon('chat') ?>
          <p>Belum ada thread di sini.<br>Jadilah yang pertama bikin thread!</p>
        </div>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php $tid = (int)$row['id']; $score = (int)$row['vote_score']; ?>
          <a class="thread-card" href="thread.php?id=<?= $tid ?>">
            <h2>
              <?php if ((int)$row['sticky']): ?><span class="badge badge-pin"><?= icon('pin') ?>Pin</span><?php endif; ?>
              <?php if ((int)$row['locked']): ?><span class="badge badge-lock"><?= icon('lock') ?>Terkunci</span><?php endif; ?>
              <?php if ($row['cat_name'] !== null): ?>
                <span class="chip" style="background:<?= e((string)$row['cat_color']) ?>"><?= e((string)$row['cat_name']) ?></span>
              <?php endif; ?>
              <?= e((string)$row['title']) ?>
            </h2>
            <p class="snippet"><?= e(mb_substr(preg_replace('/\s+/u', ' ', (string)$row['content']) ?? '', 0, 140)) ?></p>
            <div class="thread-meta">
              <span class="item"><?= icon('chat') ?> <?= (int)$row['reply_count'] ?> balasan</span>
              <span class="item"><?= icon('clock') ?> <?= e(time_ago((string)$row['bump_at'])) ?></span>
              <span class="item"><?= icon('arrow-up') ?> <?= $score ?></span>
              <span class="item">#<?= $tid ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Navigasi halaman">
        <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1, $q, $cat, $sort)) ?>">&larr; Sebelumnya</a><?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1) echo '<a href="' . e(page_url(1, $q, $cat, $sort)) . '">1</a>';
        for ($i = $start; $i <= $end; $i++) {
            echo $i === $page
                ? '<span class="current">' . $i . '</span>'
                : '<a href="' . e(page_url($i, $q, $cat, $sort)) . '">' . $i . '</a>';
        }
        if ($end < $totalPages) echo '<a href="' . e(page_url($totalPages, $q, $cat, $sort)) . '">' . $totalPages . '</a>';
        ?>
        <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1, $q, $cat, $sort)) ?>">Selanjutnya &rarr;</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </section>

  <aside>
    <p class="side-title">Kategori</p>
    <div class="panel">
      <ul class="cat-list">
        <li><a href="index.php" class="<?= $cat === null && $q === '' ? 'active' : '' ?>">
          <span class="cat-dot" style="background:var(--accent)"></span> Semua
        </a></li>
        <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?>
          <li><a href="index.php?cat=<?= e((string)$c['slug']) ?>" class="<?= $cat !== null && $cat['slug'] === $c['slug'] ? 'active' : '' ?>">
            <span class="cat-dot" style="background:<?= e((string)$c['color']) ?>"></span>
            <?= e((string)$c['name']) ?>
            <span class="cat-count"><?= (int)$c['thread_count'] ?></span>
          </a></li>
        <?php endwhile; ?>
      </ul>
    </div>
  </aside>
</div>

<?php render_footer(); ?>
