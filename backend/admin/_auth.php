<?php
require __DIR__ . '/../config.php';
session_name('absensi_admin');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function require_admin() {
    if (empty($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Token CSRF tidak valid. Muat ulang halaman.'); }
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash(?string $m = null) {
    if ($m !== null) { $_SESSION['flash'] = $m; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
