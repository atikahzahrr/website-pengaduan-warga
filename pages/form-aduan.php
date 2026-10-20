<?php
$id = $_GET['id'] ?? '';
$a  = $id ? row('SELECT * FROM aduan WHERE id_aduan = ?', [$id]) : null;
$a ??= ['id_aduan' => '', 'id_pelapor' => '', 'id_kategori' => '', 'topik' => '', 'deskripsi' => '', 'lokasi_kejadian' => ''];
$pelapor  = rows('SELECT id_pelapor, nama FROM pelapor WHERE status_aktif = 1 OR id_pelapor = ? ORDER BY nama', [(int)$a['id_pelapor']]);
$kategori = rows('SELECT id_kategori, label_kategori FROM kategori ORDER BY label_kategori');
?>
<form method="post" enctype="multipart/form-data" class="form-grid">
  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
  <input type="hidden" name="action" value="save_aduan">
  <input type="hidden" name="id_aduan" value="<?= e($a['id_aduan']) ?>">
  <input type="hidden" name="error_page" value="form-aduan<?= $id ? '&id=' . e($id) : '' ?>">
  <div class="field"><label for="id_pelapor">Pengadu</label>
    <select id="id_pelapor" name="id_pelapor" required><option value="">Pilih pengadu</option>
      <?php foreach ($pelapor as $p): ?><option value="<?= (int)$p['id_pelapor'] ?>" <?= (int)$p['id_pelapor'] === (int)$a['id_pelapor'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="field"><label for="id_kategori">Kategori</label>
    <select id="id_kategori" name="id_kategori" required><option value="">Pilih kategori</option>
      <?php foreach ($kategori as $k): ?><option value="<?= e($k['id_kategori']) ?>" <?= $k['id_kategori'] === $a['id_kategori'] ? 'selected' : '' ?>><?= e($k['label_kategori']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="field wide"><label for="topik">Topik aduan</label><input id="topik" name="topik" maxlength="180" required value="<?= e($a['topik']) ?>"></div>
  <div class="field wide"><label for="lokasi">Lokasi kejadian</label><input id="lokasi" name="lokasi_kejadian" maxlength="180" required value="<?= e($a['lokasi_kejadian']) ?>"></div>
  <div class="field wide"><label for="deskripsi">Uraian</label><textarea id="deskripsi" name="deskripsi" maxlength="1000"><?= e($a['deskripsi']) ?></textarea></div>
  <div class="field wide"><label for="foto">Foto bukti awal (JPG/PNG/WebP, maks. 8 MB)</label><input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
  <div class="field wide actions"><button class="btn primary" type="submit">Simpan Aduan</button><a class="btn" href="?page=kelola-aduan">Batal</a></div>
</form>
