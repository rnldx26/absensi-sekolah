<?php
function page_head(string $title) {
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $nav = ['index.php' => 'Dashboard', 'users.php' => 'Pengguna', 'report.php' => 'Laporan', 'logs.php' => 'Log Keamanan', 'settings.php' => 'Pengaturan'];
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' - Admin Absensi</title><style>
    *{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,sans-serif;background:#f4f6f8;color:#1f2933}
    header{background:#0f766e;color:#fff;padding:0 16px;display:flex;flex-wrap:wrap;align-items:center;gap:4px 18px}
    header b{padding:12px 0}header a{color:#d1fae5;text-decoration:none;padding:12px 4px}header a.on,header a:hover{color:#fff;border-bottom:3px solid #fff}
    main{max-width:1100px;margin:20px auto;padding:0 16px}
    .card{background:#fff;border-radius:10px;padding:18px;margin-bottom:18px;box-shadow:0 1px 3px #0001}
    table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #e5e9ee;vertical-align:top}
    th{background:#f0f4f7;font-size:13px}.scroll{overflow-x:auto}
    input,select{padding:8px;border:1px solid #c5ced6;border-radius:6px;width:100%;font:inherit}
    label{display:block;font-size:13px;margin:10px 0 3px;color:#52606d}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 14px}
    button,.btn{background:#0f766e;color:#fff;border:0;border-radius:6px;padding:8px 14px;cursor:pointer;font:inherit;text-decoration:none;display:inline-block}
    .btn2{background:#e5e9ee;color:#1f2933}.danger{background:#b91c1c}
    .msg{background:#ecfdf5;border:1px solid #6ee7b7;padding:10px 14px;border-radius:8px;margin-bottom:16px}
    .err{background:#fef2f2;border-color:#fca5a5}
    .tag{padding:2px 8px;border-radius:99px;font-size:12px;background:#e5e9ee}.ok{background:#d1fae5}.warn{background:#fef3c7}.bad{background:#fee2e2}
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:18px}.stats div{background:#fff;border-radius:10px;padding:14px;box-shadow:0 1px 3px #0001}
    .stats b{font-size:26px;display:block}small{color:#52606d}form.inline{display:inline}
    </style></head><body><header><b>Absensi</b>';
    foreach ($nav as $f => $n) echo '<a href="' . $f . '"' . ($cur === $f ? ' class="on"' : '') . '>' . $n . '</a>';
    echo '<a href="logout.php" style="margin-left:auto">Keluar</a></header><main>';
    if ($m = flash()) echo '<div class="msg' . (stripos($m, 'gagal') !== false || stripos($m, 'wajib') !== false || stripos($m, 'minimal') !== false ? ' err' : '') . '">' . h($m) . '</div>';
}
function page_foot() { echo '</main></body></html>'; }
