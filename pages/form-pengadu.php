<?php
$id = (int)($_GET['id'] ?? 0);
$p  = $id ? row('SELECT * FROM pelapor WHERE id_pelapor = ?', [$id]) : null;
$p ??= ['id_pelapor' => 0, 'nik' => '', 'nama' => '', 'avatar_tone' => '#2563eb', 'alamat' => '', 'latitude' => -7.250445, 'longitude' => 112.768845, 'status_aktif' => 1];
?>
<form method="post" class="form-grid">
  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
  <input type="hidden" name="action" value="save_pelapor">
  <input type="hidden" name="id_pelapor" value="<?= (int)$p['id_pelapor'] ?>">
  <input type="hidden" name="error_page" value="form-pengadu<?= $id ? '&id=' . $id : '' ?>">
  <div class="field wide"><label for="nama">Nama lengkap</label><input id="nama" name="nama" maxlength="100" required value="<?= e($p['nama']) ?>"></div>
  <div class="field"><label for="nik">NIK (16 digit)</label><input id="nik" name="nik" pattern="\d{16}" maxlength="16" required value="<?= e($p['nik']) ?>"></div>
  <div class="field"><label for="tone">Warna avatar</label><input id="tone" type="color" name="avatar_tone" value="<?= e($p['avatar_tone'] ?: '#2563eb') ?>"></div>
  <div class="field wide"><label for="alamat">Alamat</label><textarea id="alamat" name="alamat" maxlength="180"><?= e($p['alamat']) ?></textarea></div>
  <div class="field"><label for="lat">Latitude</label><input id="lat" name="latitude" type="number" step="any" min="-90" max="90" required value="<?= e($p['latitude']) ?>"></div>
  <div class="field"><label for="lng">Longitude</label><input id="lng" name="longitude" type="number" step="any" min="-180" max="180" required value="<?= e($p['longitude']) ?>"></div>
  <div class="field wide"><label><input type="checkbox" name="status_aktif" value="1" <?= $p['status_aktif'] ? 'checked' : '' ?>> Pengadu aktif (dapat membuat aduan)</label></div>
  <div class="field wide actions"><button class="btn primary" type="submit">Simpan Pengadu</button><a class="btn" href="?page=kelola-pengadu">Batal</a></div>
</form>
