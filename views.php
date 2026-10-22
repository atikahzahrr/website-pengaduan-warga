<?php
declare(strict_types=1);

/** Definisi halaman: judul, deskripsi, file konten, ikon, warna, dan grup menu. */
$views = [
    'ringkasan'     => ['Ringkasan Aduan Masyarakat', 'Analisis lintas sektor dari data dummy terintegrasi.', 'laporan', '▦', '#2563eb', 'Menu utama'],
    'distribusi'    => ['Distribusi Kategori Aduan', 'Pantau jumlah pengaduan dan penyelesaiannya pada setiap sektor.', 'laporan', '◔', '#8b5cf6', 'Menu utama'],
    'laporan'       => ['Log Pengaduan Lintas Sektor', 'Cari aduan, saring status, dan buka detail tindak lanjut.', 'laporan', '☰', '#0ea5e9', 'Menu utama'],
    'kelola-aduan'  => ['Kelola Aduan', 'Tambah dan edit aduan, atur kategori, serta arsipkan aduan.', 'laporan', '✎', '#6366f1', 'PENGELOLAAN'],
    'kelola-pengadu'=> ['Kelola Pengadu', 'Kelola profil pengadu dan lihat aduan yang terkait.', 'pengadu', '☻', '#ec4899', 'PENGELOLAAN'],
    'progress'      => ['Progress Aduan', 'Perbarui status, persentase progress, dan catatan tindak lanjut.', 'laporan', '↗', '#10b981', 'PENGELOLAAN'],
    'infrastruktur' => ['Monitoring Infrastruktur', 'Pengawasan jalan, drainase, lampu jalan, dan fasilitas umum.', 'laporan', '⚒', '#f97316', 'JENIS ADUAN'],
    'parkir'        => ['Monitoring Parkir & Lalu Lintas', 'Pengawasan parkir liar, kemacetan, rambu, dan penataan parkir.', 'laporan', 'P', '#6366f1', 'JENIS ADUAN'],
    'pendidikan'    => ['Monitoring Pendidikan', 'Pengawasan fasilitas sekolah dan tindak lanjut aduan pendidikan.', 'laporan', '🎓', '#059669', 'JENIS ADUAN'],
    'kebersihan'    => ['Monitoring Kebersihan', 'Pengawasan pengangkutan sampah dan kebersihan lingkungan.', 'laporan', '♻', '#14b8a6', 'JENIS ADUAN'],
    'cctv'          => ['Monitoring CCTV Publik', 'Status kamera dan kondisi lokasi menggunakan simulasi.', 'cctv', '◉', '#e11d48', 'LAINNYA'],
    'kepuasan'      => ['Monitoring Kepuasan Layanan', 'Ringkasan respons survei kepuasan layanan.', 'kepuasan', '☺', '#d97706', 'LAINNYA'],
    // Halaman form (tidak tampil di menu)
    'form-aduan'    => ['Form Aduan', 'Isi data aduan masyarakat.', 'form-aduan', '', '', ''],
    'form-pengadu'  => ['Form Pengadu', 'Isi profil pengadu.', 'form-pengadu', '', '', ''],
    'form-progress' => ['Update Progress', 'Perbarui tindak lanjut aduan.', 'form-progress', '', '', ''],
];
return $views;
