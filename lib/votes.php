<?php
/**
 * Voting thread & balasan — memakai tabel `votes` yang sudah ada:
 *   votes(user_id, target_type ENUM('thread','post','comment'), target_id, vote_type ENUM('up','down'))
 * dengan UNIQUE KEY (user_id, target_type, target_id) → satu vote per user per target.
 *
 * Logika: klik arah yang sama = batal vote (toggle);
 *         klik arah berbeda = pindah vote (switch).
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Tipe target yang valid. */
function vote_type_valid(string $type): bool
{
    return in_array($type, ['thread', 'post'], true);
}

/**
 * Skor vote untuk banyak target sekaligus (hindari N+1 query).
 * Return map: [target_id => ['up' => int, 'down' => int, 'score' => int]]
 */
function vote_scores(mysqli $db, string $type, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 's' . str_repeat('i', count($ids));
    $args  = array_merge([$type], $ids);

    $stmt = $db->prepare(
        "SELECT target_id,
                SUM(vote_type = 'up')   AS up,
                SUM(vote_type = 'down') AS down
         FROM votes
         WHERE target_type = ? AND target_id IN ($placeholders)
         GROUP BY target_id"
    );
    bind_and_execute($stmt, $types, $args);
    $res = $stmt->get_result();
    $stmt->close();

    $out = [];
    foreach ($ids as $id) {
        $out[$id] = ['up' => 0, 'down' => 0, 'score' => 0];
    }
    while ($row = $res->fetch_assoc()) {
        $id       = (int)$row['target_id'];
        $up       = (int)$row['up'];
        $down     = (int)$row['down'];
        $out[$id] = ['up' => $up, 'down' => $down, 'score' => $up - $down];
    }
    return $out;
}

/**
 * Vote milik seorang user untuk banyak target sekaligus.
 * Return map: [target_id => 'up'|'down'] (id yang belum divote tidak ada di map).
 */
function user_votes(mysqli $db, int $userId, string $type, array $ids): array
{
    if ($userId <= 0) {
        return [];
    }
    $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 'si' . str_repeat('i', count($ids));
    $args  = array_merge([$type, $userId], $ids);

    $stmt = $db->prepare(
        "SELECT target_id, vote_type
         FROM votes
         WHERE target_type = ? AND user_id = ? AND target_id IN ($placeholders)"
    );
    bind_and_execute($stmt, $types, $args);
    $res = $stmt->get_result();
    $stmt->close();

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[(int)$row['target_id']] = (string)$row['vote_type'];
    }
    return $out;
}

/**
 * Terapkan vote dari seorang user:
 *  - vote sama dengan yang tersimpan → hapus (batal);
 *  - vote berbeda                    → update (pindah);
 *  - belum ada vote                  → insert.
 * Return state vote user untuk target tersebut SETELAH operasi: 'up'|'down'|'none'.
 */
function apply_vote(mysqli $db, int $userId, string $type, int $targetId, string $dir): string
{
    $stmt = $db->prepare('SELECT vote_type FROM votes WHERE user_id = ? AND target_type = ? AND target_id = ? LIMIT 1');
    $stmt->bind_param('isi', $userId, $type, $targetId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing !== null && (string)$existing['vote_type'] === $dir) {
        // Toggle: batal vote.
        $stmt = $db->prepare('DELETE FROM votes WHERE user_id = ? AND target_type = ? AND target_id = ?');
        $stmt->bind_param('isi', $userId, $type, $targetId);
        $stmt->execute();
        $stmt->close();
        return 'none';
    }

    if ($existing !== null) {
        // Switch arah vote.
        $stmt = $db->prepare('UPDATE votes SET vote_type = ?, created_at = NOW() WHERE user_id = ? AND target_type = ? AND target_id = ?');
        $stmt->bind_param('sisi', $dir, $userId, $type, $targetId);
        $stmt->execute();
        $stmt->close();
        return $dir;
    }

    // Vote baru.
    $stmt = $db->prepare("INSERT INTO votes (user_id, target_type, target_id, vote_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isis', $userId, $type, $targetId, $dir);
    $stmt->execute();
    $stmt->close();
    return $dir;
}

/**
 * Widget vote box (tanpa JS: dua form mini POST ke vote.php).
 * $myVote: 'up'|'down'|null — menandai tombol yang sedang aktif.
 */
function render_vote_box(string $type, int $id, int $score, ?string $myVote): string
{
    $next = e($_SERVER['REQUEST_URI'] ?? 'index.php');
    $upClass   = $myVote === 'up'   ? 'vote-btn voted-up'   : 'vote-btn';
    $downClass = $myVote === 'down' ? 'vote-btn voted-down' : 'vote-btn';

    $form = static function (string $dir, string $class, string $label, string $title) use ($type, $id, $next): string {
        return '<form method="post" action="vote.php" class="inline-form">'
            . csrf_field()
            . '<input type="hidden" name="type" value="' . e($type) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="dir" value="' . $dir . '">'
            . '<input type="hidden" name="next" value="' . $next . '">'
            . '<button class="' . $class . '" title="' . $title . '" aria-label="' . $title . '">' . icon($label) . '</button>'
            . '</form>';
    };

    return '<div class="vote-box">'
        . $form('up', $upClass, 'arrow-up', 'Setuju (upvote)')
        . '<span class="vote-score' . ($score > 0 ? ' pos' : ($score < 0 ? ' neg' : '')) . '">' . $score . '</span>'
        . $form('down', $downClass, 'arrow-down', 'Tidak setuju (downvote)')
        . '</div>';
}
