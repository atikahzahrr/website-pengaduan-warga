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

$pelaporRows = []; // cache profil pengadu untuk dialog detail
$surveyAvg = (float)scalar('SELECT COALESCE(AVG(skor), 0) FROM survei_kepuasan');
$surveyN   = (int)scalar('SELECT COUNT(*) FROM survei_kepuasan');
$agencyShort = '';
if ($fixedKategori) {
    $agency = $kategoriMap[$fixedKategori]['dinas_penanggung_jawab'];
    $agencyShort = preg_match('/\(([^)]+)\)/', $agency, $mm) ? $mm[1] : $agency;
}

/* ---- Data grafik tren (mengikuti filter tabel) ---- */
$trendRows = [];
foreach (rows("SELECT SUBSTR(a.tanggal_aduan, 1, 10) AS date, COUNT(*) AS total, SUM(CASE WHEN a.status = 'Selesai' THEN 1 ELSE 0 END) AS done,
        SUM(COALESCE((SELECT persentase_progres FROM progres_aduan WHERE id_aduan = a.id_aduan ORDER BY id_progres DESC LIMIT 1), 0)) AS progress_sum
    {$from} WHERE {$whereSql} GROUP BY SUBSTR(a.tanggal_aduan, 1, 10)", $params) as $t) {
    $trendRows[$t['date']] = ['date' => $t['date'], 'total' => (int)$t['total'], 'done' => (int)$t['done'], 'progressSum' => (int)$t['progress_sum'], 'categories' => new stdClass()];
}
foreach (rows("SELECT SUBSTR(a.tanggal_aduan, 1, 10) AS date, a.id_kategori AS kat, COUNT(*) AS n {$from} WHERE {$whereSql} GROUP BY SUBSTR(a.tanggal_aduan, 1, 10), a.id_kategori", $params) as $t) {
    $trendRows[$t['date']]['categories'] = (object)array_merge((array)$trendRows[$t['date']]['categories'], [$t['kat'] => (int)$t['n']]);
}
ksort($trendRows);
$trendConfig = trendConfig($page, array_values($trendRows), $kategoriList, $fixedKategori);
?>
<div class="stats">
  <?= statCard('Total Aduan Masuk', nf($total), 'Periode simulasi') ?>
  <?= statCard('Selesai Ditangani', nf($done), pct($done, $total) . ' selesai', 'green') ?>
  <?= statCard('Dalam Proses Dinas', nf($total - $done), nf($verify) . ' menunggu verifikasi', 'blue') ?>
  <?= $fixedKategori
      ? statCard('Dinas Penanggung Jawab', $agencyShort, 'Monitoring sektor', 'amber')
      : statCard('Indeks Kepuasan', number_format($surveyAvg, 1, ',', '.') . ' / 5', nf($surveyN) . ' respons survei', 'amber') ?>
</div>

<?php if (!in_array($page, ['laporan', 'kelola-aduan', 'progress'], true)): ?>
<?php
$catCounts = array_column(rows("SELECT id_kategori, COUNT(*) n FROM aduan a WHERE {$base} GROUP BY id_kategori", $baseParams), 'n', 'id_kategori');
$circ = 2 * M_PI * 48; $offset = 0;
$segments = [['Selesai', '#10b981', $done], ['Diproses', '#2563eb', $work], ['Verifikasi', '#f59e0b', $verify]];
?>
<div id="overview" class="two-cols">
  <div>
    <?php if ($fixedKategori): $k = $kategoriMap[$fixedKategori];
      $topics = array_column(rows('SELECT DISTINCT topik FROM aduan WHERE id_kategori = ? ORDER BY topik LIMIT 4', [$fixedKategori]), 'topik'); ?>
      <h3>Fokus Monitoring</h3>
      <p class="muted" style="margin-top:12px"><?= e($k['dinas_penanggung_jawab']) ?></p>
      <p class="small muted" style="margin-top:8px"><?= e(implode(' · ', $topics)) ?></p>
    <?php else: ?>
      <h3>Distribusi Kategori Aduan</h3>
      <?php foreach ($kategoriList as $k) echo barRow($k['label_kategori'], (int)($catCounts[$k['id_kategori']] ?? 0), $total, $k['warna_tema']); ?>
    <?php endif; ?>
  </div>
  <div>
    <h3>Komposisi Status Penanganan</h3>
    <div class="donut-layout">
      <svg class="donut-svg" viewBox="0 0 160 160" role="img" aria-label="Komposisi status penanganan: <?= e(implode(', ', array_map(fn($s) => $s[0] . ' ' . nf($s[2]), $segments))) ?>">
        <circle cx="80" cy="80" r="48" fill="none" stroke="#f1f5f9" stroke-width="17"/>
        <?php foreach ($segments as [$label, $color, $n]): $len = $total ? $n / $total * $circ : 0; ?>
          <circle cx="80" cy="80" r="48" fill="none" stroke="<?= $color ?>" stroke-width="17"
                  stroke-dasharray="<?= round($len, 2) ?> <?= round($circ - $len, 2) ?>" stroke-dashoffset="<?= round(-$offset, 2) ?>" transform="rotate(-90 80 80)"><title><?= e($label) ?>: <?= nf($n) ?> (<?= pct($n, $total) ?>)</title></circle>
          <?php $offset += $len; endforeach; ?>
        <text x="80" y="79" text-anchor="middle" font-size="20" font-weight="800" fill="#172554"><?= nf($total) ?></text>
        <text x="80" y="97" text-anchor="middle" font-size="10" fill="#64748b">TOTAL ADUAN</text>
      </svg>
      <div class="donut-legend">
        <?php foreach ($segments as [$label, $color, $n]): ?>
          <div class="donut-row"><span><i class="legend-dot" style="background:<?= $color ?>" aria-hidden="true"></i><?= e($label) ?></span><strong><?= pct($n, $total) ?></strong></div>
          <p class="small muted"><?= nf($n) ?> aduan</p>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<div id="trend-panel" class="trend-panel"></div>
</section>

<section class="card" id="reports-panel">
  <h3 id="reports-title"><?= $fixedKategori ? 'Aduan ' . e($kategoriMap[$fixedKategori]['label_kategori']) : ($manage ? 'Daftar Aduan' : ($progress ? 'Daftar Progress Aduan' : 'Log Pengaduan Lintas Sektor')) ?></h3>

  <?php if ($manage): ?>
    <div id="manage-toolbar" class="toolbar">
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
    <div class="field"><label for="search">Cari aduan</label><input id="search" type="search" name="q" value="<?= e($q) ?>" placeholder="ID, pengadu, topik, lokasi…"></div>
    <div class="field"><label for="status-filter">Status</label>
      <select id="status-filter" name="status" data-autosubmit><option value="">Semua status</option>
        <?php foreach (STATUSES as $s): ?><option <?= $s === $fStatus ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select></div>
    <?php if (!$fixedKategori): ?>
    <div class="field" id="category-field"><label for="category-filter">Kategori</label>
      <select id="category-filter" name="kategori" data-autosubmit><option value="">Semua kategori</option>
        <?php foreach ($kategoriList as $k): ?><option value="<?= e($k['id_kategori']) ?>" <?= $k['id_kategori'] === $fKat ? 'selected' : '' ?>><?= e($k['label_kategori']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <a class="btn" id="reset" href="?page=<?= e($page) ?><?= $arsip ? '&arsip=1' : '' ?>">Reset</a>
  </form>

<?php
$count = (int)scalar("SELECT COUNT(*) {$from} WHERE {$whereSql}", $params);
$pages = max(1, (int)ceil($count / PAGE_SIZE));
$hal   = min($hal, $pages);
$offsetRows = ($hal - 1) * PAGE_SIZE;

$list = rows("SELECT a.*, p.nama, p.avatar_tone, p.status_aktif, p.nik, p.alamat, p.latitude, p.longitude, k.label_kategori, k.dinas_penanggung_jawab, k.warna_tema,
        COALESCE((SELECT persentase_progres FROM progres_aduan WHERE id_aduan = a.id_aduan ORDER BY id_progres DESC LIMIT 1), 0) AS progres,
        (SELECT url_file FROM media_aduan WHERE id_aduan = a.id_aduan AND tipe_media = 'foto_sebelum' ORDER BY id_media DESC LIMIT 1) AS foto_sebelum,
        (SELECT url_file FROM media_aduan WHERE id_aduan = a.id_aduan AND tipe_media = 'foto_sesudah' ORDER BY id_media DESC LIMIT 1) AS foto_sesudah
    {$from} WHERE {$whereSql} ORDER BY a.tanggal_aduan DESC, a.id_aduan DESC LIMIT " . PAGE_SIZE . " OFFSET {$offsetRows}", $params);

if ($progress) { $kind = 'progress-table'; $widths = [18, 27, 18, 15, 22];
    $headings = ['Aduan & Pengadu', 'Aduan / Foto / Video Sebelum', 'Dinas & Titik Lokasi', 'Status & Progress', 'Foto / Video Setelah Penanganan']; }
elseif ($manage) { $kind = 'management-table'; $widths = [20, 10, 25, 19, 15, 11];
    $headings = ['Aduan & Pengadu', 'Waktu Kejadian', 'Aduan & Bukti', 'Dinas & Titik Lokasi', 'Status & Progress', 'Tindakan']; }
else { $kind = 'standard-table'; $widths = [23, 33, 25, 19];
    $headings = ['Aduan & Pengadu', 'Aduan & Bukti', 'Dinas & Titik Lokasi', 'Status & Progress']; }
$svgEdit = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m16 3 5 5-10 10-5 1 1-5zM14 5l5 5"/></svg>';
$svgProg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3v18h18M7 16l4-5 4 2 6-8"/></svg>';
$svgArch = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 8h16v13H4zM3 3h18v5H3zM9 12h6"/></svg>';
?>
  <p id="results-count" class="small muted" role="status" style="margin-bottom:12px"><?= nf($count) ?> aduan sesuai filter dari <?= nf($total) ?> aduan</p>

  <div class="table-wrap reports-wrap">
    <table id="reports-table" class="reports-table <?= $kind ?>">
      <colgroup><?php foreach ($widths as $w): ?><col style="width:<?= $w ?>%"><?php endforeach; ?></colgroup>
      <caption class="small muted" style="text-align:left;padding:12px">Data simulasi pengaduan · klik ID untuk melihat detail</caption>
      <thead><tr><?php foreach ($headings as $h): ?><th scope="col"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($list as $r): ?>
        <tr>
          <td class="report-identity">
            <button class="report-id" type="button" data-report="<?= e($r['id_aduan']) ?>" aria-label="Detail aduan <?= e($r['id_aduan']) ?>">#<?= e($r['id_aduan']) ?></button>
            <div class="report-person"><?= avatar($r, 'report-avatar') ?><div><p class="report-person-name"><?= e($r['nama']) ?></p><p class="small muted"><?= e(idPelapor((int)$r['id_pelapor'])) ?></p></div></div>
            <?php if (!$manage): ?><p class="report-date small muted"><?= e(tanggalPanjang($r['tanggal_aduan'])) ?></p><?php endif; ?>
          </td>
          <?php if ($manage): ?>
            <td class="report-date-cell"><time datetime="<?= e(date('c', strtotime($r['tanggal_aduan']))) ?>"><?= e(tanggalPanjang($r['tanggal_aduan'], false)) ?></time><p class="event-clock"><?= e(date('H.i', strtotime($r['tanggal_aduan']))) ?> WIB</p></td>
          <?php endif; ?>
          <td class="report-topic">
            <span class="category-chip" style="--category-color:<?= e($r['warna_tema']) ?>"><?= e($r['label_kategori']) ?></span>
            <p class="report-topic-text"><strong><?= e($r['topik']) ?></strong></p>
            <p class="report-description"><?= e($r['deskripsi']) ?></p>
            <?= reportPhotoHtml($r) ?>
          </td>
          <td class="report-agency"><p class="agency-name"><?= e($r['dinas_penanggung_jawab']) ?></p><p class="report-location"><?= svgPin() ?><span><?= e($r['lokasi_kejadian']) ?></span></p></td>
          <td class="report-state"><?= statusBadge($r['status']) ?><?= progressBar((int)$r['progres'], $r['status'], $r['id_aduan']) ?>
            <?php if ($progress): ?><div class="row-actions"><a class="btn" href="?page=form-progress&id=<?= e($r['id_aduan']) ?>">Update Progress</a></div><?php endif; ?></td>
          <?php if ($manage): ?>
            <td class="report-action-cell"><div class="row-actions report-actions">
              <a class="btn" href="?page=form-aduan&id=<?= e($r['id_aduan']) ?>" aria-label="Edit aduan <?= e($r['id_aduan']) ?>"><?= $svgEdit ?><span>Edit</span></a>
              <a class="btn" href="?page=form-progress&id=<?= e($r['id_aduan']) ?>" aria-label="Update progress aduan <?= e($r['id_aduan']) ?>"><?= $svgProg ?><span>Progress</span></a>
              <form method="post" data-confirm="<?= $arsip ? 'Pulihkan' : 'Arsipkan' ?> aduan ini?">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="toggle_arsip">
                <input type="hidden" name="id_aduan" value="<?= e($r['id_aduan']) ?>">
                <button class="btn <?= $arsip ? '' : 'danger' ?>" type="submit" aria-label="<?= $arsip ? 'Pulihkan' : 'Arsipkan' ?> aduan <?= e($r['id_aduan']) ?>"><?= $svgArch ?><span><?= $arsip ? 'Pulihkan' : 'Arsipkan' ?></span></button>
              </form></div></td>
          <?php elseif ($progress): ?>
            <td class="report-after-cell"><span class="comparison-label after">SESUDAH</span><?= afterMediaHtml($r) ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="<?= count($headings) ?>" class="empty">Tidak ada aduan yang sesuai. Ubah pencarian atau reset filter.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pagination">
    <p id="page-info" class="small muted"><?= $count ? 'Menampilkan ' . nf($offsetRows + 1) . '–' . nf(min($offsetRows + PAGE_SIZE, $count)) . ' dari ' . nf($count) . ' aduan' : 'Menampilkan 0 aduan' ?></p>
    <div class="pagination-controls">
      <?php if ($hal > 1): ?><a class="btn" href="<?= e(url(['p' => $hal - 1])) ?>">← Sebelumnya</a><?php else: ?><button class="btn" type="button" disabled>← Sebelumnya</button><?php endif; ?>
      <span id="page-number" class="small"><?= $hal ?> / <?= $pages ?></span>
      <?php if ($hal < $pages): ?><a class="btn" href="<?= e(url(['p' => $hal + 1])) ?>">Berikutnya →</a><?php else: ?><button class="btn" type="button" disabled>Berikutnya →</button><?php endif; ?>
    </div>
  </div>

  <?php /* Isi dialog detail aduan (dibuka lewat klik pada ID atau foto) */ ?>
  <?php foreach ($list as $r):
      $hist = rows('SELECT * FROM progres_aduan WHERE id_aduan = ? ORDER BY id_progres DESC', [$r['id_aduan']]); ?>
    <div id="detail-<?= e($r['id_aduan']) ?>" data-title="Detail Aduan #<?= e($r['id_aduan']) ?>" hidden>
      <div class="profile-banner"><span><?= avatar($r, 'large') ?></span>
        <div><strong><?= e($r['nama']) ?></strong><p class="small muted"><?= e(idPelapor((int)$r['id_pelapor'])) ?> · <?= e(genderOf($r['nama'])) ?></p>
          <span class="status <?= $r['status_aktif'] ? 'done' : 'verify' ?>"><?= $r['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span></div></div>
      <dl class="detail-list">
        <dt>Pengadu</dt><dd><?= e($r['nama']) ?> (<?= e(idPelapor((int)$r['id_pelapor'])) ?>)</dd>
        <dt>NIK (dummy)</dt><dd class="ktp-number"><?= e($r['nik']) ?></dd>
        <dt>Alamat pengadu</dt><dd><?= e($r['alamat']) ?></dd>
        <dt>Lokasi pengadu</dt><dd><?= pointLink((float)$r['latitude'], (float)$r['longitude']) ?></dd>
        <dt>Tanggal diterima</dt><dd><?= e(tanggalPanjang($r['tanggal_aduan'])) ?></dd>
        <dt>Kategori</dt><dd><?= e($r['label_kategori']) ?></dd>
        <dt>Topik</dt><dd><?= e($r['topik']) ?></dd>
        <dt>Uraian aduan</dt><dd class="evidence-description"><?= e($r['deskripsi']) ?></dd>
        <dt>Lokasi kejadian</dt><dd><?= e($r['lokasi_kejadian']) ?></dd>
        <dt>Penanggung jawab</dt><dd><?= e($r['dinas_penanggung_jawab']) ?></dd>
        <dt>Status</dt><dd><?= statusBadge($r['status']) ?><?= $r['is_arsip'] ? ' · Diarsipkan' : '' ?></dd>
      </dl>
      <div class="comparison-heading"><h3>Perbandingan Before &amp; After</h3><p class="small muted">Lampiran awal dan bukti setelah proses penanganan disimpan terpisah.</p></div>
      <div class="before-after-grid">
        <section class="comparison-panel"><span class="comparison-label before">SEBELUM PENANGANAN</span><?= reportPhotoHtml($r, true) ?></section>
        <section class="comparison-panel"><span class="comparison-label after">SETELAH PENANGANAN</span><?= afterMediaHtml($r, true) ?></section>
      </div>
      <?= progressBar((int)$r['progres'], $r['status'], $r['id_aduan']) ?>
      <div class="form-actions"><a class="btn" href="?page=form-aduan&id=<?= e($r['id_aduan']) ?>">Edit Aduan</a><a class="btn primary" href="?page=form-progress&id=<?= e($r['id_aduan']) ?>">Update Progress</a></div>
      <div class="timeline"><h3>Riwayat Tindak Lanjut</h3>
        <?php foreach ($hist as $h): ?>
          <div class="timeline-entry"><p class="small muted"><?= e(tanggal($h['updated_at'])) ?></p><?= statusBadge($h['status_tahapan']) ?> <span class="small"><?= (int)$h['persentase_progres'] ?>%</span><p><?= e($h['catatan_petugas']) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
