<?php
// ====== EDIT BAGIAN INI ======
const DB_HOST = 'localhost';
const DB_NAME = 'absensi';
const DB_USER = 'root';
const DB_PASS = '';
const TZ      = 'Asia/Jakarta';   // WIB. WITA: Asia/Makassar, WIT: Asia/Jayapura
// =============================

date_default_timezone_set(TZ);

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function setting(string $k, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT kunci, nilai FROM settings') as $r) $cache[$r['kunci']] = $r['nilai'];
    }
    return $cache[$k] ?? $default;
}

function jarak_meter(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371000;
    $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1); $dl = deg2rad($lng2 - $lng1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return 2 * $R * asin(min(1, sqrt($a)));
}

function add_log(?int $uid, string $event, string $detail = ''): void {
    $st = db()->prepare('INSERT INTO logs (user_id, event, detail, created_at) VALUES (?,?,?,?)');
    $st->execute([$uid, $event, mb_substr($detail, 0, 250), date('Y-m-d H:i:s')]);
}
