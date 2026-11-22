<?php
// Publik: nama instansi + logo untuk layar login aplikasi
require __DIR__ . '/_boot.php';
$on = setting('tampil_branding', '1') === '1';
$logo = setting('logo_file', '');
out([
    'ok' => true,
    'tampil' => $on,
    'nama' => $on ? setting('nama_sekolah', '') : '',
    'logo' => $on && $logo !== '' && is_file(UPLOAD_DIR . '/' . basename($logo)),
]);
