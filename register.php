<?php
/**
 * Halaman pendaftaran akun (opsional).
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
        redirect('register.php?' . flash('error', $e->getMessage()));
    }
    if (!honeypot_ok()) {
        redirect('index.php');
    }
    if (!rate_limit($conn, 'register', 5, 60)) {
        redirect('register.php?' . flash('error', 'Terlalu banyak percobaan. Coba lagi nanti.'));
    }

    $password  = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($password !== $password2) {
        redirect('register.php?' . flash('error', 'Konfirmasi password tidak sama.'));
    }

    $err = register_user($conn, (string)($_POST['username'] ?? ''), (string)($_POST['email'] ?? ''), $password);
    if ($err === null) {
        redirect('index.php?' . flash('success', 'Akun dibuat. Selamat bergabung!'));
    }
    redirect('register.php?' . flash('error', $err));
}

render_header($conn, 'Daftar', false);
?>
<form class="form-card" method="post" action="register.php">
  <h2>Daftar akun</h2>
  <?= csrf_field() . honeypot_field() ?>
  <div class="field">
    <label for="rUser">Username</label>
    <input type="text" id="rUser" name="username" required maxlength="20" pattern="[A-Za-z0-9_]{3,20}" autocomplete="username" autofocus>
    <p class="form-hint">3&ndash;20 karakter: huruf, angka, underscore.</p>
  </div>
  <div class="field">
    <label for="rEmail">Email</label>
    <input type="email" id="rEmail" name="email" required maxlength="255" autocomplete="email">
  </div>
  <div class="field">
    <label for="rPass">Password</label>
    <input type="password" id="rPass" name="password" required minlength="8" autocomplete="new-password">
    <p class="form-hint">Minimal 8 karakter.</p>
  </div>
  <div class="field">
    <label for="rPass2">Ulangi password</label>
    <input type="password" id="rPass2" name="password2" required minlength="8" autocomplete="new-password">
  </div>
  <button type="submit" class="btn" style="width:100%">Buat akun</button>
  <p class="form-footer">Sudah punya akun? <a href="login.php">Masuk</a> &middot; <a href="index.php">Lanjut sebagai anonim</a></p>
</form>
<?php render_footer(); ?>
