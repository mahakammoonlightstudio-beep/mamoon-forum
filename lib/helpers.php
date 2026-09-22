<?php
/**
 * Helper umum: session, escaping, flash message, CSRF,
 * rate limit (memakai tabel rate_limits yang sudah ada), dan honeypot.
 */
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('mamoon_session');
    session_start();
}

/** Escape output HTML. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect lalu berhenti. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Query string flash message utk redirect: redirect('index.php?' . flash('error', 'Pesan')) */
function flash(string $type, string $msg): string
{
    $key = $type === 'error' ? 'error' : 'success';
    return $key . '=' . rawurlencode($msg);
}

/** Ambil pesan flash dari query string (sudah aman-escape di pemanggil). */
function flash_from_url(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr($v, 0, 300) : '';
}

/** Token CSRF saat ini (dibuat sekali per session). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Bidang hidden CSRF untuk form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Validasi CSRF pada POST; lempar Exception bila gagal. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        throw new RuntimeException('Sesi kedaluwarsa, muat ulang halaman lalu coba lagi.');
    }
}

/** IP klien (mendukung proxy sederhana Cloudflare/InfinityFree). */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', (string)$_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Rate limit sederhana berbasis tabel rate_limits.
 * Return true bila aksi diIZINKAN, false bila melebihi batas.
 */
function rate_limit(mysqli $db, string $action, int $max, int $windowMinutes): bool
{
    $ip   = client_ip();
    $stmt = $db->prepare('SELECT attempt_count, window_start FROM rate_limits WHERE ip_address = ? AND action_type = ?');
    $stmt->bind_param('ss', $ip, $action);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $now = time();

    if ($row === null) {
        $stmt = $db->prepare('INSERT INTO rate_limits (ip_address, action_type, attempt_count, window_start) VALUES (?, ?, 1, NOW())');
        $stmt->bind_param('ss', $ip, $action);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    $windowStart = strtotime((string)$row['window_start']) ?: $now;
    if ($now - $windowStart > $windowMinutes * 60) {
        // Jendela kedaluwarsa — reset hitungan.
        $stmt = $db->prepare('UPDATE rate_limits SET attempt_count = 1, window_start = NOW() WHERE ip_address = ? AND action_type = ?');
        $stmt->bind_param('ss', $ip, $action);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    if ((int)$row['attempt_count'] >= $max) {
        return false;
    }

    $stmt = $db->prepare('UPDATE rate_limits SET attempt_count = attempt_count + 1 WHERE ip_address = ? AND action_type = ?');
    $stmt->bind_param('ss', $ip, $action);
    $stmt->execute();
    $stmt->close();
    return true;
}

/**
 * Bind parameter dinamis lalu eksekusi statement.
 * Solusi untuk bind_param yang butuh argumen by-reference.
 */
function bind_and_execute(mysqli_stmt $stmt, string $types, array $args): void
{
    if ($types !== '') {
        $refs = [];
        foreach ($args as $k => $v) {
            $refs[$k] = &$args[$k];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
}

/** Honeypot anti-bot: field tersembunyi yang harus tetap kosong. */
function honeypot_ok(): bool
{
    return ($_POST['website'] ?? '') === '';
}

/** Bidang honeypot untuk form (disembunyikan via CSS). */
function honeypot_field(): string
{
    return '<input type="text" name="website" value="" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">';
}
