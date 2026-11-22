<?php
function theme_css(): string {
    return <<<'CSS'
:root{--bg:#050505;--panel:#0c0c0c;--line:#3a3300;--y:#ffd400;--y2:#a88f00;--tx:#e6e6e6;--dim:#8b8b8b;--red:#ff4d4d;--grn:#4ade80}
*{box-sizing:border-box}
html{background:var(--bg)}
body{margin:0;color:var(--tx);font:14px/1.55 ui-monospace,"JetBrains Mono","Fira Code",Consolas,Menlo,monospace;
 background:linear-gradient(rgba(255,212,0,.035) 1px,transparent 1px) 0 0/30px 30px,linear-gradient(90deg,rgba(255,212,0,.035) 1px,transparent 1px) 0 0/30px 30px,var(--bg);min-height:100vh}
body::after{content:"";position:fixed;inset:0;pointer-events:none;background:repeating-linear-gradient(0deg,rgba(0,0,0,.18) 0 1px,transparent 1px 3px);opacity:.5}
a{color:var(--y)}
header{position:sticky;top:0;z-index:5;background:rgba(5,5,5,.96);border-bottom:1px solid var(--y2);padding:0 16px;display:flex;flex-wrap:wrap;align-items:center;gap:2px 6px}
.brand{color:var(--y);font-weight:700;letter-spacing:1px;padding:12px 14px 12px 0;text-shadow:0 0 10px rgba(255,212,0,.6)}
.brand small{color:var(--dim);font-weight:400;text-shadow:none;margin-left:8px}
header a.nv{color:var(--dim);text-decoration:none;padding:6px 10px;text-transform:uppercase;font-size:12px;letter-spacing:1px;border:1px solid transparent;border-radius:3px}
header a.nv:hover{color:var(--y);border-color:var(--line)}
header a.nv.on{background:var(--y);color:#000;font-weight:700}
main{max-width:1180px;margin:22px auto;padding:0 16px;position:relative;z-index:1}
h1,h2,h3{color:var(--y);font-weight:700;margin:0 0 12px}
h3::before{content:"> ";color:var(--y2)}
.card{background:var(--panel);border:1px solid var(--line);border-radius:4px;padding:18px;margin-bottom:18px;box-shadow:0 0 0 1px #000 inset,0 0 24px rgba(255,212,0,.04)}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #1d1a00;vertical-align:middle}
th{color:var(--y2);font-size:11px;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--line)}
tr:hover td{background:rgba(255,212,0,.05)}
.scroll{overflow-x:auto}
input,select{width:100%;padding:9px 10px;background:#000;color:#ffe45c;border:1px solid #5c4f00;border-radius:3px;font:inherit;caret-color:var(--y)}
input[type=checkbox]{width:auto;accent-color:var(--y)}
input:focus,select:focus{outline:none;border-color:var(--y);box-shadow:0 0 0 2px rgba(255,212,0,.25)}
input::placeholder{color:#5f5a35}
label{display:block;font-size:11px;margin:12px 0 4px;color:var(--y2);text-transform:uppercase;letter-spacing:1px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:0 16px}
button,.btn{background:var(--y);color:#000;border:1px solid var(--y);border-radius:3px;padding:8px 14px;cursor:pointer;font:inherit;font-weight:700;text-decoration:none;display:inline-block;text-transform:uppercase;font-size:12px;letter-spacing:1px}
button:hover,.btn:hover{box-shadow:0 0 14px rgba(255,212,0,.5)}
.btn2{background:transparent;color:var(--y)}
.danger{background:transparent;color:var(--red);border-color:var(--red)}
.danger:hover{box-shadow:0 0 14px rgba(255,77,77,.4)}
.sm{padding:4px 8px;font-size:11px;margin:2px 0}
.msg{background:#0d0b00;border:1px solid var(--y);color:var(--y);padding:10px 14px;border-radius:3px;margin-bottom:16px}
.msg::before{content:"[i] "}
.err{border-color:var(--red);color:var(--red);background:#120505}
.err::before{content:"[!] "}
.tag{display:inline-block;padding:1px 8px;border:1px solid var(--y2);border-radius:2px;font-size:11px;color:var(--y);text-transform:uppercase;letter-spacing:1px}
.tag.ok{color:var(--grn);border-color:var(--grn)}.tag.warn{color:var(--y);border-color:var(--y)}.tag.bad{color:var(--red);border-color:var(--red)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:18px}
.stats div{background:var(--panel);border:1px solid var(--line);border-left:3px solid var(--y);padding:12px 14px;color:var(--dim);font-size:12px;text-transform:uppercase;letter-spacing:1px}
.stats b{display:block;font-size:30px;color:var(--y);text-shadow:0 0 12px rgba(255,212,0,.4);letter-spacing:0}
small,.dim{color:var(--dim)}
form.inline{display:inline}
.av{width:44px;height:44px;border-radius:3px;object-fit:cover;border:1px solid var(--y2);background:#000;display:block}
.av.zoom{cursor:zoom-in}
.noav{width:44px;height:44px;border:1px dashed #4b4300;border-radius:3px;display:flex;align-items:center;justify-content:center;color:#5c5200;font-size:10px}
.lb{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:50;display:flex;align-items:center;justify-content:center;cursor:zoom-out}
.lb img{max-width:92vw;max-height:88vh;border:2px solid var(--y);box-shadow:0 0 40px rgba(255,212,0,.35)}
.term{width:100%;max-width:400px;background:var(--panel);border:1px solid var(--y2);border-radius:5px;box-shadow:0 0 40px rgba(255,212,0,.12)}
.term .bar{display:flex;align-items:center;gap:6px;padding:8px 12px;border-bottom:1px solid var(--line);color:var(--dim);font-size:12px}
.term .bar i{width:10px;height:10px;border-radius:50%;background:#2a2500;border:1px solid var(--y2)}
.term .bar span{margin-left:8px}
.term .body{padding:24px}
.cur::after{content:"_";animation:bl 1s steps(1) infinite;color:var(--y)}
@keyframes bl{50%{opacity:0}}
.center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;position:relative;z-index:1}
.logo{max-width:96px;max-height:96px;display:block;margin:0 auto 12px}
.pw{color:var(--y);font-weight:700}
CSS;
}

function page_head(string $title) {
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $nav = ['index.php' => 'Dashboard', 'users.php' => 'Pengguna', 'lokasi.php' => 'Lokasi', 'report.php' => 'Laporan', 'logs.php' => 'Log', 'settings.php' => 'Pengaturan'];
    $inst = setting('nama_sekolah', '');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' // Admin Absensi</title><style>' . theme_css() . '</style></head><body><header>';
    echo '<span class="brand">ABSENSI://<small>' . h($inst) . '</small></span>';
    foreach ($nav as $f => $n) echo '<a class="nv' . ($cur === $f || ($f === 'users.php' && $cur === 'import.php') ? ' on' : '') . '" href="' . $f . '">' . $n . '</a>';
    echo '<a class="nv" href="logout.php" style="margin-left:auto">Keluar</a></header><main>';
    if ($m = flash()) {
        $bad = stripos($m, 'gagal') !== false || stripos($m, 'wajib') !== false || stripos($m, 'minimal') !== false;
        echo '<div class="msg' . ($bad ? ' err' : '') . '">' . h($m) . '</div>';
    }
}
function page_foot() {
    echo '</main><script>document.addEventListener("click",function(e){var t=e.target;if(t.classList&&t.classList.contains("zoom")){var o=document.createElement("div");o.className="lb";var i=document.createElement("img");i.src=t.getAttribute("data-full");o.appendChild(i);o.onclick=function(){o.remove()};document.body.appendChild(o);}});</script></body></html>';
}
/** Avatar kecil dengan zoom. $t = profile|absen */
function avatar(?string $file, string $t, int $id): string {
    if (!$file) return '<div class="noav">N/A</div>';
    $u = 'photo.php?t=' . $t . '&id=' . $id;
    return '<img class="av zoom" loading="lazy" src="' . $u . '" data-full="' . $u . '" alt="foto">';
}
function save_setting(string $k, string $v) {
    db()->prepare('INSERT INTO settings (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)')->execute([$k, $v]);
}
