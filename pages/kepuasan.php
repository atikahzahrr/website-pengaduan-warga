<?php
$dist  = rows('SELECT skor, label_kepuasan, emoji, COUNT(*) AS n FROM survei_kepuasan GROUP BY skor, label_kepuasan, emoji ORDER BY skor');
$total = array_sum(array_column($dist, 'n'));
$sum   = array_sum(array_map(fn($d) => $d['skor'] * $d['n'], $dist));
$puas  = array_sum(array_map(fn($d) => $d['skor'] >= 4 ? $d['n'] : 0, $dist));

$trendRows = array_map(fn($d) => ['date' => $d['d'], 'responses' => (int)$d['n'], 'scoreSum' => (int)$d['s']],
    rows("SELECT SUBSTR(created_at, 1, 10) AS d, COUNT(*) AS n, SUM(skor) AS s FROM survei_kepuasan GROUP BY SUBSTR(created_at, 1, 10) ORDER BY d"));
$trendConfig = trendConfig('kepuasan', $trendRows);
?>
<div class="stats">
  <?= statCard('Total Respons', nf($total), 'Seluruh periode') ?>
  <?= statCard('Rata-rata Kepuasan', number_format($total ? $sum / $total : 0, 1, ',', '.') . ' / 5', 'Skala respons 1–5', 'amber') ?>
  <?= statCard('Puas & Sangat Puas', pct($puas, $total), nf($puas) . ' respons', 'green') ?>
  <?= statCard('Perlu Perhatian', nf($total - $puas), 'Kurang / cukup puas', 'blue') ?>
</div>
<div id="trend-panel" class="trend-panel"></div>
<div id="special">
  <h3>Distribusi Respons Survei</h3>
  <div class="survey-list">
    <?php foreach ($dist as $d): ?>
      <div class="survey-option"><span class="emoji" aria-hidden="true"><?= e($d['emoji']) ?></span><p><?= e($d['label_kepuasan']) ?></p>
        <strong><?= nf((int)$d['n']) ?></strong><p class="small muted"><?= pct((int)$d['n'], $total) ?> · Skor <?= (int)$d['skor'] ?></p></div>
    <?php endforeach; ?>
  </div>
  <p class="notice">Indeks kepuasan dihitung dari jumlah dan skor respons dummy. Angka ini bukan hasil survei warga yang sebenarnya.</p>
</div>
