<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $code = 400, string $error = 'error', array $extra = []) {
    out(['ok' => false, 'message' => $msg, 'error' => $error] + $extra, $code);
}
function body(): array {
    $j = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($j) ? $j : [];
}
function same(string $a, string $b): bool {
    return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
}
function dev_headers(): array {
    return [
        'brand' => trim($_SERVER['HTTP_X_DEVICE_BRAND'] ?? ''),
        'model' => trim($_SERVER['HTTP_X_DEVICE_MODEL'] ?? ''),
        'aid'   => trim($_SERVER['HTTP_X_DEVICE_ID'] ?? ''),
    ];
}
// Pastikan perangkat yang dipakai = perangkat yang didaftarkan admin
function check_device(array $u, array $d) {
    if ($d['brand'] === '' || $d['model'] === '') {
        fail('Informasi perangkat tidak lengkap.', 403, 'device_missing');
    }
    if (!same($u['device_brand'], $d['brand']) || !same($u['device_model'], $d['model'])) {
        add_log((int)$u['id'], 'device_mismatch',
            "terdaftar: {$u['device_brand']} {$u['device_model']} | dipakai: {$d['brand']} {$d['model']}");
        fail('Perangkat ini tidak terdaftar untuk akun Anda. Hubungi admin.', 403, 'device_mismatch');
    }
    if (!empty($u['android_id']) && $d['aid'] !== $u['android_id']) {
        add_log((int)$u['id'], 'device_mismatch', "ANDROID_ID berbeda ({$d['brand']} {$d['model']})");
        fail('Perangkat ini tidak terdaftar untuk akun Anda. Hubungi admin.', 403, 'device_mismatch');
    }
}
function auth_user(): array {
    $t = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($t === '') fail('Sesi berakhir, silakan login ulang.', 401, 'unauthorized');
    $st = db()->prepare('SELECT * FROM users WHERE api_token = ? AND aktif = 1');
    $st->execute([hash('sha256', $t)]);
    $u = $st->fetch();
    if (!$u) fail('Sesi berakhir, silakan login ulang.', 401, 'unauthorized');
    check_device($u, dev_headers());
    return $u;
}
