<?php
require __DIR__ . '/_boot.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method tidak diizinkan.', 405);
$u = auth_user();
if (!empty($u['face_template'])) fail('Wajah sudah terdaftar. Minta admin untuk mereset jika perlu mendaftar ulang.', 409, 'face_exists');

$b = body();
$emb = parse_embedding($b['embedding'] ?? null);
if (!$emb) fail('Data wajah tidak valid.', 422, 'face_invalid');
$foto = save_photo((string)($b['photo'] ?? ''), 'faces');
if (!$foto) fail('Foto wajah tidak valid.', 422, 'face_invalid');

// UPDATE bersyarat: mencegah dua pendaftaran bersamaan
$st = db()->prepare('UPDATE users SET face_template = ?, face_photo = ?, face_at = ? WHERE id = ? AND face_template IS NULL');
$st->execute([json_encode($emb), $foto, date('Y-m-d H:i:s'), $u['id']]);
if ($st->rowCount() !== 1) { delete_photo($foto, 'faces'); fail('Wajah sudah terdaftar.', 409, 'face_exists'); }
add_log((int)$u['id'], 'face_enroll', 'dim=' . count($emb));
out(['ok' => true, 'message' => 'Wajah berhasil didaftarkan.']);
