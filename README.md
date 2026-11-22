# Aplikasi Absensi Sekolah/Madrasah

Struktur:
- `backend/`  : admin web + API (PHP 7.4+, MySQL/MariaDB)
- `android/`  : aplikasi Android native (Kotlin, minSdk 24)
- `.github/workflows/build-apk.yml` : build APK otomatis di GitHub Actions

## 1. Pasang backend
1. Buat database MySQL, impor `backend/schema.sql`.
2. Edit `backend/config.php` (DB_HOST, DB_NAME, DB_USER, DB_PASS, TZ).
3. Upload isi folder `backend/` ke hosting (mis. `https://domain-anda.sch.id/absensi/`). HTTPS wajib.
4. Buka `/absensi/admin/setup.php`, buat akun admin, lalu HAPUS file `setup.php`.
5. Login di `/absensi/admin/login.php`, menu Pengaturan: isi koordinat sekolah, radius, jam masuk/pulang.
   (Tombol "Gunakan lokasi perangkat ini" mengisi koordinat otomatis bila dibuka dari HP/laptop yang ada di sekolah.)

## 2. Build aplikasi Android
1. Edit `android/app/build.gradle.kts`, ganti `API_BASE` menjadi `https://domain-anda.sch.id/absensi/api/` (akhiri dengan `/`).
2. Push repo ke GitHub, jalankan workflow "Build APK", unduh `absensi-apk` dari Artifacts.
   (Atau buka folder `android/` di Android Studio, Build > Build APK.)

## 3. Mendaftarkan pengguna
Menu Pengguna > isi NIP, nama, password, **brand** dan **model** perangkat.
Cara termudah: pasang APK di HP pengguna, brand dan model tampil di layar login. Salin persis.
- Brand = `ro.product.brand`, model = `ro.product.model` (sama dengan `Build.BRAND` / `Build.MODEL`).
- Saat login pertama, `ANDROID_ID` HP ikut terikat otomatis, jadi HP lain dengan tipe yang sama tetap ditolak.
- Pengguna ganti HP: admin ubah brand/model (atau klik "Reset perangkat"), pengguna login ulang di HP baru.

## Lapisan keamanan
- Server menghitung ulang jarak (haversine) dan jam, tidak percaya data waktu dari HP.
- Mock location: aplikasi membaca `Location.isMock` (Android 12+) / `isFromMockProvider` (di bawahnya) dan melaporkannya; server menolak dan mencatat di Log Keamanan.
- Akurasi GPS dibatasi (default maks 50 m), token login disimpan hash SHA-256, login dibatasi 5x gagal/10 menit.
- Setiap request membawa brand, model, ANDROID_ID, dicocokkan ulang dengan data admin.

## Batasan yang perlu diketahui
- Deteksi mock location di sisi aplikasi bisa dilewati pada HP yang di-root / Xposed / APK yang dimodifikasi. Server tidak bisa memverifikasi flag itu. Log Keamanan membantu melihat pola mencurigakan.
- Brand + model saja tidak unik (banyak HP sama tipe); itu sebabnya ditambah ikatan ANDROID_ID.
- ANDROID_ID bisa berubah setelah factory reset; admin cukup klik "Reset perangkat".
- Belum ada: hari libur/akhir pekan, izin/sakit, lebih dari satu titik lokasi. Bisa ditambahkan.
