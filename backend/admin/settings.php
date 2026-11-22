<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama = trim($_POST['nama_sekolah'] ?? '');
    $jm = trim($_POST['jam_masuk'] ?? ''); $jp = trim($_POST['jam_pulang'] ?? '');
    $tol = (int)($_POST['toleransi'] ?? 0); $mak = (int)($_POST['max_akurasi'] ?? 0); $thr = (float)($_POST['face_threshold'] ?? 0);
    $msgs = [];
    if ($nama === '') $msgs[] = 'Gagal: nama instansi wajib diisi.';
    elseif (!preg_match('/^\d{2}:\d{2}$/', $jm) || !preg_match('/^\d{2}:\d{2}$/', $jp)) $msgs[] = 'Gagal: format jam harus HH:MM.';
    elseif ($tol < 0 || $tol > 180 || $mak < 5 || $mak > 500) $msgs[] = 'Gagal: toleransi 0-180 menit, akurasi GPS 5-500 m.';
    elseif ($thr < 0.30 || $thr > 0.95) $msgs[] = 'Gagal: ambang kecocokan wajah harus 0,30 - 0,95.';
    else {
        save_setting('nama_sekolah', $nama);
        save_setting('tampil_branding', !empty($_POST['tampil_branding']) ? '1' : '0');
        save_setting('jam_masuk', $jm); save_setting('jam_pulang', $jp);
        save_setting('toleransi', (string)$tol); save_setting('max_akurasi', (string)$mak);
        save_setting('face_threshold', number_format($thr, 2, '.', ''));
        $msgs[] = 'Pengaturan disimpan.';

        $old = setting('logo_file', '');
        if (!empty($_POST['hapus_logo']) && $old !== '') {
            @unlink(UPLOAD_DIR . '/' . basename($old)); save_setting('logo_file', ''); $msgs[] = 'Logo dihapus.';
        }
        if (!empty($_FILES['logo']['name'])) {
            $f = $_FILES['logo'];
            $map = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
            $info = ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= 1500000) ? @getimagesize($f['tmp_name']) : false;
            if (!$info || !isset($map[$info[2]])) $msgs[] = 'Gagal: logo harus PNG/JPG/WEBP, maksimal 1,5 MB.';
            else {
                $name = 'logo_' . bin2hex(random_bytes(4)) . '.' . $map[$info[2]];
                if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
                    $cur = setting('logo_file', '');
                    if ($cur !== '' && is_file(UPLOAD_DIR . '/' . basename($cur))) @unlink(UPLOAD_DIR . '/' . basename($cur));
                    save_setting('logo_file', $name); $msgs[] = 'Logo diperbarui.';
                } else $msgs[] = 'Gagal menyimpan logo (folder uploads tidak dapat ditulis).';
            }
        }
    }
    flash(implode(' ', $msgs));
    header('Location: settings.php'); exit;
}
$logo = setting('logo_file', '');
page_head('Pengaturan');
?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="card"><h3>identitas instansi (tampil di menu login)</h3>
<div class="grid">
 <div><label>Nama instansi</label><input name="nama_sekolah" value="<?= h(setting('nama_sekolah', '')) ?>"></div>
 <div><label>Logo (PNG/JPG/WEBP, maks 1,5 MB)</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div>
</div>
<?php if ($logo !== '' && is_file(UPLOAD_DIR . '/' . basename($logo))): ?>
<p><img src="../api/logo.php?v=<?= h($logo) ?>" alt="logo" style="max-height:70px;border:1px solid var(--line);padding:4px;background:#000">
 <label style="display:inline-block;margin-left:12px"><input type="checkbox" name="hapus_logo" value="1"> hapus logo</label></p>
<?php endif; ?>
<p><label style="margin:0"><input type="checkbox" name="tampil_branding" value="1" <?= setting('tampil_branding', '1') === '1' ? 'checked' : '' ?>> tampilkan nama &amp; logo di halaman login (web admin dan aplikasi Android)</label></p>
</div>

<div class="card"><h3>jam kerja</h3><div class="grid">
 <div><label>Jam masuk</label><input name="jam_masuk" type="time" value="<?= h(setting('jam_masuk', '07:00')) ?>"></div>
 <div><label>Toleransi terlambat (menit)</label><input name="toleransi" type="number" value="<?= h(setting('toleransi', 10)) ?>"></div>
 <div><label>Absen pulang mulai jam</label><input name="jam_pulang" type="time" value="<?= h(setting('jam_pulang', '13:00')) ?>"></div>
</div></div>

<div class="card"><h3>keamanan</h3><div class="grid">
 <div><label>Akurasi GPS maksimal (meter)</label><input name="max_akurasi" type="number" value="<?= h(setting('max_akurasi', 50)) ?>"></div>
 <div><label>Ambang kecocokan wajah (0,30 - 0,95)</label><input name="face_threshold" type="number" step="0.01" value="<?= h(setting('face_threshold', '0.60')) ?>"></div>
</div>
<p><small>Ambang wajah: makin tinggi makin ketat (lebih banyak penolakan salah). Uji dengan beberapa pengguna lalu cek kolom skor di Laporan untuk menentukan nilai yang pas. Titik lokasi absen diatur di menu Lokasi.</small></p></div>
<button>&gt; Simpan pengaturan</button>
</form>
<?php page_foot();
