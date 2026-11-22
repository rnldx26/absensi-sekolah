<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['aksi'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($a === 'simpan') {
        $nip = trim($_POST['nip'] ?? ''); $nama = trim($_POST['nama'] ?? ''); $jab = trim($_POST['jabatan'] ?? 'Guru');
        $brand = trim($_POST['brand'] ?? ''); $model = trim($_POST['model'] ?? ''); $pass = (string)($_POST['password'] ?? '');
        if ($nip === '' || $nama === '' || $brand === '' || $model === '') flash('NIP, nama, brand, dan model perangkat wajib diisi.');
        elseif ($id === 0 && strlen($pass) < 6) flash('Password minimal 6 karakter.');
        elseif ($pass !== '' && strlen($pass) < 6) flash('Password minimal 6 karakter.');
        else {
            try {
                if ($id === 0) {
                    $pdo->prepare('INSERT INTO users (nip, nama, jabatan, password_hash, device_brand, device_model) VALUES (?,?,?,?,?,?)')
                        ->execute([$nip, $nama, $jab, password_hash($pass, PASSWORD_DEFAULT), $brand, $model]);
                    flash('Pengguna ditambahkan.');
                } else {
                    $old = $pdo->prepare('SELECT device_brand, device_model FROM users WHERE id = ?'); $old->execute([$id]); $o = $old->fetch();
                    $pdo->prepare('UPDATE users SET nip=?, nama=?, jabatan=?, device_brand=?, device_model=? WHERE id=?')->execute([$nip, $nama, $jab, $brand, $model, $id]);
                    if ($pass !== '') $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
                    $ganti = $o && (mb_strtolower($o['device_brand']) !== mb_strtolower($brand) || mb_strtolower($o['device_model']) !== mb_strtolower($model));
                    if ($ganti) { $pdo->prepare('UPDATE users SET android_id=NULL, api_token=NULL WHERE id=?')->execute([$id]); flash('Perubahan disimpan. Perangkat diganti, pengguna harus login ulang di HP baru.'); }
                    else flash('Perubahan disimpan.');
                }
            } catch (PDOException $e) {
                flash($e->getCode() === '23000' ? 'Gagal: NIP sudah dipakai.' : 'Gagal menyimpan data.');
            }
        }
    } elseif ($a === 'reset' && $id) {
        $pdo->prepare('UPDATE users SET android_id=NULL, api_token=NULL WHERE id=?')->execute([$id]);
        flash('Ikatan perangkat direset. Pengguna harus login ulang.');
    } elseif ($a === 'toggle' && $id) {
        $pdo->prepare('UPDATE users SET aktif = 1 - aktif, api_token = NULL WHERE id=?')->execute([$id]);
        flash('Status akun diubah.');
    } elseif ($a === 'hapus' && $id) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        flash('Pengguna dan seluruh riwayat absennya dihapus.');
    }
    header('Location: users.php'); exit;
}

$edit = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch() ?: null; }
$users = $pdo->query('SELECT * FROM users ORDER BY nama')->fetchAll();
page_head('Pengguna');
?>
<div class="card">
<h3 style="margin-top:0"><?= $edit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></h3>
<form method="post"><?= csrf_field() ?>
<input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
<div class="grid">
 <div><label>NIP / ID (untuk login)</label><input name="nip" required value="<?= h($edit['nip'] ?? '') ?>"></div>
 <div><label>Nama lengkap</label><input name="nama" required value="<?= h($edit['nama'] ?? '') ?>"></div>
 <div><label>Jabatan</label><input name="jabatan" value="<?= h($edit['jabatan'] ?? 'Guru') ?>"></div>
 <div><label>Password <?= $edit ? '(kosongkan jika tidak diubah)' : '' ?></label><input name="password" type="password" <?= $edit ? '' : 'required' ?> autocomplete="new-password"></div>
 <div><label>Brand perangkat * (ro.product.brand)</label><input name="brand" required placeholder="mis. samsung" value="<?= h($edit['device_brand'] ?? '') ?>"></div>
 <div><label>Nomor model perangkat * (ro.product.model)</label><input name="model" required placeholder="mis. SM-A546E" value="<?= h($edit['device_model'] ?? '') ?>"></div>
</div>
<p><small>Cara termudah: pasang aplikasi di HP pengguna. Brand dan model tampil di layar login, salin persis seperti itu (huruf besar/kecil tidak dipermasalahkan). Mengganti brand/model akan mereset ikatan perangkat.</small></p>
<button>Simpan</button> <?php if ($edit) echo '<a class="btn btn2" href="users.php">Batal</a>'; ?>
</form></div>

<div class="card scroll"><table>
<tr><th>Nama / NIP</th><th>Perangkat terdaftar</th><th>Status</th><th>Aksi</th></tr>
<?php foreach ($users as $u): ?>
<tr><td><b><?= h($u['nama']) ?></b><br><small><?= h($u['nip']) ?> · <?= h($u['jabatan']) ?></small></td>
<td><?= h($u['device_brand']) ?> <?= h($u['device_model']) ?><br>
 <small><?= $u['android_id'] ? 'Terikat ke 1 perangkat' : 'Belum login / ikatan direset' ?></small></td>
<td><span class="tag <?= $u['aktif'] ? 'ok' : 'bad' ?>"><?= $u['aktif'] ? 'aktif' : 'nonaktif' ?></span></td>
<td>
<a class="btn btn2" href="?edit=<?= (int)$u['id'] ?>">Ubah</a>
<?php foreach ([['reset', 'Reset perangkat', 'btn2', 'Reset ikatan perangkat pengguna ini?'], ['toggle', $u['aktif'] ? 'Nonaktifkan' : 'Aktifkan', 'btn2', ''], ['hapus', 'Hapus', 'danger', 'Hapus pengguna beserta seluruh riwayat absennya?']] as [$ak, $lb, $cls, $cf]): ?>
<form method="post" class="inline" <?= $cf ? 'onsubmit="return confirm(\'' . h($cf) . '\')"' : '' ?>><?= csrf_field() ?>
<input type="hidden" name="aksi" value="<?= $ak ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="<?= $cls ?>"><?= h($lb) ?></button></form>
<?php endforeach; ?>
</td></tr>
<?php endforeach; ?></table></div>
<?php page_foot();
