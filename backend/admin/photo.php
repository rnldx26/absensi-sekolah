<?php
require __DIR__ . '/_auth.php'; require_admin();
$t = $_GET['t'] ?? ''; $id = (int)($_GET['id'] ?? 0);
if ($t === 'profile') { $q = 'SELECT face_photo AS f FROM users WHERE id = ?'; $sub = 'faces'; }
elseif ($t === 'absen') { $q = 'SELECT foto AS f FROM attendance WHERE id = ?'; $sub = 'absen'; }
else { http_response_code(404); exit; }
$st = db()->prepare($q); $st->execute([$id]);
$f = basename((string)$st->fetchColumn());
$p = UPLOAD_DIR . '/' . $sub . '/' . $f;
if ($f === '' || !is_file($p)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($p);
