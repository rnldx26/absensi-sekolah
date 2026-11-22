<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();
$today = date('Y-m-d');
$st = db()->prepare("SELECT u.id, u.nama, u.nip, u.jabatan, u.face_photo, m.id AS mid, m.foto, m.waktu AS masuk, m.status, p.waktu AS pulang
  FROM users u
  LEFT JOIN attendance m ON m.user_id = u.id AND m.tanggal = ? AND m.tipe = 'masuk'
  LEFT JOIN attendance p ON p.user_id = u.id AND p.tanggal = ? AND p.tipe = 'pulang'
  WHERE u.aktif = 1 ORDER BY u.nama");
$st->execute([$today, $today]);
$rows = $st->fetchAll();
$total = count($rows); $hadir = $telat = 0;
foreach ($rows as $r) { if ($r['masuk']) { $hadir++; if ($r['status'] === 'terlambat') $telat++; } }
$blok = (int)db()->query("SELECT COUNT(*) FROM logs WHERE event IN ('mock_location','device_mismatch','wajah_tidak_cocok') AND DATE(created_at) = CURDATE()")->fetchColumn();
page_head('Dashboard');
?>
<div class="stats">
  <div><b><?= $total ?></b>Pengguna aktif</div>
  <div><b><?= $hadir ?></b>Sudah masuk</div>
  <div><b><?= $telat ?></b>Terlambat</div>
  <div><b><?= $total - $hadir ?></b>Belum absen</div>
  <div><b><?= $blok ?></b>Diblokir hari ini</div>
</div>
<div class="card scroll"><h3>kehadiran <?= h(date('d/m/Y')) ?></h3>
<table><tr><th>Foto</th><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= $r['foto'] ? avatar($r['foto'], 'absen', (int)$r['mid']) : avatar($r['face_photo'], 'profile', (int)$r['id']) ?></td>
<td><?= h($r['nama']) ?></td><td><?= h($r['nip']) ?></td><td><?= h($r['jabatan']) ?></td>
<td><?= $r['masuk'] ? h(date('H:i', strtotime($r['masuk']))) : '-' ?></td>
<td><?= $r['pulang'] ? h(date('H:i', strtotime($r['pulang']))) : '-' ?></td>
<td><?php if (!$r['masuk']) echo '<span class="tag bad">belum absen</span>';
 else echo '<span class="tag ' . ($r['status'] === 'terlambat' ? 'warn' : 'ok') . '">' . h($r['status']) . '</span>'; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="dim">Belum ada pengguna.</td></tr>'; ?></table></div>
<?php page_foot();
