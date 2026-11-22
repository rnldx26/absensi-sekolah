<?php
// Jalankan SEKALI untuk membuat akun admin pertama, lalu HAPUS file ini.
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php';
if ((int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) exit('Admin sudah dibuat. Hapus file setup.php dari server.');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? ''); $p = (string)($_POST['password'] ?? '');
    if ($u === '' || strlen($p) < 8) { $msg = 'Username wajib diisi dan password minimal 8 karakter.'; }
    else {
        db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
        exit('<body style="background:#050505;color:#ffd400;font-family:monospace;padding:30px">Admin dibuat. HAPUS file setup.php sekarang, lalu <a href="login.php">login</a>.</body>');
    }
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup</title><style><?= theme_css() ?></style></head>
<body><div class="center"><form method="post" class="term"><div class="bar"><i></i><i></i><i></i><span>setup@absensi:~</span></div><div class="body">
<h2 class="cur">SETUP ADMIN</h2>
<?= $msg ? '<div class="msg err">' . h($msg) . '</div>' : '' ?><?= csrf_field() ?>
<label>username</label><input name="username" required>
<label>password (min. 8)</label><input name="password" type="password" required>
<p><button style="width:100%">&gt; create admin</button></p></div></form></div></body></html>
