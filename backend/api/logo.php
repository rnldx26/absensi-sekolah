<?php
require __DIR__ . '/../config.php';
$f = basename(setting('logo_file', ''));
$p = UPLOAD_DIR . '/' . $f;
if (setting('tampil_branding', '1') !== '1' || $f === '' || !is_file($p)) { http_response_code(404); exit; }
$types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'];
header('Content-Type: ' . ($types[pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($p);
