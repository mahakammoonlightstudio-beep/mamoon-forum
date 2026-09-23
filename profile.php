<?php
/**
 * Halaman profil user: statistik + aktivitas terbaru.
 * Diakses via profile.php?u=username
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/votes.php';

/** @var mysqli $conn */

$uname = trim((string)($_GET['u'] ?? ''));
if ($uname === '') {
    redirect('index.php');
}
$uname = mb_substr($uname, 0, 50);

$stmt = $conn->prepare('SELECT id, username, role, avatar_url, created_at FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
bind_and_execute($stmt, 's', [$uname]);
$profile = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if ($profile === null) {
    http_response_code(404);
    render_header($conn, 'Profil tidak ditemukan', false);
    echo '<div class="empty-state panel panel-pad" style="margin-top:24px">' . icon('user') .
         '<p>Profil tidak ditemukan atau akun sudah dinonaktifkan.</p>' .
         '<a class="btn" href="index.php">Kembali ke forum</a></div>';
    render_footer();
    exit;
}

$uid  = (int)$profile['id'];
$me   = current_user($conn);
$isMe = $me !== null && (int)$me['id'] === $uid;

$rep = user_reputation($conn, $uid);

// ---------- Statistik ----------
$stmt = $conn->prepare('SELECT COUNT(*) AS c FROM threads WHERE user_id = ?');
bind_and_execute($stmt, 'i', [$uid]);
$nThreads = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS c FROM posts WHERE user_id = ?');
bind_and_execute($stmt, 'i', [$uid]);
$nReplies = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS c FROM votes WHERE user_id = ?');
bind_and_execute($stmt, 'i', [$uid]);
$nVotesGiven = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// ---------- Thread terbaru (10) ----------
$stmt = $conn->prepare('SELECT id, title, created_at, sticky, locked FROM threads WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
bind_and_execute($stmt, 'i', [$uid]);
$tRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$tScores = vote_scores($conn, 'thread', array_map(fn($r) => (int)$r['id'], $tRows));

// ---------- Balasan terbaru (10) ----------
$stmt = $conn->prepare(
    'SELECT p.id, p.content, p.created_at, p.thread_id, t.title AS thread_title
     FROM posts p JOIN threads t ON t.id = p.thread_id
     WHERE p.user_id = ?
     ORDER BY p.created_at DESC, p.id DESC LIMIT 10'
);
bind_and_execute($stmt, 'i', [$uid]);
$pRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$pScores = vote_scores($conn, 'post', array_map(fn($r) => (int)$r['id'], $pRows));

$avatarLetter = mb_strtoupper(mb_substr((string)$profile['username'], 0, 1));
$joined = date('d M Y', (int)strtotime((string)$profile['created_at']));

render_header($conn, 'Profil ' . $profile['username']);
?>

<p class="back-link"><a href="index.php"><?= icon('back') ?> Kembali ke forum</a></p>

<div class="panel panel-pad profile-head">
  <?php if (!empty($profile['avatar_url'])): ?>
    <img class="profile-avatar" src="<?= e((string)$profile['avatar_url']) ?>" alt="" width="64" height="64">
  <?php else: ?>
    <span class="avatar profile-avatar"><?= e($avatarLetter) ?></span>
  <?php endif; ?>
  <div>
    <h2 style="margin:0"><?= e((string)$profile['username']) ?>
      <?php if ($isMe): ?><a class="btn btn-ghost btn-sm" href="#edit-profil" style="margin-left:8px"><?= icon('user') ?> Edit profil</a><?php endif; ?>
    </h2>
    <p class="thread-meta" style="margin-top:4px">
      <span class="item"><em class="role-tag"><?= e((string)$profile['role']) ?></em></span>
      <span class="item"><?= icon('clock') ?> Bergabung <?= e($joined) ?></span>
      <?php if ($isMe): ?><span class="item badge badge-pin">Ini profil kamu</span><?php endif; ?>
    </p>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card"><b><?= $nThreads ?></b><span>Thread</span></div>
  <div class="stat-card"><b><?= $nReplies ?></b><span>Balasan</span></div>
  <div class="stat-card"><b><?= $nVotesGiven ?></b><span>Vote diberikan</span></div>
  <div class="stat-card"><b class="<?= $rep > 0 ? 'rep-pos' : ($rep < 0 ? 'rep-neg' : '') ?>"><?= $rep ?></b><span>Reputasi</span></div>
</div>

<?php if ($isMe): ?>
<details class="panel panel-pad" id="edit-profil" style="margin-bottom:14px">
  <summary style="cursor:pointer;font-weight:700;display:flex;align-items:center;gap:8px">
    <?= icon('user') ?> Edit profil
  </summary>
  <form action="edit_profile.php" method="post" enctype="multipart/form-data" style="margin-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="avatar">
    <div class="file-upload-wrapper">
      <label class="file-upload-btn" for="avatarInput"><?= icon('image') ?> Ganti avatar</label>
      <input type="file" name="avatar" id="avatarInput" accept="image/*">
      <span class="file-name" id="fileName">Tidak ada file</span>
      <button type="button" class="file-clear" id="fileClear">&times;</button>
    </div>
    <button type="submit" class="btn">Simpan avatar</button>
    <p class="form-hint">Otomatis dikompres jadi maks 256px. Format: JPG, PNG, GIF, WebP.</p>
  </form>
  <hr>
  <form action="edit_profile.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <div class="field">
      <label for="curPass">Password saat ini</label>
      <input type="password" id="curPass" name="current_password" required autocomplete="current-password">
    </div>
    <div class="field">
      <label for="newPass">Password baru (min. 8 karakter)</label>
      <input type="password" id="newPass" name="new_password" required minlength="8" autocomplete="new-password">
    </div>
    <div class="field">
      <label for="confPass">Ulangi password baru</label>
      <input type="password" id="confPass" name="confirm_password" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Ganti password</button>
  </form>
</details>
<?php endif; ?>

<div class="panel">
  <p class="side-title" style="padding:14px 16px 0"><?= icon('chat') ?> Thread terbaru</p>
  <?php if ($tRows === []): ?>
    <div class="empty-state" style="padding:14px 16px 18px"><p>Belum membuat thread.</p></div>
  <?php else: ?>
    <?php foreach ($tRows as $r): ?>
      <?php $rid = (int)$r['id']; ?>
      <a class="act-row" href="thread.php?id=<?= $rid ?>">
        <span class="act-title">
          <?php if ((int)$r['sticky']): ?><span class="badge badge-pin"><?= icon('pin') ?>Pin</span><?php endif; ?>
          <?php if ((int)$r['locked']): ?><span class="badge badge-lock"><?= icon('lock') ?>Terkunci</span><?php endif; ?>
          <?php if (($tScores[$rid]['score'] ?? 0) >= HOT_VOTE_SCORE): ?><span class="badge badge-hot"><?= icon('flame') ?>Hot</span><?php endif; ?>
          <?= e((string)$r['title']) ?>
        </span>
        <span class="act-meta"><?= icon('arrow-up') ?> <?= (int)($tScores[$rid]['score'] ?? 0) ?> &middot; <?= e(time_ago((string)$r['created_at'])) ?></span>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="panel">
  <p class="side-title" style="padding:14px 16px 0"><?= icon('back') ?> Balasan terbaru</p>
  <?php if ($pRows === []): ?>
    <div class="empty-state" style="padding:14px 16px 18px"><p>Belum membuat balasan.</p></div>
  <?php else: ?>
    <?php foreach ($pRows as $r): ?>
      <?php $rid = (int)$r['id']; ?>
      <a class="act-row" href="thread.php?id=<?= (int)$r['thread_id'] ?>#p<?= $rid ?>">
        <span class="act-title">di &ldquo;<?= e((string)$r['thread_title']) ?>&rdquo;</span>
        <span class="act-snippet"><?= e(mb_substr(preg_replace('/\s+/u', ' ', (string)$r['content']) ?? '', 0, 90)) ?></span>
        <span class="act-meta"><?= icon('arrow-up') ?> <?= (int)($pScores[$rid]['score'] ?? 0) ?> &middot; <?= e(time_ago((string)$r['created_at'])) ?></span>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
