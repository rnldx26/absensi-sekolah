<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php'; require_admin();

const MAX_BARIS = 300;   // bcrypt per baris ~50-100 ms, batasi agar tidak timeout

function col_index(string $ref): int {
    preg_match('/^([A-Z]+)/', $ref, $m); $n = 0;
    foreach (str_split($m[1] ?? 'A') as $ch) $n = $n * 26 + (ord($ch) - 64);
    return $n - 1;
}
function fix_num(string $v): string {
    return preg_match('/^-?\d+(\.\d+)?[eE][+-]?\d+$/', $v) ? sprintf('%.0f', (float)$v) : $v;
}
/** Baca sheet pertama .xlsx tanpa library tambahan (ZipArchive + SimpleXML). */
function read_xlsx(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('File Excel tidak dapat dibuka.');
    libxml_use_internal_errors(true);
    $shared = [];
    if (($x = $zip->getFromName('xl/sharedStrings.xml')) !== false && ($sx = simplexml_load_string($x))) {
        foreach ($sx->si as $si) {
            if (isset($si->t)) { $shared[] = (string)$si->t; continue; }
            $t = ''; foreach ($si->r as $r) $t .= (string)$r->t; $shared[] = $t;
        }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheet === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $n)) { $sheet = $zip->getFromIndex($i); break; }
        }
    }
    $zip->close();
    if ($sheet === false || !($sx = simplexml_load_string($sheet))) throw new RuntimeException('Sheet Excel tidak terbaca.');
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $t = (string)$c['t'];
            if ($t === 's') $v = $shared[(int)$c->v] ?? '';
            elseif ($t === 'inlineStr') { $v = isset($c->is->t) ? (string)$c->is->t : ''; if ($v === '' && isset($c->is->r)) foreach ($c->is->r as $rr) $v .= (string)$rr->t; }
            else $v = fix_num((string)$c->v);
            $r[col_index((string)$c['r'])] = trim($v);
        }
        $rows[] = $r;
    }
    return $rows;
}
function read_csv_file(string $path): array {
    $rows = []; $f = fopen($path, 'r');
    $first = fgets($f); rewind($f);
    $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    while (($d = fgetcsv($f, 0, $delim)) !== false) {
        if ($d === [null]) continue;
        if (!$rows && isset($d[0])) $d[0] = preg_replace('/^\xEF\xBB\xBF/', '', $d[0]);
        $rows[] = array_map('trim', $d);
    }
    fclose($f);
    return $rows;
}
function norm_head(string $s): string { return preg_replace('/[^a-z]/', '', strtolower($s)); }
function rand_pass(): string {
    $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $p = '';
    for ($i = 0; $i < 8; $i++) $p .= $abc[random_int(0, strlen($abc) - 1)];
    return $p;
}

$hasil = null; $fatal = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    @set_time_limit(180);
    $f = $_FILES['file'] ?? null;
    $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) $fatal = 'Gagal: file tidak terunggah.';
    elseif ($f['size'] > 2000000) $fatal = 'Gagal: ukuran file maksimal 2 MB.';
    elseif (!in_array($ext, ['xlsx', 'csv'], true)) $fatal = 'Gagal: gunakan file .xlsx dari template (atau .csv).';
    elseif ($ext === 'xlsx' && !class_exists('ZipArchive')) $fatal = 'Gagal: ekstensi PHP zip belum aktif di server. Gunakan file .csv atau aktifkan ekstensi zip.';
    else {
        try {
            $rows = $ext === 'xlsx' ? read_xlsx($f['tmp_name']) : read_csv_file($f['tmp_name']);
            if (count($rows) < 2) throw new RuntimeException('File kosong (butuh header + minimal 1 baris data).');
            // petakan kolom berdasarkan nama header
            $map = [];
            foreach ($rows[0] as $i => $h) {
                $k = norm_head((string)$h);
                if (in_array($k, ['nip', 'nama', 'jabatan', 'password', 'brand', 'model'], true)) $map[$k] = $i;
            }
            foreach (['nip', 'nama', 'brand', 'model'] as $w) if (!isset($map[$w])) throw new RuntimeException("Gagal: kolom \"$w\" tidak ditemukan di header. Gunakan template yang disediakan.");
            if (count($rows) - 1 > MAX_BARIS) throw new RuntimeException('Gagal: maksimal ' . MAX_BARIS . ' baris per impor. Pecah menjadi beberapa file.');

            $pdo = db();
            $ada = array_flip($pdo->query('SELECT nip FROM users')->fetchAll(PDO::FETCH_COLUMN));
            $ins = $pdo->prepare('INSERT INTO users (nip, nama, jabatan, password_hash, device_brand, device_model) VALUES (?,?,?,?,?,?)');
            $hasil = ['ok' => 0, 'gagal' => 0, 'rows' => []];
            $pdo->beginTransaction();
            for ($n = 1; $n < count($rows); $n++) {
                $r = $rows[$n];
                $g = fn($k) => isset($map[$k]) ? trim((string)($r[$map[$k]] ?? '')) : '';
                $nip = $g('nip'); $nama = $g('nama'); $jab = $g('jabatan') ?: 'Guru'; $pw = $g('password'); $brand = $g('brand'); $model = $g('model');
                if ($nip . $nama . $brand . $model . $pw === '') continue;   // baris kosong
                $baris = $n + 1; $err = '';
                if ($nip === '' || $nama === '' || $brand === '' || $model === '') $err = 'NIP, nama, brand, model wajib diisi';
                elseif (isset($ada[$nip])) $err = 'NIP sudah terdaftar';
                elseif ($pw !== '' && strlen($pw) < 6) $err = 'password minimal 6 karakter';
                if ($err) { $hasil['gagal']++; $hasil['rows'][] = [$baris, $nip, $nama, $err, '']; continue; }
                $auto = $pw === '';
                if ($auto) $pw = rand_pass();
                $ins->execute([$nip, $nama, $jab, password_hash($pw, PASSWORD_DEFAULT), $brand, $model]);
                $ada[$nip] = true; $hasil['ok']++;
                $hasil['rows'][] = [$baris, $nip, $nama, 'OK', $auto ? $pw : '(sesuai file)'];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $hasil = null; $fatal = $e instanceof RuntimeException ? $e->getMessage() : 'Gagal memproses file.';
        }
    }
}
page_head('Impor Pengguna');
if ($fatal) echo '<div class="msg err">' . h($fatal) . '</div>';
?>
<div class="card">
<h3>impor pengguna dari excel</h3>
<p>1. Unduh template &rarr; 2. isi data (satu pengguna per baris) &rarr; 3. unggah di bawah ini.</p>
<p><a class="btn btn2" href="template_pengguna.xlsx" download>Unduh template .xlsx</a></p>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
 <label>File .xlsx (maks. 2 MB, <?= MAX_BARIS ?> baris)</label><input type="file" name="file" accept=".xlsx,.csv" required>
 <p><button>&gt; Impor</button> <a class="btn btn2" href="users.php">Kembali</a></p>
</form>
<p><small>Kolom wajib: NIP, Nama, Brand, Model. Jabatan kosong = Guru. Password kosong = dibuatkan otomatis (ditampilkan sekali setelah impor). NIP yang sudah ada dilewati. Wajah didaftarkan masing-masing pengguna saat login pertama.</small></p>
</div>
<?php if ($hasil): ?>
<div class="card scroll"><h3>hasil impor: <?= (int)$hasil['ok'] ?> berhasil, <?= (int)$hasil['gagal'] ?> gagal</h3>
<?php if (array_filter($hasil['rows'], fn($x) => $x[3] === 'OK' && $x[4] !== '(sesuai file)')): ?>
<div class="msg err">Password otomatis hanya tampil sekali di halaman ini. Catat/salin sekarang dan bagikan ke pengguna.</div>
<?php endif; ?>
<table><tr><th>Baris</th><th>NIP</th><th>Nama</th><th>Status</th><th>Password</th></tr>
<?php foreach ($hasil['rows'] as $x): ?>
<tr><td><?= (int)$x[0] ?></td><td><?= h($x[1]) ?></td><td><?= h($x[2]) ?></td>
<td><span class="tag <?= $x[3] === 'OK' ? 'ok' : 'bad' ?>"><?= h($x[3]) ?></span></td><td class="pw"><?= h($x[4]) ?></td></tr>
<?php endforeach; ?></table></div>
<?php endif; page_foot();
