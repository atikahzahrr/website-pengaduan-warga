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
  <?= statCard('Total Pengadu', nf($all), 'Profil simulasi') ?>
  <?= statCard('Pengadu Aktif', nf($aktif), 'Dapat membuat aduan', 'green') ?>
  <?= statCard('Pengadu Nonaktif', nf($all - $aktif), 'Riwayat tetap tersedia', 'amber') ?>
  <?= statCard('Aduan Terkait', nf((int)scalar('SELECT COUNT(*) FROM aduan')), 'Termasuk arsip', 'blue') ?>
</div>

<div class="toolbar"><a class="btn primary" href="?page=form-pengadu">+ Tambah Pengadu</a></div>
<form method="get" class="field" style="margin-bottom:16px">
  <input type="hidden" name="page" value="kelola-pengadu">
  <label for="person-search">Cari pengadu</label><input id="person-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Nama, ID, no. KTP, alamat, koordinat…">
</form>

<div class="table-wrap">
  <table>
    <thead><tr><th scope="col">Foto / Nama</th><th scope="col">No. KTP</th><th scope="col">Kontak / Alamat / Lokasi</th><th scope="col">Aduan</th><th scope="col">Status / Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($list as $p): $pid = idPelapor((int)$p['id_pelapor']); ?>
      <tr>
        <td class="person-cell"><div class="person-name"><?= avatar($p) ?><div>
          <button class="report-id" type="button" data-profile="<?= (int)$p['id_pelapor'] ?>"><?= e($p['nama']) ?></button>
          <p class="muted"><?= e($pid) ?> · <?= e(genderOf($p['nama'])) ?></p></div></div></td>
        <td><p class="ktp-number"><?= e($p['nik']) ?></p><span class="small muted">Nomor dummy</span></td>
        <td class="person-address"><p class="muted"><?= e($p['alamat'] ?: 'Belum diisi') ?></p><?= pointLink((float)$p['latitude'], (float)$p['longitude']) ?></td>
        <td><?= nf((int)$p['jumlah_aduan']) ?><div class="row-actions"><a class="btn" href="?page=laporan&q=<?= urlencode($p['nama']) ?>">Lihat Aduan</a></div></td>
        <td><span class="status <?= $p['status_aktif'] ? 'done' : 'verify' ?>"><?= $p['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span>
          <div class="row-actions"><button class="btn" type="button" data-profile="<?= (int)$p['id_pelapor'] ?>">Profil</button><a class="btn" href="?page=form-pengadu&id=<?= (int)$p['id_pelapor'] ?>">Edit Pengadu</a></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="empty">Pengadu tidak ditemukan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<div class="pagination">
  <p id="persons-count" class="small muted" role="status"><?= nf($count) ?> pengadu sesuai pencarian</p>
  <div class="pagination-controls">
    <?php if ($hal > 1): ?><a class="btn" href="<?= e(url(['p' => $hal - 1])) ?>">← Sebelumnya</a><?php else: ?><button class="btn" type="button" disabled>← Sebelumnya</button><?php endif; ?>
    <span id="person-page" class="small"><?= $hal ?> / <?= $pages ?></span>
    <?php if ($hal < $pages): ?><a class="btn" href="<?= e(url(['p' => $hal + 1])) ?>">Berikutnya →</a><?php else: ?><button class="btn" type="button" disabled>Berikutnya →</button><?php endif; ?>
  </div>
</div>

<?php /* Isi dialog profil pengadu */ ?>
<?php foreach ($list as $p): $pid = idPelapor((int)$p['id_pelapor']); ?>
  <div id="profile-<?= (int)$p['id_pelapor'] ?>" data-title="Profil Pengadu" hidden>
    <div class="profile-banner"><span><?= avatar($p, 'large') ?></span>
      <div><strong><?= e($p['nama']) ?></strong><p class="small muted"><?= e($pid) ?> · <?= e(genderOf($p['nama'])) ?></p>
        <span class="status <?= $p['status_aktif'] ? 'done' : 'verify' ?>"><?= $p['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span></div></div>
    <dl class="detail-list">
      <dt>Jenis kelamin</dt><dd><?= e(genderOf($p['nama'])) ?></dd>
      <dt>No. KTP (dummy)</dt><dd class="ktp-number"><?= e($p['nik']) ?></dd>
      <dt>Alamat lengkap</dt><dd><?= e($p['alamat'] ?: 'Belum diisi') ?></dd>
      <dt>Titik lokasi</dt><dd><?= pointLink((float)$p['latitude'], (float)$p['longitude']) ?><p class="small muted">Koordinat simulasi; klik untuk membuka peta.</p></dd>
      <dt>Total aduan</dt><dd><?= nf((int)$p['jumlah_aduan']) ?> aduan</dd>
    </dl>
    <div class="form-actions"><a class="btn primary" href="?page=form-pengadu&id=<?= (int)$p['id_pelapor'] ?>">Edit Pengadu</a><a class="btn" href="?page=laporan&q=<?= urlencode($p['nama']) ?>">Lihat Aduan</a></div>
    <p class="notice">Avatar, nomor KTP, alamat, dan lokasi merupakan data generik untuk simulasi.</p>
  </div>
<?php endforeach; ?>
