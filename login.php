<?php
/**
 * Halaman login (opsional — forum tetap bisa dipakai anonymous).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

/** @var mysqli $conn */

if (current_user($conn) !== null) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
    } catch (RuntimeException $e) {
        redirect('login.php?' . flash('error', $e->getMessage()));
    }
    if (!honeypot_ok()) {
        redirect('index.php');
    }
    if (!rate_limit($conn, 'login', 10, 15)) {
        redirect('login.php?' . flash('error', 'Terlalu banyak percobaan. Coba lagi nanti.'));
    }

    $err = login_user($conn, trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''));
    if ($err === null) {
        redirect('index.php?' . flash('success', 'Selamat datang kembali!'));
    }
    redirect('login.php?' . flash('error', $err));
}

render_header($conn, 'Masuk', false);
?>
<form class="form-card" method="post" action="login.php">
  <h2>Masuk</h2>
  <?= csrf_field() . honeypot_field() ?>
  <div class="field">
    <label for="lUser">Username</label>
    <input type="text" id="lUser" name="username" required maxlength="20" autocomplete="username" autofocus>
  </div>
  <div class="field">
    <label for="lPass">Password</label>
    <input type="password" id="lPass" name="password" required autocomplete="current-password">
  </div>
  <button type="submit" class="btn" style="width:100%">Masuk</button>
  <p class="form-footer">Belum punya akun? <a href="register.php">Daftar</a> &middot; <a href="index.php">Lanjut sebagai anonim</a></p>
</form>
<?php render_footer(); ?>
