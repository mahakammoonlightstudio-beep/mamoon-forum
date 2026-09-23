<?php
/**
 * Layout bersama: header, footer, ikon SVG inline, toast, helper waktu.
 * Menggantikan Font Awesome CDN (hemat ~100 KB per halaman).
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

/** Ikon SVG inline (gaya feather, 24x24, stroke currentColor). */
function icon(string $name): string
{
    $paths = [
        'image'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'pin'     => '<path d="M12 17v5"/><path d="M9 10.8V6a3 3 0 1 1 6 0v4.8l2.5 3.2h-11L9 10.8Z"/>',
        'lock'    => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'moon'    => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>',
        'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'back'    => '<path d="M19 12H5m7-7-7 7 7 7"/>',
        'chat'    => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'warn'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>',
        'check'   => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>',
        'arrow-up'   => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
        'arrow-down' => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        'flame'      => '<path d="M12 22c4.4 0 8-3.4 8-7.6 0-3-1.7-5-3.2-6.9-.8-1-1.9-2.3-2.8-3.5-.5 2.2-1.4 3.4-2.7 4.5C9.6 10 8 8.5 8.3 6c-2 2-4.3 5.2-4.3 8.4C4 18.6 7.6 22 12 22Z"/><path d="M12 22c2.2 0 4-1.7 4-3.8 0-1.5-.8-2.6-1.6-3.6-.5-.6-1-1.4-1.4-2.2-.9 1.2-2.8 2-2.6 4 .1 1-.6 1.4-1.1 1.2-.8-.3-1.3-1.4-1.1-2.6-.8.9-1.2 2-1.2 3.2 0 2.1 1.8 3.8 4 3.8Z"/>',
    ];
    $p = $paths[$name] ?? $paths['chat'];
    return '<svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

/** "5 menit lalu" dsb. */
function time_ago(?string $ts): string
{
    if ($ts === null) {
        return '-';
    }
    $t = strtotime($ts);
    if ($t === false) {
        return e($ts);
    }
    $diff = time() - $t;
    if ($diff < 60)      return 'baru saja';
    if ($diff < 3600)    return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400)   return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800)  return floor($diff / 86400) . ' hari lalu';
    return date('d M Y H:i', $t);
}

/** Toast notifikasi dari ?error= / ?success=. */
function render_toasts(): void
{
    $error   = flash_from_url('error');
    $success = flash_from_url('success');
    if ($error !== '') {
        echo '<div class="toast toast-error">' . icon('warn') . '<span>' . e(urldecode($error)) . '</span><button class="toast-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button></div>';
    }
    if ($success !== '') {
        echo '<div class="toast toast-success">' . icon('check') . '<span>' . e(urldecode($success)) . '</span><button class="toast-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button></div>';
    }
}

/**
 * Buka halaman HTML lengkap (head, header, nav, toast, main terbuka).
 * $title judul tab; $showSearch menampilkan kotak pencarian di nav.
 */
function render_header(mysqli $db, string $title, bool $showSearch = true): void
{
    $user = current_user($db);
    $site = defined('SITE_NAME') ? SITE_NAME : 'Mamoon Forum';
    ?><!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - <?= e($site) ?></title>
<meta name="description" content="Mamoon Forum — tempat diskusi santai. Ringan, cepat, tanpa iklan.">
<link rel="icon" type="image/png" href="logo-forum.png">
<link rel="stylesheet" href="assets/style.css">
<script>(function(){var t=localStorage.getItem('theme');if(!t){t=window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}document.documentElement.setAttribute('data-theme',t)})();</script>
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="index.php"><img src="logo-forum.png" alt="" width="26" height="26"><span><?= e($site) ?></span></a>
    <nav class="nav">
      <?php if ($showSearch): ?>
      <form class="nav-search" action="index.php" method="get" role="search">
        <label class="sr-only" for="navQ">Cari thread</label>
        <?= icon('search') ?><input id="navQ" type="search" name="q" placeholder="Cari thread&hellip;" value="<?= e(flash_from_url('q')) ?>">
      </form>
      <?php endif; ?>
      <?php if ($user): ?>
        <?php $rep = user_reputation($db, (int)$user['id']); ?>
        <a class="user-chip" href="profile.php?u=<?= e((string)$user['username']) ?>" title="Reputasi: <?= $rep ?>"><?php if (!empty($user['avatar_url'])): ?><img class="chip-avatar" src="<?= e((string)$user['avatar_url']) ?>" alt="" width="20" height="20"><?php else: ?><?= icon('user') ?><?php endif; ?><span><?= e($user['username']) ?></span><em><?= e($user['role']) ?></em><span class="rep" title="Reputasi"><?= icon('arrow-up') ?><?= $rep ?></span></a>
        <form method="post" action="logout.php" class="inline-form"><?= csrf_field() ?><button class="btn btn-ghost btn-icon" title="Keluar"><?= icon('logout') ?></button></form>
      <?php else: ?>
        <a class="btn btn-ghost" href="login.php">Masuk</a>
        <a class="btn" href="register.php">Daftar</a>
      <?php endif; ?>
      <button id="theme-toggle" class="btn btn-ghost btn-icon" title="Ganti tema"><?= str_replace('<svg class="icon"', '<svg class="icon icon-sun"', icon('sun')) ?><?= str_replace('<svg class="icon"', '<svg class="icon icon-moon"', icon('moon')) ?></button>
    </nav>
  </div>
</header>
<div class="toast-container"><?= render_toasts() ?></div>
<main class="container" id="main">
<?php
}

/** Tutup halaman. */
function render_footer(): void
{
    ?></main>
<footer class="site-footer">
  <div class="container">Mamoon Forum &mdash; komunitas diskusi santai, dibuat dengan PHP + MySQL.</div>
</footer>
<script src="assets/app.js" defer></script>
</body>
</html><?php
}
