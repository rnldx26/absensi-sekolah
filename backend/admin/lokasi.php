<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['aksi'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($a === 'simpan') {
        $nama = trim($_POST['nama'] ?? ''); $lat = (float)($_POST['lat'] ?? 0); $lng = (float)($_POST['lng'] ?? 0); $rad = (int)($_POST['radius'] ?? 0);
        if ($nama === '') flash('Gagal: nama lokasi wajib diisi.');
        elseif (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) flash('Gagal: koordinat tidak valid.');
        elseif ($rad < 10 || $rad > 5000) flash('Gagal: radius harus 10 - 5000 meter.');
        elseif ($id === 0) { $pdo->prepare('INSERT INTO locations (nama, lat, lng, radius, aktif) VALUES (?,?,?,?,1)')->execute([$nama, $lat, $lng, $rad]); flash('Lokasi ditambahkan dan langsung aktif.'); }
        else { $pdo->prepare('UPDATE locations SET nama=?, lat=?, lng=?, radius=? WHERE id=?')->execute([$nama, $lat, $lng, $rad, $id]); flash('Lokasi diperbarui.'); }
    } elseif ($a === 'toggle' && $id) {
        $pdo->prepare('UPDATE locations SET aktif = 1 - aktif WHERE id=?')->execute([$id]); flash('Status lokasi diubah.');
    } elseif ($a === 'hapus' && $id) {
        $pdo->prepare('DELETE FROM locations WHERE id=?')->execute([$id]); flash('Lokasi dihapus. Riwayat absen tetap menyimpan nama lokasinya.');
    }
    header('Location: lokasi.php'); exit;
}
$edit = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM locations WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch() ?: null; }
$locs = $pdo->query('SELECT * FROM locations ORDER BY aktif DESC, nama')->fetchAll();
$nAktif = count(array_filter($locs, fn($l) => $l['aktif']));
page_head('Lokasi');
?>
<div class="card"><h3><?= $edit ? 'ubah lokasi' : 'tambah lokasi absen' ?></h3>
<form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
<div class="grid">
 <div><label>Nama lokasi</label><input name="nama" required placeholder="mis. Gedung Utama" value="<?= h($edit['nama'] ?? '') ?>"></div>
 <div><label>Latitude</label><input name="lat" id="lat" required value="<?= h($edit['lat'] ?? '') ?>"></div>
 <div><label>Longitude</label><input name="lng" id="lng" required value="<?= h($edit['lng'] ?? '') ?>"></div>
 <div><label>Radius (meter)</label><input name="radius" type="number" required value="<?= h($edit['radius'] ?? 100) ?>"></div>
</div>
<p><button type="button" class="btn2" onclick="pakaiLokasi()">Gunakan lokasi perangkat ini</button> <small>(buka dari perangkat yang sedang di lokasi; butuh HTTPS)</small></p>
<button>Simpan</button> <?php if ($edit) echo '<a class="btn btn2" href="lokasi.php">Batal</a>'; ?>
</form></div>

<div class="card scroll"><h3>daftar lokasi (<?= $nAktif ?> aktif)</h3>
<?php if ($nAktif === 0) echo '<div class="msg err">Belum ada lokasi aktif. Pengguna tidak dapat absen sebelum minimal satu lokasi diaktifkan.</div>'; ?>
<table><tr><th>Nama</th><th>Koordinat</th><th>Radius</th><th>Status</th><th>Aksi</th></tr>
<?php foreach ($locs as $l): ?>
<tr><td><b><?= h($l['nama']) ?></b></td>
<td><?= h($l['lat']) ?>, <?= h($l['lng']) ?><br><small><a href="https://www.google.com/maps?q=<?= h($l['lat']) ?>,<?= h($l['lng']) ?>" target="_blank" rel="noopener noreferrer">lihat di peta</a></small></td>
<td><?= (int)$l['radius'] ?> m</td>
<td><span class="tag <?= $l['aktif'] ? 'ok' : 'bad' ?>"><?= $l['aktif'] ? 'aktif' : 'nonaktif' ?></span></td>
<td><a class="btn btn2 sm" href="?edit=<?= (int)$l['id'] ?>">Ubah</a>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn2 sm"><?= $l['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
<form method="post" class="inline" onsubmit="return confirm('Hapus lokasi ini?')"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="danger sm">Hapus</button></form></td></tr>
<?php endforeach; if (!$locs) echo '<tr><td colspan="5" class="dim">Belum ada lokasi.</td></tr>'; ?></table>
<p><small>Pengguna cukup berada di dalam radius salah satu lokasi aktif. Lokasi yang dinonaktifkan tidak dapat dipakai absen.</small></p></div>
<script>
function pakaiLokasi(){navigator.geolocation.getCurrentPosition(function(p){document.getElementById('lat').value=p.coords.latitude.toFixed(7);document.getElementById('lng').value=p.coords.longitude.toFixed(7);},function(){alert('Lokasi tidak dapat diambil.');},{enableHighAccuracy:true});}
</script>
<?php page_foot();
