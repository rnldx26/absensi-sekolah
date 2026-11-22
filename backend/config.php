<?php
// ====== EDIT BAGIAN INI ======
const DB_HOST = 'localhost';
const DB_NAME = 'absensi';
const DB_USER = 'root';
const DB_PASS = '';
const TZ      = 'Asia/Jakarta';   // WIB. WITA: Asia/Makassar, WIT: Asia/Jayapura
// =============================

const UPLOAD_DIR = __DIR__ . '/uploads';
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

/** Simpan foto JPEG (base64) ke uploads/<sub>/. Return nama file atau null jika tidak valid. */
function save_photo(string $b64, string $sub): ?string {
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 500 || strlen($raw) > 400000) return null;
    $info = @getimagesizefromstring($raw);
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] < 64 || $info[1] < 64 || $info[0] > 1280 || $info[1] > 1280) return null;
    $dir = UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;
    $name = bin2hex(random_bytes(12)) . '.jpg';
    return file_put_contents("$dir/$name", $raw) === false ? null : $name;
}

function delete_photo(?string $name, string $sub): void {
    if ($name) @unlink(UPLOAD_DIR . '/' . $sub . '/' . basename($name));
}

/** Validasi + normalisasi (L2) embedding wajah dari aplikasi. */
function parse_embedding($arr): ?array {
    if (!is_array($arr) || count($arr) < 64 || count($arr) > 1024) return null;
    $v = []; $n = 0.0;
    foreach ($arr as $x) {
        if (!is_int($x) && !is_float($x)) return null;
        $x = (float)$x;
        if (!is_finite($x)) return null;
        $v[] = $x; $n += $x * $x;
    }
    if ($n < 1e-9) return null;
    $n = sqrt($n);
    return array_map(fn($x) => round($x / $n, 6), $v);
}

function cosine(array $a, array $b): float {
    if (count($a) !== count($b)) return -1.0;
    $d = 0.0; $na = 0.0; $nb = 0.0;
    foreach ($a as $i => $x) { $d += $x * $b[$i]; $na += $x * $x; $nb += $b[$i] * $b[$i]; }
    return ($na > 0 && $nb > 0) ? $d / sqrt($na * $nb) : -1.0;
}

function active_locations(): array {
    return db()->query('SELECT * FROM locations WHERE aktif = 1 ORDER BY nama')->fetchAll();
}
