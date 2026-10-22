<?php
$cams   = rows('SELECT * FROM cctv_publik ORDER BY id_cctv');
$online = count(array_filter($cams, fn($c) => $c['status_online']));
$padat  = count(array_filter($cams, fn($c) => $c['kondisi_arus'] === 'Padat'));

/* Data tren ketersediaan: fixture simulasi 30 hari (sama seperti dashboard asli). */
$trendRows = [];
for ($i = 0; $i < 30; $i++) {
    $on = $i === 29 ? $online : ($i % 9 === 0 ? 2 : ($i % 5 === 0 ? 3 : 4));
    $trendRows[] = ['date' => sprintf('2026-09-%02d', $i + 1), 'online' => min($on, count($cams)), 'total' => count($cams)];
}
$trendConfig = trendConfig('cctv', $trendRows, [], null, count($cams));

/** Pratinjau video contoh 3 detik per kamera. */
function cctvPreview(array $c): string
{
    $slug = strtolower($c['id_cctv']);
    if (!file_exists(__DIR__ . "/../assets/video/{$slug}.mp4")) return '<p class="notice">Contoh video belum tersedia.</p>';
    $poster = file_exists(__DIR__ . "/../assets/img/{$slug}-poster.jpg") ? ' poster="assets/img/' . e($slug) . '-poster.jpg"' : '';
    return '<figure class="cctv-preview"><video data-cctv="' . e($c['id_cctv']) . '" controls muted loop playsinline preload="none"' . $poster
         . ' aria-label="Contoh animasi CCTV ' . e($c['nama_kamera']) . ', 3 detik"><source src="assets/video/' . e($slug) . '.mp4" type="video/mp4">Browser ini tidak mendukung video MP4.</video>'
         . '<figcaption><span class="cctv-demo-badge">CONTOH ANIMASI · 3 DETIK</span><span>'
         . ($c['status_online'] ? 'Putar simulasi area kamera' : 'Contoh rekaman · kamera offline') . '</span></figcaption></figure>';
}
?>
<div class="stats">
  <?= statCard('Total Kamera', nf(count($cams)), 'Titik pemantauan') ?>
  <?= statCard('Kamera Online', nf($online), 'Status simulasi', 'green') ?>
  <?= statCard('Kamera Offline', nf(count($cams) - $online), 'Perlu pengecekan', 'amber') ?>
  <?= statCard('Lokasi Padat', nf($padat), 'Pantauan simulasi', 'blue') ?>
</div>
<div id="trend-panel" class="trend-panel"></div>
<div id="special">
  <div class="camera-grid">
    <?php foreach ($cams as $c): ?>
      <article class="camera">
        <?= cctvPreview($c) ?>
        <div class="camera-info">
          <div class="top"><h3><?= e($c['nama_kamera']) ?></h3><span class="status <?= $c['status_online'] ? 'done' : 'verify' ?>"><?= $c['status_online'] ? 'Online' : 'Offline' ?></span></div>
          <p class="small muted"><?= e($c['id_cctv']) ?> · <?= e($c['lokasi_detail']) ?></p>
          <p class="small">Kondisi: <strong><?= e($c['kondisi_arus']) ?></strong></p>
          <button class="btn" data-camera="<?= e($c['id_cctv']) ?>" type="button">Lihat Detail Kamera</button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="notice">Setiap titik memiliki contoh animasi video 3 detik sesuai area pemantauan. Gunakan tombol putar; video berulang otomatis setelah diputar. Status kamera adalah data dummy, bukan koneksi CCTV langsung.</p>
</div>

<?php /* Isi dialog detail kamera */ ?>
<?php foreach ($cams as $c): ?>
  <div id="camera-<?= e($c['id_cctv']) ?>" data-title="Detail Kamera <?= e($c['id_cctv']) ?>" hidden>
    <?= cctvPreview($c) ?>
    <dl class="detail-list" style="margin-top:20px">
      <dt>Lokasi</dt><dd><?= e($c['lokasi_detail']) ?></dd>
      <dt>Pengelola</dt><dd><?= e($c['dinas_pengampu']) ?></dd>
      <dt>Status</dt><dd><?= $c['status_online'] ? 'Online' : 'Offline' ?></dd>
      <dt>Kondisi</dt><dd><?= e($c['kondisi_arus']) ?></dd>
    </dl>
    <p class="notice">Video ini adalah contoh animasi 3 detik, bukan rekaman lokasi asli atau siaran langsung. <?= $c['status_online'] ? 'Status online merupakan simulasi.' : 'Kamera berstatus offline; contoh video tetap tersedia untuk demonstrasi.' ?></p>
  </div>
<?php endforeach; ?>
