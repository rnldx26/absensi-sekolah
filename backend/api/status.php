<?php
require __DIR__ . '/_boot.php';
$u = auth_user();
$today = date('Y-m-d');

$st = db()->prepare('SELECT tipe, waktu, status FROM attendance WHERE user_id = ? AND tanggal = ?');
$st->execute([$u['id'], $today]);
$masuk = $pulang = null;
foreach ($st as $r) {
    $row = ['jam' => date('H:i', strtotime($r['waktu'])), 'status' => $r['status']];
    if ($r['tipe'] === 'masuk') $masuk = $row; else $pulang = $row;
}

$st = db()->prepare("SELECT tanggal,
    MAX(CASE WHEN tipe='masuk' THEN TIME_FORMAT(waktu,'%H:%i') END) AS masuk,
    MAX(CASE WHEN tipe='pulang' THEN TIME_FORMAT(waktu,'%H:%i') END) AS pulang,
    MAX(CASE WHEN tipe='masuk' THEN status END) AS status
  FROM attendance WHERE user_id = ? GROUP BY tanggal ORDER BY tanggal DESC LIMIT 10");
$st->execute([$u['id']]);

$lokasi = array_map(fn($l) => $l['nama'] . ' (' . $l['radius'] . ' m)', active_locations());

out([
    'ok' => true,
    'nama' => $u['nama'],
    'tanggal' => $today,
    'sekolah' => setting('nama_sekolah', ''),
    'lokasi' => $lokasi,
    'jam_masuk' => setting('jam_masuk', '07:00'),
    'jam_pulang' => setting('jam_pulang', '13:00'),
    'face_enrolled' => !empty($u['face_template']),
    'masuk' => $masuk,
    'pulang' => $pulang,
    'riwayat' => $st->fetchAll(),
]);
