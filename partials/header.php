<?php
$navLabels = [
    'ringkasan' => 'Ringkasan', 'distribusi' => 'Distribusi Aduan', 'laporan' => 'Log Pengaduan',
    'kelola-aduan' => 'Kelola Aduan', 'kelola-pengadu' => 'Kelola Pengadu', 'progress' => 'Progress Aduan',
    'infrastruktur' => 'Infrastruktur', 'parkir' => 'Parkir & Lalu Lintas', 'pendidikan' => 'Pendidikan',
    'kebersihan' => 'Kebersihan', 'cctv' => 'CCTV Publik', 'kepuasan' => 'Kepuasan Layanan',
];
$groups = [];
foreach ($views as $key => $v) {
    if ($v[5] !== '') $groups[$v[5]][$key] = $v;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> · Dashboard Aduan Masyarakat</title>
<link rel="stylesheet" href="assets/style.css">
<script>/* Terapkan tema lebih awal agar tidak berkedip */
(function(){try{var p=localStorage.getItem('aduan-dashboard-theme')||'auto';
document.documentElement.dataset.theme=p==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;}catch(e){}})();</script>
</head>
<body>
<div class="shell">
  <header class="page-header">
    <div>
      <h1>Dashboard Aduan Masyarakat</h1>
      <p class="muted">Pusat kendali layanan publik &amp; pengaduan masyarakat</p>
    </div>
    <div class="header-actions">
      <label class="theme-control">Tema
        <select id="theme-select"><option value="auto">Otomatis</option><option value="light">Terang</option><option value="dark">Gelap</option></select>
      </label>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <div class="brand">▦ &nbsp; Aduan Masyarakat<p class="small muted">Dashboard Eksekutif</p></div>
      <nav aria-label="Menu utama dashboard">
        <?php $first = true; foreach ($groups as $label => $items): ?>
          <?php if (!$first): ?><div class="sidebar-divider"></div><?php endif; $first = false; ?>
          <p class="nav-label"><?= e($label) ?></p>
          <div class="nav">
            <?php foreach ($items as $key => $v): ?>
              <a href="?page=<?= e($key) ?>" <?= $key === $page ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" style="--icon-tone:<?= e($v[4]) ?>"><?= e($v[3]) ?></span><?= e($navLabels[$key] ?? $v[0]) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
    </aside>

    <main id="main-content">
      <?php if ($msg = flash()): ?><p class="notice" role="status"><?= e($msg) ?></p><?php endif; ?>
      <section class="card">
        <div class="intro">
          <div>
            <span class="pill">PUSAT KOMANDO EKSEKUTIF</span>
            <h2><?= e($title) ?></h2>
            <p class="muted small"><?= e($description) ?></p>
          </div>
          <div class="small muted">Diperbarui: <?= e(tanggal(date('Y-m-d H:i:s'))) ?></div>
        </div>
