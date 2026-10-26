<?php
/** Halaman login mandiri (tanpa layout dashboard). */
$pesan  = flash();
$oldUser = (string)($_SESSION['old_username'] ?? '');
unset($_SESSION['old_username']);
$kunci  = loginLockedFor();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk · Dashboard Aduan Masyarakat</title>
<link rel="stylesheet" href="assets/style.css">
<script>/* Terapkan tema lebih awal agar tidak berkedip */
(function(){try{var p=localStorage.getItem('aduan-dashboard-theme')||'auto';
document.documentElement.dataset.theme=p==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;}catch(e){}})();</script>
</head>
<body class="auth-body">
  <div class="auth-topbar">
    <label class="theme-control">Tema
      <select id="theme-select" aria-label="Pilih tema dashboard"><option value="auto">Otomatis</option><option value="light">Terang</option><option value="dark">Gelap</option></select>
    </label>
  </div>

  <main class="auth-wrap auth-wrap-single">
    <section class="auth-card auth-card-centered">
      <div class="auth-brand">
        <span class="auth-logo" aria-hidden="true">▦</span>
        <div>
          <strong class="auth-brand-name">Aduan Masyarakat</strong>
          <p class="small muted">Dashboard Eksekutif</p>
        </div>
      </div>

      <span class="pill">PORTAL ADMIN</span>
      <h1 class="auth-title">Masuk ke Dashboard</h1>
      <p class="auth-sub">Gunakan akun admin yang diberikan untuk memantau aduan masyarakat.</p>

      <?php if ($pesan): ?>
        <p class="notice auth-notice"><?= e($pesan) ?></p>
      <?php endif; ?>
      <?php if ($kunci > 0): ?>
        <p class="notice auth-notice auth-warn">Terlalu banyak percobaan login. Coba lagi dalam <?= (int)$kunci ?> detik.</p>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="login">
        <div class="field">
          <label for="username">Nama pengguna</label>
          <input id="username" name="username" type="text" maxlength="60" autocomplete="username" required autofocus
                 value="<?= e($oldUser) ?>" placeholder="superadmin">
        </div>
        <div class="field">
          <label for="password">Kata sandi</label>
          <div class="auth-password">
            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••">
            <button class="auth-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button class="btn primary auth-submit" type="submit">Masuk</button>
      </form>

      <p class="auth-switch">Belum punya akun? <a href="?page=register">Daftar akun admin</a></p>

      <div class="auth-demo">
        <strong>Akun simulasi bawaan</strong>
        <p><span class="ktp-number"><?= e(DEFAULT_ADMIN_USERNAME) ?></span> / <span class="ktp-number"><?= e(DEFAULT_ADMIN_PASSWORD) ?></span></p>
        <p class="small muted">Superadmin dapat menambah admin lain melalui menu Kelola Admin.</p>
      </div>
    </section>
  </main>

  <footer class="auth-footnote">Seluruh aduan, identitas, survei, dan kamera pada dashboard ini merupakan data simulasi.</footer>
  <script src="assets/app.js"></script>
</body>
</html>
