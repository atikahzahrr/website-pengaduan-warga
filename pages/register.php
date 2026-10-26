<?php
/** Halaman registrasi mandiri (tanpa layout dashboard). */
$pesan  = flash();
$oldUser = (string)($_SESSION['old_username'] ?? '');
unset($_SESSION['old_username']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun · Dashboard Aduan Masyarakat</title>
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
      <h1 class="auth-title">Daftar Akun Admin</h1>
      <p class="auth-sub">Buat akun untuk mengakses dashboard pengaduan masyarakat.</p>

      <?php if ($pesan): ?>
        <p class="notice auth-notice"><?= e($pesan) ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="error_page" value="register">
        <div class="field">
          <label for="username">Nama pengguna</label>
          <input id="username" name="username" type="text" maxlength="60" autocomplete="username" required pattern="[a-z0-9._\-]{3,60}" title="3–60 karakter: huruf kecil, angka, titik, garis, atau garis bawah." value="<?= e($oldUser) ?>" placeholder="budi.santoso">
          <p class="small muted">Huruf kecil, angka, titik, garis, atau garis bawah.</p>
        </div>
        <div class="field">
          <label for="nama_lengkap">Nama lengkap</label>
          <input id="nama_lengkap" name="nama_lengkap" type="text" maxlength="100" autocomplete="name" required placeholder="Budi Santoso">
        </div>
        <div class="field">
          <label for="dinas">Unit kerja / dinas (opsional)</label>
          <input id="dinas" name="dinas" type="text" maxlength="120" placeholder="Dinas Pekerjaan Umum (PU)">
        </div>
        <div class="field">
          <label for="password">Kata sandi</label>
          <div class="auth-password">
            <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required placeholder="Minimal 8 karakter">
            <button class="auth-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label for="password2">Konfirmasi kata sandi</label>
          <div class="auth-password">
            <input id="password2" name="password2" type="password" minlength="8" autocomplete="new-password" required placeholder="Ulangi kata sandi">
            <button class="auth-toggle" type="button" data-password-toggle="password2" aria-label="Tampilkan kata sandi">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button class="btn primary auth-submit" type="submit">Daftar</button>
      </form>

      <p class="auth-switch">Sudah punya akun? <a href="?page=login">Masuk</a></p>
    </section>
  </main>

  <footer class="auth-footnote">Seluruh aduan, identitas, survei, dan kamera pada dashboard ini merupakan data simulasi.</footer>
  <script src="assets/app.js"></script>
</body>
</html>
