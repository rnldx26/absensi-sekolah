<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();
$today = date('Y-m-d');
$st = db()->prepare("SELECT u.nama, u.nip, u.jabatan, m.waktu AS masuk, m.status, m.jarak, p.waktu AS pulang
  FROM users u
  LEFT JOIN attendance m ON m.user_id = u.id AND m.tanggal = ? AND m.tipe = 'masuk'
  LEFT JOIN attendance p ON p.user_id = u.id AND p.tanggal = ? AND p.tipe = 'pulang'
  WHERE u.aktif = 1 ORDER BY u.nama");
$st->execute([$today, $today]);
$rows = $st->fetchAll();
$total = count($rows); $hadir = $telat = 0;
foreach ($rows as $r) { if ($r['masuk']) { $hadir++; if ($r['status'] === 'terlambat') $telat++; } }
$blok = (int)db()->query("SELECT COUNT(*) FROM logs WHERE event IN ('mock_location','device_mismatch') AND DATE(created_at) = CURDATE()")->fetchColumn();
page_head('Dashboard');
?>
<div class="stats">
  <div><b><?= $total ?></b>Pengguna aktif</div>
  <div><b><?= $hadir ?></b>Sudah absen masuk</div>
  <div><b><?= $telat ?></b>Terlambat</div>
  <div><b><?= $total - $hadir ?></b>Belum absen</div>
  <div><b><?= $blok ?></b>Percobaan diblokir hari ini</div>
</div>
<div class="card scroll"><h3 style="margin-top:0">Kehadiran <?= h(date('d/m/Y')) ?></h3>
<table><tr><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['nama']) ?></td><td><?= h($r['nip']) ?></td><td><?= h($r['jabatan']) ?></td>
<td><?= $r['masuk'] ? h(date('H:i', strtotime($r['masuk']))) : '-' ?></td>
<td><?= $r['pulang'] ? h(date('H:i', strtotime($r['pulang']))) : '-' ?></td>
<td><?php if (!$r['masuk']) echo '<span class="tag bad">belum absen</span>';
 else echo '<span class="tag ' . ($r['status'] === 'terlambat' ? 'warn' : 'ok') . '">' . h($r['status']) . '</span>'; ?></td></tr>
<?php endforeach; ?></table></div>
<?php page_foot();
