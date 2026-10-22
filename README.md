# Dashboard Aduan Masyarakat (PHP Native + SQLite)

## Menjalankan
    cd aduan
    php -S localhost:8000
Buka http://localhost:8000

Syarat: PHP 8.1+ dengan ekstensi `pdo_sqlite`, `mbstring`, `fileinfo`.
Database `database/aduan.sqlite` dibuat otomatis dari `database/schema.sql` + data simulasi (`database/seed.php`) saat pertama dibuka.
Untuk mengulang dari awal, hapus file `database/aduan.sqlite`.

## Struktur
    index.php          router + layout
    config.php         koneksi PDO, helper (e, nf, pct, flash, url, upload)
    views.php          daftar halaman & menu
    actions.php        handler POST (CSRF, validasi, simpan)
    pages/             laporan, pengadu, cctv, kepuasan, form-*
    partials/          header & footer
    assets/            style.css, app.js
    database/          schema.sql, seed.php
    uploads/           foto bukti (tercatat di tabel media_aduan)

## Tampilan
Tampilan disamakan dengan Dashboard Monitoring Aduan Warga (versi HTML tunggal):
`assets/style.css` memakai CSS dashboard asli (+ blok penyesuaian PHP di bagian bawah),
`assets/app.js` memuat tema, dialog detail, dan grafik tren (data dikirim dari PHP lewat `window.TREND_CONFIG`),
`assets/img/` dan `assets/video/` berisi foto avatar, foto aduan/sesudah, serta video contoh CCTV.
