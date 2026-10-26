<?php
$navLabels = [
    'ringkasan' => 'Ringkasan', 'distribusi' => 'Distribusi Aduan', 'laporan' => 'Log Pengaduan',
    'kelola-aduan' => 'Kelola Aduan', 'kelola-pengadu' => 'Kelola Pengadu', 'progress' => 'Progress Aduan',
    'infrastruktur' => 'Infrastruktur', 'parkir' => 'Parkir & Lalu Lintas', 'pendidikan' => 'Pendidikan',
    'kebersihan' => 'Kebersihan', 'cctv' => 'CCTV Publik', 'kepuasan' => 'Kepuasan Layanan',
    'admin' => 'Kelola Admin', 'akun' => 'Profil & Akun',
];
$user = currentUser();
$groups = [];
foreach ($views as $key => $v) {
    if ($v[5] === '') continue;
    if ($key === 'admin' && !isSuperadmin()) continue;   // khusus superadmin
    $groups[$v[5]][$key] = $v;
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
        <select id="theme-select" aria-label="Pilih tema dashboard"><option value="auto">Otomatis</option><option value="light">Terang</option><option value="dark">Gelap</option></select>
      </label>
      <?php if ($user): ?>
        <div class="user-chip">
          <span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?></span>
          <span class="user-meta"><strong><?= e($user['nama']) ?></strong><span class="small muted"><?= e(roleLabel($user['role'])) ?></span></span>
        </div>
        <form method="post" action="index.php" class="logout-form">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action" value="logout">
          <button class="btn" type="submit">Keluar</button>
        </form>
      <?php endif; ?>
      <span class="dummy">Mode simulasi · Data dummy</span>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <div class="brand">▦ &nbsp; Aduan Masyarakat<p class="small muted">Dashboard Eksekutif</p></div>
      <nav aria-label="Menu utama dashboard">
        <?php $first = true; foreach ($groups as $label => $items): ?>
          <?php if (!$first): ?><div class="sidebar-divider" aria-hidden="true"></div><?php endif; $first = false; ?>
          <p class="nav-label"><?= e($label) ?></p>
          <div class="nav">
            <?php foreach ($items as $key => $v): ?>
              <a href="?page=<?= e($key) ?>" <?= $key === $page ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true" style="--icon-tone:<?= e($v[4]) ?>"><?= icon($key) ?></span><?= e($navLabels[$key] ?? $v[0]) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
      <p class="small muted" style="margin:20px 10px 4px">Dataset awal: September 2026</p>
    </aside>

    <main id="main-content" tabindex="-1">
      <p id="storage-status" class="small muted" role="status"><?= ($msg = flash()) ? e($msg) : '' ?></p>
      <section class="card">
        <div class="intro">
          <div>
            <span class="pill">PUSAT KOMANDO EKSEKUTIF</span>
            <h2 id="view-title"><?= e($title) ?></h2>
            <p id="view-description" class="muted small"><?= e($description) ?></p>
          </div>
          <div>
            <a id="refresh" class="btn" href="<?= e(url()) ?>">↻ Perbarui Tampilan</a>
            <p id="updated" class="small muted" role="status" style="margin-top:8px">Tampilan diperbarui: <?= e(tanggal(date('Y-m-d H:i:s'))) ?></p>
          </div>
        </div>
