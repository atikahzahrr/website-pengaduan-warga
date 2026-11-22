# Dashboard Aduan Masyarakat (PHP Native + SQLite)

## Menjalankan dashboard
    cd aduan
    php -S localhost:8000
Buka http://localhost:8000

## Menjalankan API untuk aplikasi mobile
Aplikasi mobile (pengaduan-baru) memanggil API di port 3000, jadi jalankan server dengan router:

    cd aduan
    set TOKEN_SECRET=ganti-dengan-rahasia-panjang
    set ADMIN_KEY=ganti-kunci-admin
    php -S 0.0.0.0:3000 router.php

Router ini juga melayani dashboard, jadi satu server cukup untuk keduanya.
Di Windows, ganti `set` dengan `$env:` (PowerShell) bila perlu. Di Laragon, atur variabel ini di menu Preferences > Environment.

Syarat: PHP 8.1+ dengan ekstensi `pdo_sqlite`, `sqlite3`, `mbstring`, `fileinfo`.

### php.ini (wajib untuk unggah video)
    upload_max_filesize = 32M
    post_max_size = 40M
    max_file_uploads = 20

Tanpa ini, unggahan video 30 MB akan ditolak PHP sebelum sampai ke aplikasi.

## Fitur API (setara server/server.js pada pengaduan-baru)
- Warga: daftar (`POST /api/register`, NIK 16 digit), masuk (`POST /api/login`, token 7 hari, terkunci 5 menit setelah 5 kali salah).
- Profil: `GET/PATCH /api/me`, `POST/DELETE /api/me/foto` (maks 4 MB), `POST /api/me/password`.
- Aduan: `GET/POST /api/aduan` (foto maks 5 x 2 MB, video maks 1 x 30 MB), `PATCH /api/aduan/{id}` dan `DELETE /api/aduan/{id}` hanya selama status Verifikasi. Pembatalan mengarsipkan aduan dan mencatat progres "Dibatalkan".
- Progres dan notifikasi: `GET /api/aduan/{id}/progres`, `GET /api/notifikasi`.
- Lainnya: `GET /api/kategori`, `GET /api/cctv`, `POST /api/survei`.
- Admin (header `x-admin-key`): `GET /api/admin/aduan`, `GET /api/admin/aduan/{id}`, `GET /api/admin/statistik`, `PATCH /api/admin/aduan/{id}/status`, `PATCH /api/admin/aduan/{id}/arsip`, `DELETE /api/admin/aduan/{id}`, `POST /api/admin/aduan/{id}/media`, `PUT /api/admin/cctv/{id}`, `GET /api/admin/pelapor`.

Catatan:
- Status "Selesai" otomatis 100%. Catatan petugas boleh kosong.
- Label kategori untuk aplikasi mobile: Infrastruktur, Parkir Liar, Pendidikan, Kebersihan.
- Akun warga memakai tabel `pelapor` (kolom username dan password). Akun admin dashboard tetap di tabel `users`.
- Pelapor yang dibuat dari dashboard belum punya username dan kata sandi, sehingga belum bisa masuk ke aplikasi. Beri akses lewat registrasi aplikasi atau tambahkan username dan kata sandi lewat database.

## Struktur
    index.php          router dashboard + layout
    router.php         penjalan server (dashboard + API)
    api/index.php      API untuk aplikasi mobile
    config.php         koneksi PDO, helper, migrasi skema
    views.php          daftar halaman & menu
    actions.php        handler POST dashboard (CSRF, validasi, simpan)
    auth.php           sesi login admin dashboard
    pages/             laporan, pengadu, cctv, kepuasan, admin, akun, form-*
    partials/          header, footer, ikon
    assets/            style.css, app.js
    database/          schema.sql, seed.php
    uploads/           foto dan video (tercatat di tabel media_aduan)
