<?php
$q   = trim($_GET['q'] ?? '');
$hal = max(1, (int)($_GET['p'] ?? 1));
$like = "%{$q}%";
$where = 'WHERE p.nama LIKE ? OR p.nik LIKE ? OR p.alamat LIKE ?';
$count = (int)scalar("SELECT COUNT(*) FROM pelapor p {$where}", [$like, $like, $like]);
$pages = max(1, (int)ceil($count / PAGE_SIZE));
$hal   = min($hal, $pages);
$list  = rows("SELECT p.*, (SELECT COUNT(*) FROM aduan WHERE id_pelapor = p.id_pelapor) AS jumlah_aduan
               FROM pelapor p {$where} ORDER BY p.nama LIMIT " . PAGE_SIZE . ' OFFSET ' . (($hal - 1) * PAGE_SIZE), [$like, $like, $like]);

$all = (int)scalar('SELECT COUNT(*) FROM pelapor');
$aktif = (int)scalar('SELECT COUNT(*) FROM pelapor WHERE status_aktif = 1');
?>
<div class="stats">
  <?= statCard('Total Pengadu', nf($all), 'Profil terdaftar') ?>
  <?= statCard('Pengadu Aktif', nf($aktif), 'Dapat membuat aduan', 'green') ?>
  <?= statCard('Pengadu Nonaktif', nf($all - $aktif), 'Riwayat tetap tersedia', 'amber') ?>
  <?= statCard('Aduan Terkait', nf((int)scalar('SELECT COUNT(*) FROM aduan')), 'Termasuk arsip', 'blue') ?>
</div>

<div class="toolbar"><a class="btn primary" href="?page=form-pengadu">+ Tambah Pengadu</a></div>
<form method="get" class="field" style="margin-bottom:16px">
  <input type="hidden" name="page" value="kelola-pengadu">
  <label for="q">Cari pengadu</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Nama, NIK, alamat…">
</form>

<div class="table-wrap">
  <table class="reports-table">
    <thead><tr><th>Pengadu</th><th>NIK</th><th>Alamat &amp; Titik</th><th>Status</th><th>Aduan</th><th>Tindakan</th></tr></thead>
    <tbody>
    <?php foreach ($list as $p): ?>
      <tr>
        <td><div class="person-name"><?= avatar($p) ?><span class="report-person-name"><?= e($p['nama']) ?></span></div></td>
        <td><?= e($p['nik']) ?></td>
        <td><?= e($p['alamat']) ?><br><a class="location-link" target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= e($p['latitude']) ?>,<?= e($p['longitude']) ?>">📍 <?= number_format((float)$p['latitude'], 6) ?>, <?= number_format((float)$p['longitude'], 6) ?></a></td>
        <td><span class="status <?= $p['status_aktif'] ? 'done' : 'verify' ?>"><?= $p['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td><a class="location-link" href="?page=laporan&q=<?= urlencode($p['nama']) ?>"><?= nf((int)$p['jumlah_aduan']) ?> aduan</a></td>
        <td><a class="btn" href="?page=form-pengadu&id=<?= (int)$p['id_pelapor'] ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6" class="empty">Pengadu tidak ditemukan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<div class="pagination">
  <p class="small muted"><?= nf($count) ?> pengadu sesuai pencarian</p>
  <div class="pagination-controls">
    <?php if ($hal > 1): ?><a class="btn" href="<?= e(url(['p' => $hal - 1])) ?>">← Sebelumnya</a><?php endif; ?>
    <span class="small"><?= $hal ?> / <?= $pages ?></span>
    <?php if ($hal < $pages): ?><a class="btn" href="<?= e(url(['p' => $hal + 1])) ?>">Berikutnya →</a><?php endif; ?>
  </div>
</div>
