-- Upgrade dari v1 ke v2 (jalankan SEKALI)
ALTER TABLE users
  ADD COLUMN face_template MEDIUMTEXT NULL,
  ADD COLUMN face_photo VARCHAR(80) NULL,
  ADD COLUMN face_at DATETIME NULL;

ALTER TABLE attendance
  ADD COLUMN lokasi_nama VARCHAR(100) NULL,
  ADD COLUMN foto VARCHAR(80) NULL,
  ADD COLUMN face_score DECIMAL(5,3) NULL;

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  radius INT NOT NULL DEFAULT 100,
  aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pindahkan lokasi lama (v1) menjadi satu lokasi aktif
INSERT INTO locations (nama, lat, lng, radius, aktif)
SELECT 'Lokasi Utama',
       (SELECT CAST(nilai AS DECIMAL(10,7)) FROM settings WHERE kunci='lat'),
       (SELECT CAST(nilai AS DECIMAL(10,7)) FROM settings WHERE kunci='lng'),
       (SELECT CAST(nilai AS UNSIGNED) FROM settings WHERE kunci='radius'), 1
FROM DUAL
WHERE (SELECT COUNT(*) FROM settings WHERE kunci='lat' AND nilai <> '0') > 0;

DELETE FROM settings WHERE kunci IN ('lat','lng','radius');
INSERT INTO settings (kunci, nilai) VALUES ('tampil_branding','1'), ('face_threshold','0.60')
  ON DUPLICATE KEY UPDATE nilai = nilai;
