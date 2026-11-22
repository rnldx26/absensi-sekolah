<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();
$ok = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d ?? '');
$dari = $ok($_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01');
$sampai = $ok($_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$uid = (int)($_GET['user'] ?? 0);

$sql = 'SELECT a.id, a.tanggal, u.nip, u.nama, a.tipe, a.waktu, a.status, a.jarak, a.akurasi, a.lokasi_nama, a.foto, a.face_score, u.face_photo, u.id AS uid
        FROM attendance a JOIN users u ON u.id = a.user_id WHERE a.tanggal BETWEEN ? AND ?';
$p = [$dari, $sampai];
if ($uid) { $sql .= ' AND a.user_id = ?'; $p[] = $uid; }
$sql .= ' ORDER BY a.tanggal DESC, u.nama, a.tipe';
$st = db()->prepare($sql); $st->execute($p); $rows = $st->fetchAll();

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="absensi_' . $dari . '_' . $sampai . '.csv"');
    $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, ['Tanggal', 'NIP', 'Nama', 'Jenis', 'Jam', 'Status', 'Lokasi', 'Jarak (m)', 'Akurasi (m)', 'Skor wajah']);
    foreach ($rows as $r) fputcsv($f, [$r['tanggal'], $r['nip'], $r['nama'], $r['tipe'], date('H:i:s', strtotime($r['waktu'])), $r['status'], $r['lokasi_nama'], $r['jarak'], $r['akurasi'], $r['face_score']]);
    exit;
}
$users = db()->query('SELECT id, nama FROM users ORDER BY nama')->fetchAll();
page_head('Laporan');
?>
<div class="card"><form method="get" class="grid">
 <div><label>Dari</label><input type="date" name="dari" value="<?= h($dari) ?>"></div>
 <div><label>Sampai</label><input type="date" name="sampai" value="<?= h($sampai) ?>"></div>
 <div><label>Pengguna</label><select name="user"><option value="0">Semua</option>
  <?php foreach ($users as $u) echo '<option value="' . (int)$u['id'] . '"' . ($uid === (int)$u['id'] ? ' selected' : '') . '>' . h($u['nama']) . '</option>'; ?></select></div>
 <div style="align-self:end"><button>Tampilkan</button>
  <a class="btn btn2" href="?<?= h(http_build_query(['dari' => $dari, 'sampai' => $sampai, 'user' => $uid, 'csv' => 1])) ?>">CSV</a></div>
</form></div>
<div class="card scroll"><table><tr><th>Foto</th><th>Tanggal</th><th>Nama</th><th>Jenis</th><th>Jam</th><th>Status</th><th>Lokasi</th><th>Wajah</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= $r['foto'] ? avatar($r['foto'], 'absen', (int)$r['id']) : avatar($r['face_photo'], 'profile', (int)$r['uid']) ?></td>
<td><?= h($r['tanggal']) ?></td><td><?= h($r['nama']) ?><br><small><?= h($r['nip']) ?></small></td><td><?= h($r['tipe']) ?></td>
<td><?= h(date('H:i', strtotime($r['waktu']))) ?></td><td><span class="tag <?= $r['status'] === 'terlambat' ? 'warn' : 'ok' ?>"><?= h($r['status']) ?></span></td>
<td><?= h($r['lokasi_nama'] ?? '-') ?><br><small><?= (int)$r['jarak'] ?> m &middot; akurasi <?= (int)$r['akurasi'] ?> m</small></td>
<td><?= $r['face_score'] !== null ? 'skor ' . h($r['face_score']) : '-' ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="dim">Tidak ada data.</td></tr>'; ?></table>
<p><small>Foto = hasil kamera saat absen (klik untuk memperbesar). Jika kosong, ditampilkan foto pendaftaran wajah.</small></p></div>
<?php page_foot();
