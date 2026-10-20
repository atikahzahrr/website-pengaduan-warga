<?php
$cams   = rows('SELECT * FROM cctv_publik ORDER BY id_cctv');
$online = count(array_filter($cams, fn($c) => $c['status_online']));
$padat  = count(array_filter($cams, fn($c) => $c['kondisi_arus'] === 'Padat'));
?>
<div class="stats">
  <?= statCard('Total Kamera', nf(count($cams)), 'Titik pemantauan') ?>
  <?= statCard('Kamera Online', nf($online), 'Status terkini', 'green') ?>
  <?= statCard('Kamera Offline', nf(count($cams) - $online), 'Perlu pengecekan', 'amber') ?>
  <?= statCard('Lokasi Padat', nf($padat), 'Arus lalu lintas padat', 'blue') ?>
</div>
<div class="camera-grid">
  <?php foreach ($cams as $c): ?>
    <article class="camera">
      <div class="camera-scene <?= $c['status_online'] ? '' : 'offline' ?>"><span><?= $c['status_online'] ? '● LIVE · ' : '○ OFFLINE · ' ?><?= e($c['id_cctv']) ?></span></div>
      <div class="camera-info">
        <div class="top"><h3><?= e($c['nama_kamera']) ?></h3><span class="status <?= $c['status_online'] ? 'done' : 'verify' ?>"><?= $c['status_online'] ? 'Online' : 'Offline' ?></span></div>
        <p class="small muted"><?= e($c['lokasi_detail']) ?> · <?= e($c['dinas_pengampu']) ?></p>
        <p class="small">Kondisi: <strong><?= e($c['kondisi_arus']) ?></strong></p>
      </div>
    </article>
  <?php endforeach; ?>
</div>
