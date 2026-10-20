<?php
$id = $_GET['id'] ?? '';
$a  = row('SELECT a.*, p.nama, k.label_kategori FROM aduan a JOIN pelapor p USING (id_pelapor) JOIN kategori k USING (id_kategori) WHERE a.id_aduan = ?', [$id]);
if (!$a) { echo '<p class="notice">Aduan tidak ditemukan.</p>'; return; }
$last    = row('SELECT * FROM progres_aduan WHERE id_aduan = ? ORDER BY id_progres DESC LIMIT 1', [$id]);
$history = rows('SELECT * FROM progres_aduan WHERE id_aduan = ? ORDER BY id_progres DESC', [$id]);
?>
<p class="muted" style="margin-bottom:16px"><strong>#<?= e($a['id_aduan']) ?></strong> · <?= e($a['topik']) ?> · <?= e($a['nama']) ?> · <?= e($a['label_kategori']) ?></p>
<form method="post" enctype="multipart/form-data" class="form-grid">
  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
  <input type="hidden" name="action" value="save_progress">
  <input type="hidden" name="id_aduan" value="<?= e($id) ?>">
  <input type="hidden" name="error_page" value="form-progress&id=<?= e($id) ?>">
  <div class="field"><label for="status">Status</label>
    <select id="status" name="status"><?php foreach (STATUSES as $s): ?><option <?= $s === $a['status'] ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="persen">Progress (%)</label><input id="persen" name="persen" type="number" min="0" max="100" required value="<?= (int)($last['persentase_progres'] ?? 0) ?>"></div>
  <div class="field wide"><label for="catatan">Catatan tindak lanjut</label><textarea id="catatan" name="catatan" maxlength="1000" required></textarea></div>
  <div class="field wide"><label for="foto">Foto setelah penanganan (opsional)</label><input id="foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
  <div class="field wide actions"><button class="btn primary" type="submit">Simpan Progress</button><a class="btn" href="?page=progress">Batal</a></div>
</form>
<div class="timeline"><h3>Riwayat Tindak Lanjut</h3>
  <?php foreach ($history as $h): ?>
    <div class="timeline-entry"><p class="small muted"><?= e(tanggal($h['updated_at'])) ?></p>
      <p><?= statusBadge($h['status_tahapan']) ?> · <?= (int)$h['persentase_progres'] ?>%</p><p><?= e($h['catatan_petugas']) ?></p></div>
  <?php endforeach; ?>
</div>
