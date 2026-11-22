# Absensi Sekolah/Madrasah v2 (Android native + admin PHP)

```
backend/   admin web + API (PHP 7.4+, MySQL/MariaDB, ekstensi zip untuk impor .xlsx)
android/   aplikasi Android native (Kotlin, minSdk 24)
.github/   workflow build APK (GitHub Actions)
```

## Fitur
- Login terikat perangkat (brand + model + ID unik), pop-up bila perangkat berbeda
- Face recognition: pengguna mendaftarkan wajah saat login pertama, absen wajib verifikasi wajah (kedip = liveness)
- Foto absen + foto pendaftaran tampil di dashboard, pengguna, dan laporan admin
- Banyak lokasi absen, admin bebas mengaktifkan/menonaktifkan
- Deteksi mock location + pop-up berisi nama aplikasi pemalsunya
- Impor pengguna massal lewat template Excel, nama instansi + logo di layar login (web dan Android)
- Tema hitam-kuning bergaya terminal

## 1. Backend
**Instalasi baru:** impor `backend/schema.sql`.
**Upgrade dari v1:** jalankan `backend/migrate_v2.sql` SEKALI (lokasi lama otomatis menjadi satu lokasi aktif).

1. Edit `backend/config.php` (DB, zona waktu).
2. Upload isi `backend/` ke hosting HTTPS. Pastikan folder `uploads/` bisa ditulis oleh PHP (chmod 755/775) dan file `uploads/.htaccess` ikut ter-upload (foto hanya diakses lewat PHP admin).
3. Buka `/admin/setup.php`, buat admin, lalu HAPUS `setup.php`.
4. Menu **Lokasi**: tambah titik absen. Menu **Pengaturan**: nama instansi, logo, jam kerja, ambang wajah.

## 2. Model wajah (WAJIB sebelum build APK)
Aplikasi memakai model **MobileFaceNet TFLite float32** (input 112x112 RGB, output 128 atau 192 dimensi, terdeteksi otomatis).
Model tidak disertakan karena lisensi bobot model berbeda-beda. Cari sendiri model MobileFaceNet berformat `.tflite` dari sumber yang lisensinya sesuai kebutuhan Anda, ganti namanya menjadi:

`android/app/src/main/assets/mobile_face_net.tflite`

Normalisasi piksel default `(px - 128) / 128` (umum untuk model `mobile_face_net.tflite` 192-d). Jika model Anda memakai normalisasi lain (mis. `/255`), ubah `MEAN` dan `STD` di `FaceEmbedder.kt`. Model quantized (uint8) tidak didukung.
Workflow GitHub Actions akan gagal dengan pesan jelas bila file model belum ada.

## 3. Android
1. Ganti `API_BASE` di `android/app/build.gradle.kts` (HTTPS, akhiri `/`).
2. Push ke GitHub, jalankan workflow "Build APK", unduh `absensi-apk`.

## 4. Alur pengguna
1. Admin menambah pengguna (satu per satu atau impor Excel) dengan brand + model perangkat.
2. Pengguna login di HP terdaftar, lalu diminta mendaftarkan wajah (kedip mata saat diminta).
3. Absen: GPS dicek (lokasi aktif, radius, mock) -> verifikasi wajah -> data dicocokkan di server.
4. Salah HP: pop-up "Perangkat tidak terdaftar". Lokasi palsu: pop-up dengan nama aplikasinya.

## Kalibrasi ambang wajah
Default kecocokan (cosine) 0,60. Setelah beberapa pengguna mencoba, lihat kolom "skor wajah" di Laporan dan Log (kejadian "Wajah tidak cocok"), lalu atur di Pengaturan. Terlalu rendah = orang lain bisa lolos, terlalu tinggi = pemilik sendiri sering ditolak.

## Batasan yang perlu diketahui
- Liveness (kedip) berjalan di aplikasi, server tidak bisa memverifikasinya. Foto statis biasanya gagal, tetapi video/rekaman wajah atau APK yang dimodifikasi bisa mengelabui. Untuk keamanan lebih tinggi perlu model anti-spoof tambahan.
- Deteksi mock location juga di sisi aplikasi: HP root/Xposed/APK modifikasi bisa melewatinya. Nama aplikasi pemalsu diambil dari aplikasi pengguna yang meminta izin mock location; pada beberapa versi Android aplikasi yang aktif tidak bisa dipastikan, maka ditampilkan daftar kandidat.
- Izin `QUERY_ALL_PACKAGES` dipakai untuk menyebut nama aplikasi mock. Ini tidak diizinkan untuk Google Play, tetapi aman untuk APK yang dipasang manual.
- Template Excel dibaca tanpa library tambahan; maksimal 300 baris per impor, NIP yang sudah ada dilewati.
- Ganti HP: admin ubah brand/model atau klik "Reset perangkat". Wajah tetap berlaku; "Reset wajah" hanya bila perlu daftar ulang.
- Belum ada: hari libur/akhir pekan, izin/sakit.
