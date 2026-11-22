<?php
require __DIR__ . '/_boot.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method tidak diizinkan.', 405);

$b = body();
$nip  = trim($b['nip'] ?? '');
$pass = (string)($b['password'] ?? '');
$d = [
    'brand' => trim($b['brand'] ?? ''),
    'model' => trim($b['model'] ?? ''),
    'aid'   => trim($b['android_id'] ?? ''),
];
if ($nip === '' || $pass === '') fail('NIP dan password wajib diisi.');
if ($d['aid'] === '') fail('Informasi perangkat tidak lengkap.', 403, 'device_missing');

// Batasi percobaan login: 5x gagal per 10 menit per NIP
$st = db()->prepare("SELECT COUNT(*) FROM logs WHERE event='login_gagal' AND detail=? AND created_at > ?");
$st->execute(["nip=$nip", date('Y-m-d H:i:s', time() - 600)]);
if ((int)$st->fetchColumn() >= 5) fail('Terlalu banyak percobaan. Coba lagi 10 menit lagi.', 429, 'too_many');

$st = db()->prepare('SELECT * FROM users WHERE nip = ?');
$st->execute([$nip]);
$u = $st->fetch();
if (!$u || !password_verify($pass, $u['password_hash'])) {
    add_log($u ? (int)$u['id'] : null, 'login_gagal', "nip=$nip");
    fail('NIP atau password salah.', 401, 'bad_credentials');
}
if (!$u['aktif']) fail('Akun dinonaktifkan. Hubungi admin.', 403, 'inactive');

// brand + model harus sama dengan yang didaftarkan admin
if (!same($u['device_brand'], $d['brand']) || !same($u['device_model'], $d['model'])) {
    add_log((int)$u['id'], 'device_mismatch',
        "login: terdaftar {$u['device_brand']} {$u['device_model']} | dipakai {$d['brand']} {$d['model']}");
    fail('Perangkat ini tidak terdaftar untuk akun Anda. Hubungi admin.', 403, 'device_mismatch');
}
// ANDROID_ID: terikat otomatis saat login pertama, admin bisa mereset
if (empty($u['android_id'])) {
    db()->prepare('UPDATE users SET android_id = ? WHERE id = ?')->execute([$d['aid'], $u['id']]);
} elseif ($u['android_id'] !== $d['aid']) {
    add_log((int)$u['id'], 'device_mismatch', "login: ANDROID_ID berbeda ({$d['brand']} {$d['model']})");
    fail('Perangkat ini tidak terdaftar untuk akun Anda. Hubungi admin.', 403, 'device_mismatch');
}

$token = bin2hex(random_bytes(32));
db()->prepare('UPDATE users SET api_token = ? WHERE id = ?')->execute([hash('sha256', $token), $u['id']]);
add_log((int)$u['id'], 'login_ok', "{$d['brand']} {$d['model']}");
out(['ok' => true, 'token' => $token, 'nama' => $u['nama'], 'jabatan' => $u['jabatan']]);
