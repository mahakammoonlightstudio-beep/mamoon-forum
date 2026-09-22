<?php
/**
 * Auth hybrid: forum tetap bisa dipakai anonymous,
 * tapi pengunjung boleh login/register untuk fitur tambahan (vote, moderasi, dst).
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Ambil data user yang sedang login (atau null bila anonymous). */
function current_user(mysqli $db): ?array
{
    static $cached = false;
    static $user   = null;
    if ($cached) {
        return $user;
    }
    $cached = true;

    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId)) {
        return null;
    }

    $stmt = $db->prepare('SELECT id, username, role, avatar_url FROM users WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if ($user === null) {
        unset($_SESSION['user_id']); // sesi basi
    }
    return $user;
}

/**
 * Reputasi user = total skor vote (up − down) dari thread & balasan miliknya.
 * Bisa - (negatif). 0 bila anonim / belum ada vote.
 */
function user_reputation(mysqli $db, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $score = 0;

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE v.vote_type WHEN 'up' THEN 1 ELSE -1 END), 0) AS s
         FROM votes v
         JOIN threads t ON v.target_type = 'thread' AND v.target_id = t.id
         WHERE t.user_id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $score += (int)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
    $stmt->close();

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE v.vote_type WHEN 'up' THEN 1 ELSE -1 END), 0) AS s
         FROM votes v
         JOIN posts p ON v.target_type = 'post' AND v.target_id = p.id
         WHERE p.user_id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $score += (int)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
    $stmt->close();

    return $score;
}

/** Apakah user aktif punya peran moderator/admin? */
function is_mod(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['moderator', 'admin'], true);
}

/** Coba login; return pesan error atau null bila sukses. */
function login_user(mysqli $db, string $username, string $password): ?string
{
    $stmt = $db->prepare('SELECT id, password_hash, is_active FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null || !password_verify($password, (string)$row['password_hash'])) {
        return 'Username atau password salah.';
    }
    if (!(int)$row['is_active']) {
        return 'Akun dinonaktifkan.';
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id'];

    $upd = $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $upd->bind_param('i', $row['id']);
    $upd->execute();
    $upd->close();
    return null;
}

/** Daftar akun baru; return pesan error atau null bila sukses. */
function register_user(mysqli $db, string $username, string $email, string $password): ?string
{
    $username = trim($username);
    $email    = trim(mb_strtolower($email));

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return 'Username 3–20 karakter, hanya huruf, angka, dan underscore.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Format email tidak valid.';
    }
    if (strlen($password) < 8) {
        return 'Password minimal 8 karakter.';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    try {
        $stmt->bind_param('sss', $username, $email, $hash);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        return 'Username atau email sudah terdaftar.';
    }
    $stmt->close();

    $id = (int)$db->insert_id;
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    return null;
}

/** Keluar: hapus sesi + cookie. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
