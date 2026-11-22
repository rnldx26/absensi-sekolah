<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();
$rows = db()->query("SELECT l.*, u.nama FROM logs l LEFT JOIN users u ON u.id = l.user_id
  WHERE l.event <> 'login_ok' ORDER BY l.id DESC LIMIT 200")->fetchAll();
$label = ['mock_location' => 'Lokasi palsu', 'device_mismatch' => 'Perangkat tidak cocok', 'di_luar_radius' => 'Di luar radius',
          'login_gagal' => 'Login gagal', 'wajah_tidak_cocok' => 'Wajah tidak cocok', 'face_enroll' => 'Wajah didaftarkan'];
$bad = ['mock_location', 'device_mismatch', 'wajah_tidak_cocok'];
page_head('Log Keamanan');
?>
<div class="card scroll"><h3>200 kejadian terakhir</h3>
<table><tr><th>Waktu</th><th>Pengguna</th><th>Kejadian</th><th>Detail</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['created_at']) ?></td><td><?= h($r['nama'] ?? '-') ?></td>
<td><span class="tag <?= in_array($r['event'], $bad, true) ? 'bad' : ($r['event'] === 'face_enroll' ? 'ok' : 'warn') ?>"><?= h($label[$r['event']] ?? $r['event']) ?></span></td>
<td><?= h($r['detail']) ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="4" class="dim">Belum ada kejadian.</td></tr>'; ?></table></div>
<?php page_foot();
