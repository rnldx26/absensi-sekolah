<?php
require __DIR__ . '/_auth.php'; require __DIR__ . '/_layout.php';
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
    $err = 'ACCESS DENIED: username atau password salah.';
}
$tampil = setting('tampil_branding', '1') === '1';
$logo = setting('logo_file', '');
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login // Admin Absensi</title><style><?= theme_css() ?></style></head>
<body><div class="center"><form method="post" class="term"><div class="bar"><i></i><i></i><i></i><span>auth@absensi:~</span></div>
<div class="body">
<?php if ($tampil && $logo !== '' && is_file(UPLOAD_DIR . '/' . basename($logo))): ?><img class="logo" src="../api/logo.php?v=<?= h($logo) ?>" alt="logo"><?php endif; ?>
<h2 class="cur" style="text-align:center;margin-bottom:4px"><?= $tampil && setting('nama_sekolah', '') !== '' ? h(setting('nama_sekolah')) : 'ADMIN LOGIN' ?></h2>
<p class="dim" style="text-align:center;margin:0 0 14px">// restricted area &middot; authorized personnel only</p>
<?php if ($err) echo '<div class="msg err">' . h($err) . '</div>'; ?>
<?= csrf_field() ?>
<label>username</label><input name="username" required autofocus autocomplete="username">
<label>password</label><input name="password" type="password" required autocomplete="current-password">
<p><button style="width:100%">&gt; authenticate</button></p></div></form></div></body></html>
