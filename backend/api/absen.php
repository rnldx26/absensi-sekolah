<?php
require __DIR__ . '/_boot.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method tidak diizinkan.', 405);
$u = auth_user();
$b = body();

$tipe = $b['tipe'] ?? '';
if (!in_array($tipe, ['masuk', 'pulang'], true)) fail('Jenis absen tidak valid.');
if (!isset($b['lat'], $b['lng'])) fail('Lokasi tidak terbaca.');
$lat = (float)$b['lat']; $lng = (float)$b['lng'];
$akurasi = (float)($b['akurasi'] ?? 0);

// 1) Mock location (dilaporkan aplikasi)
if (!empty($b['is_mock'])) {
    add_log((int)$u['id'], 'mock_location', "lat=$lat lng=$lng");
    fail('Terdeteksi lokasi palsu (mock location). Matikan aplikasi lokasi palsu lalu coba lagi.', 403, 'mock_location');
}

// 2) Akurasi GPS
$maxAk = (float)setting('max_akurasi', 50);
if ($akurasi <= 0 || $akurasi > $maxAk) {
    fail('Akurasi GPS kurang baik (' . round($akurasi) . ' m, maks ' . round($maxAk) . ' m). Pindah ke area terbuka lalu coba lagi.', 422, 'low_accuracy');
}

// 3) Radius, dihitung ulang di server
$jarak  = jarak_meter($lat, $lng, (float)setting('lat', 0), (float)setting('lng', 0));
$radius = (float)setting('radius', 100);
if ($jarak > $radius) {
    add_log((int)$u['id'], 'di_luar_radius', 'jarak=' . round($jarak) . "m lat=$lat lng=$lng");
    fail('Anda berada ' . round($jarak) . ' m dari lokasi (maks ' . round($radius) . ' m).', 403, 'outside_radius', ['jarak' => (int)round($jarak)]);
}

// 4) Aturan waktu (jam server)
$now = time();
$tanggal = date('Y-m-d', $now);
$st = db()->prepare('SELECT tipe FROM attendance WHERE user_id = ? AND tanggal = ?');
$st->execute([$u['id'], $tanggal]);
$sudah = $st->fetchAll(PDO::FETCH_COLUMN);

if (in_array($tipe, $sudah, true)) fail("Anda sudah absen $tipe hari ini.", 409, 'duplicate');

if ($tipe === 'masuk') {
    $batas = strtotime("$tanggal " . setting('jam_masuk', '07:00')) + ((int)setting('toleransi', 10)) * 60;
    $status = $now > $batas ? 'terlambat' : 'hadir';
} else {
    if (!in_array('masuk', $sudah, true)) fail('Anda belum absen masuk hari ini.', 409, 'no_masuk');
    $jp = setting('jam_pulang', '13:00');
    if ($now < strtotime("$tanggal $jp")) fail("Belum waktunya pulang (mulai $jp).", 409, 'too_early');
    $status = 'pulang';
}

try {
    db()->prepare('INSERT INTO attendance (user_id, tanggal, tipe, waktu, lat, lng, jarak, akurasi, status) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$u['id'], $tanggal, $tipe, date('Y-m-d H:i:s', $now), $lat, $lng, (int)round($jarak), (int)round($akurasi), $status]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') fail("Anda sudah absen $tipe hari ini.", 409, 'duplicate');
    throw $e;
}

out(['ok' => true, 'message' => "Absen $tipe berhasil", 'jam' => date('H:i', $now), 'status' => $status, 'jarak' => (int)round($jarak)]);
