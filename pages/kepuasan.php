<?php
$dist  = rows('SELECT skor, label_kepuasan, emoji, COUNT(*) AS n FROM survei_kepuasan GROUP BY skor, label_kepuasan, emoji ORDER BY skor');
$total = array_sum(array_column($dist, 'n'));
$sum   = array_sum(array_map(fn($d) => $d['skor'] * $d['n'], $dist));
$puas  = array_sum(array_map(fn($d) => $d['skor'] >= 4 ? $d['n'] : 0, $dist));
?>
<div class="stats">
  <?= statCard('Total Respons', nf($total), 'Seluruh periode') ?>
  <?= statCard('Rata-rata Kepuasan', number_format($total ? $sum / $total : 0, 1, ',', '.') . ' / 5', 'Skala 1–5', 'amber') ?>
  <?= statCard('Puas & Sangat Puas', pct($puas, $total), nf($puas) . ' respons', 'green') ?>
  <?= statCard('Perlu Perhatian', nf($total - $puas), 'Kurang / cukup puas', 'blue') ?>
</div>
<h3>Distribusi Respons Survei</h3>
<div class="survey-list">
  <?php foreach ($dist as $d): ?>
    <div class="survey-option"><span class="emoji"><?= e($d['emoji']) ?></span><p><?= e($d['label_kepuasan']) ?></p>
      <strong><?= nf((int)$d['n']) ?></strong><span class="small muted"><?= pct((int)$d['n'], $total) ?></span></div>
  <?php endforeach; ?>
</div>
