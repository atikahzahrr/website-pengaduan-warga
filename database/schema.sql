PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id_user INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    nama_lengkap TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin' CHECK(role IN ('superadmin','admin')),
    dinas TEXT,
    status_aktif INTEGER NOT NULL DEFAULT 1,
    terakhir_masuk DATETIME,
    dibuat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pelapor (
    id_pelapor INTEGER PRIMARY KEY AUTOINCREMENT,
    nik TEXT UNIQUE NOT NULL,
    nama TEXT NOT NULL,
    avatar_tone TEXT,
    alamat TEXT,
    latitude REAL,
    longitude REAL,
    status_aktif INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS kategori (
    id_kategori TEXT PRIMARY KEY,
    label_kategori TEXT NOT NULL,
    dinas_penanggung_jawab TEXT NOT NULL,
    warna_tema TEXT
);

CREATE TABLE IF NOT EXISTS aduan (
    id_aduan TEXT PRIMARY KEY,
    id_pelapor INTEGER NOT NULL,
    id_kategori TEXT NOT NULL,
    topik TEXT NOT NULL,
    deskripsi TEXT,
    lokasi_kejadian TEXT,
    status TEXT CHECK(status IN ('Verifikasi', 'Diproses', 'Selesai')) DEFAULT 'Verifikasi',
    tanggal_aduan DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_arsip INTEGER DEFAULT 0,
    FOREIGN KEY (id_pelapor) REFERENCES pelapor(id_pelapor) ON DELETE CASCADE,
    FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS media_aduan (
    id_media INTEGER PRIMARY KEY AUTOINCREMENT,
    id_aduan TEXT NOT NULL,
    tipe_media TEXT CHECK(tipe_media IN ('foto_sebelum', 'foto_sesudah', 'video')),
    url_file TEXT NOT NULL,
    keterangan_media TEXT,
    FOREIGN KEY (id_aduan) REFERENCES aduan(id_aduan) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS progres_aduan (
    id_progres INTEGER PRIMARY KEY AUTOINCREMENT,
    id_aduan TEXT NOT NULL,
    status_tahapan TEXT NOT NULL,
    persentase_progres INTEGER CHECK(persentase_progres BETWEEN 0 AND 100),
    catatan_petugas TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_aduan) REFERENCES aduan(id_aduan) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cctv_publik (
    id_cctv TEXT PRIMARY KEY,
    nama_kamera TEXT NOT NULL,
    lokasi_detail TEXT,
    dinas_pengampu TEXT,
    status_online INTEGER DEFAULT 1,
    kondisi_arus TEXT
);

CREATE TABLE IF NOT EXISTS survei_kepuasan (
    id_survei INTEGER PRIMARY KEY AUTOINCREMENT,
    id_pelapor INTEGER,
    skor INTEGER CHECK(skor BETWEEN 1 AND 5),
    label_kepuasan TEXT,
    emoji TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pelapor) REFERENCES pelapor(id_pelapor) ON DELETE SET NULL
);
