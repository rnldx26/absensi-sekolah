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
$preflight = !empty($b['preflight']);   // true = hanya cek lokasi/waktu, belum verifikasi wajah

// 1) Mock location (dilaporkan aplikasi, lengkap dengan nama aplikasi pemalsunya)
if (!empty($b['is_mock'])) {
    $apps = mb_substr(trim((string)($b['mock_apps'] ?? '')), 0, 150);
    add_log((int)$u['id'], 'mock_location', "aplikasi: " . ($apps !== '' ? $apps : 'tidak diketahui') . " | lat=$lat lng=$lng");
    fail('Terdeteksi lokasi palsu (mock location).', 403, 'mock_location', ['apps' => $apps]);
}

// 2) Wajah harus sudah terdaftar
if (empty($u['face_template'])) fail('Wajah Anda belum terdaftar.', 403, 'face_not_enrolled');

// 3) Akurasi GPS
$maxAk = (float)setting('max_akurasi', 50);
if ($akurasi <= 0 || $akurasi > $maxAk) {
    fail('Akurasi GPS kurang baik (' . round($akurasi) . ' m, maks ' . round($maxAk) . ' m). Pindah ke area terbuka lalu coba lagi.', 422, 'low_accuracy');
}

// 4) Radius: cocok dengan salah satu lokasi AKTIF (dihitung di server)
$locs = active_locations();
if (!$locs) fail('Belum ada lokasi absen yang aktif. Hubungi admin.', 403, 'no_location');
$cocok = null; $dekat = null; $dekatJarak = INF;
foreach ($locs as $l) {
    $j = jarak_meter($lat, $lng, (float)$l['lat'], (float)$l['lng']);
    if ($j < $dekatJarak) { $dekatJarak = $j; $dekat = $l; }
    if ($j <= (float)$l['radius'] && ($cocok === null || $j < $cocok['jarak'])) $cocok = ['loc' => $l, 'jarak' => $j];
}
if (!$cocok) {
    add_log((int)$u['id'], 'di_luar_radius', 'terdekat ' . $dekat['nama'] . ' ' . round($dekatJarak) . "m lat=$lat lng=$lng");
    fail('Anda berada ' . round($dekatJarak) . ' m dari "' . $dekat['nama'] . '" (maks ' . (int)$dekat['radius'] . ' m).', 403, 'outside_radius', ['jarak' => (int)round($dekatJarak)]);
}
$lokasiNama = $cocok['loc']['nama'];
$jarak = $cocok['jarak'];

// 5) Aturan waktu (jam server)
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

if ($preflight) out(['ok' => true, 'preflight' => true, 'lokasi' => $lokasiNama, 'jarak' => (int)round($jarak)]);

// 6) Verifikasi wajah (dicocokkan di server dengan template terdaftar)
$emb = parse_embedding($b['embedding'] ?? null);
if (!$emb) fail('Data wajah tidak valid.', 422, 'face_invalid');
$tpl = json_decode($u['face_template'], true) ?: [];
$skor = cosine($emb, $tpl);
$thr = (float)setting('face_threshold', 0.60);
if ($skor < $thr) {
    add_log((int)$u['id'], 'wajah_tidak_cocok', 'skor=' . round($skor, 3) . ' batas=' . $thr . " ($tipe)");
    fail('Wajah tidak cocok dengan data terdaftar. Pastikan wajah terlihat jelas dan pencahayaan cukup.', 403, 'face_mismatch');
}
$foto = save_photo((string)($b['photo'] ?? ''), 'absen');
if (!$foto) fail('Foto wajah tidak valid.', 422, 'face_invalid');

try {
    db()->prepare('INSERT INTO attendance (user_id, tanggal, tipe, waktu, lat, lng, jarak, akurasi, status, lokasi_nama, foto, face_score) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$u['id'], $tanggal, $tipe, date('Y-m-d H:i:s', $now), $lat, $lng, (int)round($jarak), (int)round($akurasi), $status, $lokasiNama, $foto, round($skor, 3)]);
} catch (PDOException $e) {
    delete_photo($foto, 'absen');
    if ($e->getCode() === '23000') fail("Anda sudah absen $tipe hari ini.", 409, 'duplicate');
    throw $e;
}

out(['ok' => true, 'message' => "Absen $tipe berhasil di $lokasiNama", 'jam' => date('H:i', $now), 'status' => $status, 'jarak' => (int)round($jarak)]);
