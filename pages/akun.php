<?php
/** Halaman Profil & Akun untuk pengguna yang sedang login. */
$user  = currentUser();
$akun  = row('SELECT * FROM users WHERE id_user = ?', [$user['id_user']]);
?>
<div class="two-cols">
  <section class="sub-panel">
    <h3>Informasi Akun</h3>
    <p class="small muted">Data akun yang sedang digunakan untuk masuk dashboard.</p>
    <div class="profile-banner">
      <span class="user-avatar large" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?></span>
      <div>
        <strong><?= e($user['nama']) ?></strong>
        <p class="small muted"><span class="ktp-number"><?= e($user['username']) ?></span></p>
        <span class="role-chip <?= $user['role'] === 'superadmin' ? 'super' : '' ?>"><?= e(roleLabel($user['role'])) ?></span>
      </div>
    </div>
    <dl class="detail-list">
      <dt>Nama pengguna</dt><dd class="ktp-number"><?= e($user['username']) ?></dd>
      <dt>Nama lengkap</dt><dd><?= e($user['nama']) ?></dd>
      <dt>Unit kerja</dt><dd><?= e($user['dinas'] ?: '—') ?></dd>
      <dt>Peran</dt><dd><?= e(roleLabel($user['role'])) ?></dd>
      <dt>Dibuat</dt><dd><?= e(tanggalPanjang($akun['dibuat_at'] ?? date('Y-m-d H:i:s'))) ?></dd>
      <dt>Terakhir masuk</dt><dd><?= !empty($akun['terakhir_masuk']) ? e(tanggalPanjang($akun['terakhir_masuk'])) : 'Sesi pertama' ?></dd>
    </dl>
    <p class="notice">Akun baru dibuatkan oleh superadmin melalui menu Kelola Admin.</p>
  </section>

  <section class="sub-panel">
    <h3>Ubah Kata Sandi</h3>
    <p class="small muted">Gunakan kata sandi lama untuk memverifikasi permintaan.</p>
    <form method="post" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="error_page" value="akun">
      <div class="field wide"><label for="password_lama">Kata sandi lama</label>
        <div class="auth-password">
          <input id="password_lama" name="password_lama" type="password" required autocomplete="current-password">
          <button class="auth-toggle" type="button" data-password-toggle="password_lama" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      <div class="field wide"><label for="password_baru">Kata sandi baru</label>
        <div class="auth-password">
          <input id="password_baru" name="password_baru" type="password" minlength="8" required autocomplete="new-password" placeholder="Minimal 8 karakter">
          <button class="auth-toggle" type="button" data-password-toggle="password_baru" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      <div class="field wide"><label for="password_konfirmasi">Konfirmasi kata sandi baru</label>
        <div class="auth-password">
          <input id="password_konfirmasi" name="password_konfirmasi" type="password" minlength="8" required autocomplete="new-password">
          <button class="auth-toggle" type="button" data-password-toggle="password_konfirmasi" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      <div class="field wide actions"><button class="btn primary" type="submit">Simpan Kata Sandi</button></div>
    </form>
  </section>
</div>
