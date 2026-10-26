<?php
/** Halaman Kelola Admin — hanya untuk superadmin. */
requireSuperadmin();

$akun = rows("SELECT * FROM users ORDER BY CASE role WHEN 'superadmin' THEN 0 ELSE 1 END, nama_lengkap COLLATE NOCASE");
$total   = count($akun);
$aktif   = count(array_filter($akun, fn($u) => (int)$u['status_aktif'] === 1));
$super   = count(array_filter($akun, fn($u) => $u['role'] === 'superadmin'));
$saya    = (int)(currentUser()['id_user'] ?? 0);
?>
<div class="stats">
  <?= statCard('Total Akun', nf($total), 'Superadmin dan admin') ?>
  <?= statCard('Akun Aktif', nf($aktif), 'Dapat masuk dashboard', 'green') ?>
  <?= statCard('Superadmin', nf($super), 'Akses penuh', 'blue') ?>
  <?= statCard('Akun Nonaktif', nf($total - $aktif), 'Tidak dapat masuk', 'amber') ?>
</div>

<div class="two-cols">
  <section class="sub-panel">
    <h3>Tambah Admin Baru</h3>
    <p class="small muted">Akun baru langsung aktif dan dapat masuk ke dashboard.</p>
    <form method="post" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="save_admin">
      <input type="hidden" name="error_page" value="admin">
      <div class="field"><label for="username">Nama pengguna</label>
        <input id="username" name="username" type="text" maxlength="60" required pattern="[a-z0-9._\-]{3,60}" placeholder="budi.santoso"></div>
      <div class="field"><label for="nama_lengkap">Nama lengkap</label>
        <input id="nama_lengkap" name="nama_lengkap" type="text" maxlength="100" required placeholder="Budi Santoso"></div>
      <div class="field wide"><label for="dinas">Unit kerja / dinas</label>
        <input id="dinas" name="dinas" type="text" maxlength="120" placeholder="Dinas Pekerjaan Umum (PU)"></div>
      <div class="field"><label for="role">Peran</label>
        <select id="role" name="role">
          <option value="admin">Admin</option>
          <option value="superadmin">Superadmin</option>
        </select></div>
      <div class="field"><label for="password">Kata sandi</label>
        <div class="auth-password">
          <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password" placeholder="Minimal 8 karakter">
          <button class="auth-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      <div class="field wide"><label for="password2">Konfirmasi kata sandi</label>
        <div class="auth-password">
          <input id="password2" name="password2" type="password" minlength="8" required autocomplete="new-password">
          <button class="auth-toggle" type="button" data-password-toggle="password2" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      <div class="field wide actions"><button class="btn primary" type="submit">+ Tambah Admin</button></div>
    </form>
  </section>

  <section class="sub-panel">
    <h3>Atur Ulang Kata Sandi</h3>
    <p class="small muted">Kata sandi baru minimal 8 karakter dan langsung berlaku.</p>
    <form method="post" class="form-grid">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="reset_admin_password">
      <input type="hidden" name="error_page" value="admin">
      <div class="field wide"><label for="id_user">Akun</label>
        <select id="id_user" name="id_user" required>
          <?php foreach ($akun as $u): ?>
            <option value="<?= (int)$u['id_user'] ?>"><?= e($u['username']) ?> · <?= e($u['nama_lengkap']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="reset_password">Kata sandi baru</label>
        <div class="auth-password">
          <input id="reset_password" name="password" type="password" minlength="8" required autocomplete="new-password">
          <button class="auth-toggle" type="button" data-password-toggle="reset_password" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      <div class="field"><label for="reset_password2">Konfirmasi</label>
        <div class="auth-password">
          <input id="reset_password2" name="password2" type="password" minlength="8" required autocomplete="new-password">
          <button class="auth-toggle" type="button" data-password-toggle="reset_password2" aria-label="Tampilkan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div></div>
      <div class="field wide actions"><button class="btn primary" type="submit">Perbarui Kata Sandi</button></div>
    </form>
  </section>
</div>

<div class="table-wrap">
  <table class="accounts-table">
    <thead><tr>
      <th scope="col">Pengguna</th><th scope="col">Peran</th><th scope="col">Unit Kerja</th>
      <th scope="col">Status</th><th scope="col">Terakhir Masuk</th><th scope="col">Aksi</th>
    </tr></thead>
    <tbody>
    <?php foreach ($akun as $u): ?>
      <tr>
        <td class="person-cell">
          <div class="person-name">
            <span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['nama_lengkap'], 0, 1))) ?></span>
            <div>
              <strong><?= e($u['nama_lengkap']) ?></strong>
              <p class="muted"><span class="ktp-number"><?= e($u['username']) ?></span><?= (int)$u['id_user'] === $saya ? ' · akun Anda' : '' ?></p>
            </div>
          </div>
        </td>
        <td><span class="role-chip <?= $u['role'] === 'superadmin' ? 'super' : '' ?>"><?= e(roleLabel($u['role'])) ?></span></td>
        <td><span class="agency-name"><?= e($u['dinas'] ?: '—') ?></span></td>
        <td><span class="status <?= (int)$u['status_aktif'] ? 'done' : 'verify' ?>"><?= (int)$u['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td><span class="small muted"><?= $u['terakhir_masuk'] ? e(tanggalPanjang($u['terakhir_masuk'])) : 'Belum pernah' ?></span></td>
        <td>
          <div class="row-actions">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="toggle_admin">
              <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
              <input type="hidden" name="error_page" value="admin">
              <button class="btn" type="submit"><?= (int)$u['status_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
            <form method="post" data-confirm="Hapus akun <?= e($u['username']) ?>?">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete_admin">
              <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
              <input type="hidden" name="error_page" value="admin">
              <button class="btn danger" type="submit">Hapus</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$akun): ?><tr><td colspan="6" class="empty">Belum ada akun.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="notice">Akun admin dibuat oleh superadmin dan dapat langsung masuk ke dashboard. Semua data pada halaman ini merupakan data simulasi.</p>
