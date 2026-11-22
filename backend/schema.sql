-- Instalasi BARU (v2). Jika sudah memakai v1, jalankan migrate_v2.sql saja.
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nip VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  jabatan VARCHAR(50) NOT NULL DEFAULT 'Guru',
  password_hash VARCHAR(255) NOT NULL,
  device_brand VARCHAR(60) NOT NULL,
  device_model VARCHAR(80) NOT NULL,
  android_id VARCHAR(64) NULL,
  api_token CHAR(64) NULL,
  face_template MEDIUMTEXT NULL,
  face_photo VARCHAR(80) NULL,
  face_at DATETIME NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (api_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  kunci VARCHAR(50) PRIMARY KEY,
  nilai VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  radius INT NOT NULL DEFAULT 100,
  aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  tanggal DATE NOT NULL,
  tipe ENUM('masuk','pulang') NOT NULL,
  waktu DATETIME NOT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  jarak INT NOT NULL,
  akurasi INT NOT NULL,
  status VARCHAR(20) NOT NULL,
  lokasi_nama VARCHAR(100) NULL,
  foto VARCHAR(80) NULL,
  face_score DECIMAL(5,3) NULL,
  UNIQUE KEY uq_absen (user_id, tanggal, tipe),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  event VARCHAR(40) NOT NULL,
  detail VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  INDEX (event, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (kunci, nilai) VALUES
 ('nama_sekolah','Madrasah Contoh'), ('tampil_branding','1'),
 ('jam_masuk','07:00'), ('toleransi','10'), ('jam_pulang','13:00'),
 ('max_akurasi','50'), ('face_threshold','0.60');
