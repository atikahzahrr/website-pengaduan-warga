<?php
/**
 * Halaman berbasis aduan: ringkasan, distribusi, log, kelola, progress, dan per-kategori.
 * Mode tampilan ditentukan oleh $page.
 */
$kategoriList = rows('SELECT * FROM kategori ORDER BY label_kategori');
$kategoriMap  = array_column($kategoriList, null, 'id_kategori');
$fixedKategori = isset($kategoriMap[$page]) ? $page : null;
$manage   = $page === 'kelola-aduan';
$progress = $page === 'progress';

/* ---- Filter ---- */
$q        = trim($_GET['q'] ?? '');
$fStatus  = in_array($_GET['status'] ?? '', STATUSES, true) ? $_GET['status'] : '';
$fKat     = $fixedKategori ?? (isset($kategoriMap[$_GET['kategori'] ?? '']) ? $_GET['kategori'] : '');
$arsip    = $manage && ($_GET['arsip'] ?? '') === '1' ? 1 : 0;
$hal      = max(1, (int)($_GET['p'] ?? 1));

$baseWhere  = ['a.is_arsip = ?'];
$baseParams = [$arsip];
if ($fixedKategori) { $baseWhere[] = 'a.id_kategori = ?'; $baseParams[] = $fixedKategori; }

$where  = $baseWhere;
$params = $baseParams;
if ($fStatus) { $where[] = 'a.status = ?'; $params[] = $fStatus; }
if ($fKat && !$fixedKategori) { $where[] = 'a.id_kategori = ?'; $params[] = $fKat; }
if ($q !== '') {
    $where[] = '(a.id_aduan LIKE ? OR p.nama LIKE ? OR a.topik LIKE ? OR a.lokasi_kejadian LIKE ?)';
    array_push($params, ...array_fill(0, 4, "%{$q}%"));
}
$whereSql = implode(' AND ', $where);
$from = 'FROM aduan a JOIN pelapor p USING (id_pelapor) JOIN kategori k USING (id_kategori)';

/* ---- Statistik ---- */
$base = implode(' AND ', $baseWhere);
$total  = (int)scalar("SELECT COUNT(*) FROM aduan a WHERE {$base}", $baseParams);
$byStat = array_column(rows("SELECT status, COUNT(*) n FROM aduan a WHERE {$base} GROUP BY status", $baseParams), 'n', 'status');
$done   = (int)($byStat['Selesai'] ?? 0);
$work   = (int)($byStat['Diproses'] ?? 0);
$verify = (int)($byStat['Verifikasi'] ?? 0);
?>
<div class="stats">
  <?= statCard('Total Aduan Masuk', nf($total), $arsip ? 'Aduan diarsipkan' : 'Aduan aktif') ?>
  <?= statCard('Sudah Selesai', nf($done), pct($done, $total) . ' dari total', 'green') ?>
  <?= statCard('Sedang Diproses', nf($work), 'Dalam penanganan dinas', 'blue') ?>
  <?= statCard('Menunggu Verifikasi', nf($verify), 'Perlu validasi petugas', 'amber') ?>
</div>

<?php if (!in_array($page, ['laporan', 'kelola-aduan', 'progress'], true)): ?>
<?php
$catCounts = array_column(rows("SELECT id_kategori, COUNT(*) n FROM aduan a WHERE {$base} GROUP BY id_kategori", $baseParams), 'n', 'id_kategori');
$circ = 2 * M_PI * 48; $offset = 0;
$segments = [['Selesai', '#10b981', $done], ['Diproses', '#2563eb', $work], ['Verifikasi', '#f59e0b', $verify]];
?>
<div class="two-cols">
  <div>
    <?php if ($fixedKategori): $k = $kategoriMap[$fixedKategori]; ?>
      <h3>Fokus Monitoring</h3>
      <p class="muted" style="margin-top:12px"><?= e($k['label_kategori']) ?> ditangani oleh <strong><?= e($k['dinas_penanggung_jawab']) ?></strong>.</p>
    <?php else: ?>
      <h3>Distribusi Kategori Aduan</h3>
      <?php foreach ($kategoriList as $k) echo barRow($k['label_kategori'], (int)($catCounts[$k['id_kategori']] ?? 0), $total, $k['warna_tema']); ?>
    <?php endif; ?>
  </div>
  <div>
    <h3>Komposisi Status Penanganan</h3>
    <div class="donut-layout">
      <svg class="donut-svg" viewBox="0 0 160 160" role="img" aria-label="Komposisi status penanganan">
        <circle cx="80" cy="80" r="48" fill="none" stroke="#e2e8f0" stroke-width="17"/>
        <?php foreach ($segments as [$label, $color, $n]): $len = $total ? $n / $total * $circ : 0; ?>
          <circle cx="80" cy="80" r="48" fill="none" stroke="<?= $color ?>" stroke-width="17"
                  stroke-dasharray="<?= round($len, 2) ?> <?= round($circ - $len, 2) ?>" stroke-dashoffset="<?= round(-$offset, 2) ?>" transform="rotate(-90 80 80)"/>
          <?php $offset += $len; endforeach; ?>
        <text x="80" y="78" text-anchor="middle" font-size="22" font-weight="800" fill="#172554"><?= nf($total) ?></text>
        <text x="80" y="96" text-anchor="middle" font-size="10">aduan</text>
      </svg>
      <div class="donut-legend">
        <?php foreach ($segments as [$label, $color, $n]): ?>
          <div class="donut-row"><span><i class="legend-dot" style="background:<?= $color ?>"></i><?= e($label) ?></span><strong><?= nf($n) ?> · <?= pct($n, $total) ?></strong></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
</section>

<section class="card" id="reports-panel">
  <h3><?= $fixedKategori ? 'Aduan ' . e($kategoriMap[$fixedKategori]['label_kategori']) : ($manage ? 'Daftar Aduan' : ($progress ? 'Daftar Progress Aduan' : 'Log Pengaduan Lintas Sektor')) ?></h3>

  <?php if ($manage): ?>
    <div class="toolbar">
      <a class="btn primary" href="?page=form-aduan">+ Tambah Aduan</a>
      <form method="get" class="field">
        <input type="hidden" name="page" value="kelola-aduan">
        <label for="arsip">Daftar aduan</label>
        <select id="arsip" name="arsip" data-autosubmit>
          <option value="0">Aduan aktif</option><option value="1" <?= $arsip ? 'selected' : '' ?>>Aduan diarsipkan</option>
        </select>
      </form>
    </div>
  <?php endif; ?>

  <form method="get" class="filters">
    <input type="hidden" name="page" value="<?= e($page) ?>">
    <?php if ($arsip): ?><input type="hidden" name="arsip" value="1"><?php endif; ?>
    <div class="field"><label for="q">Cari aduan</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ID, pengadu, topik, lokasi…"></div>
    <div class="field"><label for="status">Status</label>
      <select id="status" name="status"><option value="">Semua status</option>
        <?php foreach (STATUSES as $s): ?><option <?= $s === $fStatus ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select></div>
    <?php if (!$fixedKategori): ?>
    <div class="field"><label for="kategori">Kategori</label>
      <select id="kategori" name="kategori"><option value="">Semua kategori</option>
        <?php foreach ($kategoriList as $k): ?><option value="<?= e($k['id_kategori']) ?>" <?= $k['id_kategori'] === $fKat ? 'selected' : '' ?>><?= e($k['label_kategori']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="field actions"><button class="btn primary" type="submit">Terapkan</button><a class="btn" href="?page=<?= e($page) ?>">Reset</a></div>
  </form>

<?php
$count = (int)scalar("SELECT COUNT(*) {$from} WHERE {$whereSql}", $params);
$pages = max(1, (int)ceil($count / PAGE_SIZE));
$hal   = min($hal, $pages);
$offsetRows = ($hal - 1) * PAGE_SIZE;

$list = rows("SELECT a.*, p.nama, p.avatar_tone, k.label_kategori, k.dinas_penanggung_jawab, k.warna_tema,
        COALESCE((SELECT persentase_progres FROM progres_aduan WHERE id_aduan = a.id_aduan ORDER BY id_progres DESC LIMIT 1), 0) AS progres,
        (SELECT url_file FROM media_aduan WHERE id_aduan = a.id_aduan AND tipe_media = 'foto_sebelum' ORDER BY id_media DESC LIMIT 1) AS foto_sebelum,
        (SELECT url_file FROM media_aduan WHERE id_aduan = a.id_aduan AND tipe_media = 'foto_sesudah' ORDER BY id_media DESC LIMIT 1) AS foto_sesudah
    {$from} WHERE {$whereSql} ORDER BY a.tanggal_aduan DESC, a.id_aduan DESC LIMIT " . PAGE_SIZE . " OFFSET {$offsetRows}", $params);

$headings = $progress ? ['Aduan & Pengadu', 'Aduan & Bukti Sebelum', 'Dinas & Lokasi', 'Status & Progress', 'Bukti Setelah Penanganan', 'Tindakan']
          : ($manage ? ['Aduan & Pengadu', 'Waktu Aduan', 'Aduan & Bukti', 'Dinas & Lokasi', 'Status & Progress', 'Tindakan']
          : ['Aduan & Pengadu', 'Aduan & Bukti', 'Dinas & Lokasi', 'Status & Progress']);
?>
  <p class="small muted" style="margin-bottom:12px" role="status"><?= nf($count) ?> aduan sesuai filter dari <?= nf($total) ?> aduan</p>

  <div class="table-wrap">
    <table class="reports-table">
      <thead><tr><?php foreach ($headings as $h): ?><th scope="col"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($list as $r): ?>
        <tr>
          <td>
            <span class="report-id">#<?= e($r['id_aduan']) ?></span>
            <div class="person-name"><?= avatar($r) ?><span class="report-person-name"><?= e($r['nama']) ?></span></div>
          </td>
          <?php if ($manage): ?><td><?= e(tanggal($r['tanggal_aduan'])) ?></td><?php endif; ?>
          <td class="report-topic">
            <span class="category-chip" style="--category-color:<?= e($r['warna_tema']) ?>"><?= e($r['label_kategori']) ?></span>
            <p class="report-topic-text"><strong><?= e($r['topik']) ?></strong></p>
            <p class="report-description"><?= e($r['deskripsi']) ?></p>
            <?php if ($r['foto_sebelum']): ?><figure class="report-photo"><img src="<?= e($r['foto_sebelum']) ?>" alt="Foto aduan" loading="lazy"></figure><?php endif; ?>
          </td>
          <td><p class="agency-name"><?= e($r['dinas_penanggung_jawab']) ?></p><p class="report-location">📍 <?= e($r['lokasi_kejadian']) ?></p></td>
          <td class="report-state"><?= statusBadge($r['status']) ?><?= progressBar((int)$r['progres'], $r['status']) ?></td>
          <?php if ($progress): ?>
            <td><?php if ($r['foto_sesudah']): ?><figure class="report-photo"><img src="<?= e($r['foto_sesudah']) ?>" alt="Foto setelah penanganan" loading="lazy"></figure>
                <?php else: ?><div class="after-empty"><p>Belum ada foto setelah penanganan.</p></div><?php endif; ?></td>
            <td><a class="btn" href="?page=form-progress&id=<?= e($r['id_aduan']) ?>">Update Progress</a></td>
          <?php elseif ($manage): ?>
            <td><div class="row-actions">
              <a class="btn" href="?page=form-aduan&id=<?= e($r['id_aduan']) ?>">Edit</a>
              <form method="post" data-confirm="<?= $arsip ? 'Pulihkan' : 'Arsipkan' ?> aduan ini?">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="toggle_arsip">
                <input type="hidden" name="id_aduan" value="<?= e($r['id_aduan']) ?>">
                <button class="btn danger" type="submit"><?= $arsip ? 'Pulihkan' : 'Arsipkan' ?></button>
              </form></div></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="<?= count($headings) ?>" class="empty">Tidak ada aduan yang sesuai. Ubah pencarian atau reset filter.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pagination">
    <p class="small muted"><?= $count ? 'Menampilkan ' . nf($offsetRows + 1) . '–' . nf(min($offsetRows + PAGE_SIZE, $count)) . ' dari ' . nf($count) . ' aduan' : 'Menampilkan 0 aduan' ?></p>
    <div class="pagination-controls">
      <?php if ($hal > 1): ?><a class="btn" href="<?= e(url(['p' => $hal - 1])) ?>">← Sebelumnya</a><?php endif; ?>
      <span class="small"><?= $hal ?> / <?= $pages ?></span>
      <?php if ($hal < $pages): ?><a class="btn" href="<?= e(url(['p' => $hal + 1])) ?>">Berikutnya →</a><?php endif; ?>
    </div>
  </div>
