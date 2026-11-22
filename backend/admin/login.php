<?php
require __DIR__ . '/_auth.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $st->execute([trim($_POST['username'] ?? '')]);
    $a = $st->fetch();
    if ($a && password_verify($_POST['password'] ?? '', $a['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $a['id'];
        header('Location: index.php'); exit;
    }
    sleep(1);
    $err = 'Username atau password salah.';
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Admin</title><style>body{font:15px system-ui;background:#0f766e;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
form{background:#fff;padding:26px;border-radius:12px;width:320px}input{width:100%;padding:10px;margin:6px 0 14px;border:1px solid #c5ced6;border-radius:6px;font:inherit}
button{width:100%;padding:10px;background:#0f766e;color:#fff;border:0;border-radius:6px;font:inherit;cursor:pointer}.e{color:#b91c1c;margin-bottom:10px}</style></head>
<body><form method="post"><h2 style="margin-top:0">Admin Absensi</h2>
<?php if ($err) echo '<div class="e">' . htmlspecialchars($err) . '</div>'; ?>
<?= csrf_field() ?>
<label>Username</label><input name="username" required autofocus>
<label>Password</label><input name="password" type="password" required>
<button>Masuk</button></form></body></html>
