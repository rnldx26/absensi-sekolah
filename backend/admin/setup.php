<?php
// Jalankan SEKALI untuk membuat akun admin pertama, lalu HAPUS file ini.
require __DIR__ . '/_auth.php';
if ((int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) exit('Admin sudah dibuat. Hapus file setup.php dari server.');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? ''); $p = (string)($_POST['password'] ?? '');
    if ($u === '' || strlen($p) < 8) { $msg = 'Username wajib diisi dan password minimal 8 karakter.'; }
    else {
        db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
        exit('Admin dibuat. HAPUS file setup.php sekarang, lalu <a href="login.php">login</a>.');
    }
}
?><!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<body style="font:15px system-ui;max-width:340px;margin:60px auto"><h2>Setup Admin</h2><?= $msg ? '<p style="color:#b91c1c">' . htmlspecialchars($msg) . '</p>' : '' ?>
<form method="post"><?= csrf_field() ?>Username<br><input name="username" required style="width:100%;padding:8px"><br><br>
Password (min. 8)<br><input name="password" type="password" required style="width:100%;padding:8px"><br><br><button>Buat Admin</button></form></body>
