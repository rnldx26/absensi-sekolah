<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();
$keys = ['nama_sekolah', 'lat', 'lng', 'radius', 'jam_masuk', 'toleransi', 'jam_pulang', 'max_akurasi'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v = []; foreach ($keys as $k) $v[$k] = trim($_POST[$k] ?? '');
    $lat = (float)$v['lat']; $lng = (float)$v['lng']; $rad = (int)$v['radius'];
    if ($v['nama_sekolah'] === '' || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) flash('Gagal: nama dan koordinat lokasi wajib diisi dengan benar.');
    elseif ($rad < 10 || $rad > 5000) flash('Gagal: radius harus 10 - 5000 meter.');
    elseif (!preg_match('/^\d{2}:\d{2}$/', $v['jam_masuk']) || !preg_match('/^\d{2}:\d{2}$/', $v['jam_pulang'])) flash('Gagal: format jam harus HH:MM.');
    else {
        $st = db()->prepare('INSERT INTO settings (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
        foreach ($keys as $k) $st->execute([$k, $v[$k]]);
        flash('Pengaturan disimpan.');
    }
    header('Location: settings.php'); exit;
}
$s = []; foreach ($keys as $k) $s[$k] = setting($k, '');
page_head('Pengaturan');
?>
<div class="card"><form method="post"><?= csrf_field() ?>
<div class="grid">
 <div><label>Nama sekolah/madrasah</label><input name="nama_sekolah" value="<?= h($s['nama_sekolah']) ?>"></div>
 <div><label>Latitude</label><input name="lat" id="lat" value="<?= h($s['lat']) ?>"></div>
 <div><label>Longitude</label><input name="lng" id="lng" value="<?= h($s['lng']) ?>"></div>
 <div><label>Radius absen (meter)</label><input name="radius" type="number" value="<?= h($s['radius']) ?>"></div>
 <div><label>Jam masuk</label><input name="jam_masuk" type="time" value="<?= h($s['jam_masuk']) ?>"></div>
 <div><label>Toleransi terlambat (menit)</label><input name="toleransi" type="number" value="<?= h($s['toleransi']) ?>"></div>
 <div><label>Absen pulang mulai jam</label><input name="jam_pulang" type="time" value="<?= h($s['jam_pulang']) ?>"></div>
 <div><label>Akurasi GPS maksimal (meter)</label><input name="max_akurasi" type="number" value="<?= h($s['max_akurasi']) ?>"></div>
</div>
<p><button type="button" class="btn2" style="background:#e5e9ee;color:#1f2933" onclick="pakaiLokasi()">Gunakan lokasi perangkat ini</button>
<small> (jalankan dari HP/laptop yang sedang berada di sekolah, butuh HTTPS)</small></p>
<button>Simpan</button></form></div>
<script>
function pakaiLokasi(){navigator.geolocation.getCurrentPosition(function(p){document.getElementById('lat').value=p.coords.latitude.toFixed(7);document.getElementById('lng').value=p.coords.longitude.toFixed(7);},function(){alert('Lokasi tidak dapat diambil.');},{enableHighAccuracy:true});}
</script>
<?php page_foot();
