<?php
declare(strict_types=1);

/** Mengisi data simulasi awal (dipanggil otomatis saat database baru dibuat). */
function seedDatabase(PDO $pdo): void
{
    $pdo->prepare('INSERT INTO users (username,nama_lengkap,password_hash,role,dinas) VALUES (?,?,?,?,?)')
        ->execute([
            DEFAULT_ADMIN_USERNAME,
            'Administrator Super',
            password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'superadmin',
            'Pusat Kendali Layanan',
        ]);

    $kategori = [
        ['infrastruktur', 'Infrastruktur & Jalan Rusak',    'Dinas Pekerjaan Umum (PU)',    '#2563eb'],
        ['pendidikan',    'Fasilitas Pendidikan & Sekolah', 'Dinas Pendidikan (Disdik)',    '#10b981'],
        ['parkir',        'Parkir & Lalu Lintas',           'Dinas Perhubungan (Dishub)',   '#6366f1'],
        ['kebersihan',    'Kebersihan Lingkungan & Sampah', 'Dinas Lingkungan Hidup (DLH)', '#f59e0b'],
    ];
    $st = $pdo->prepare('INSERT INTO kategori VALUES (?,?,?,?)');
    foreach ($kategori as $k) $st->execute($k);

    $pelapor = [
        ['Budi Santoso', '#2563eb'], ['Nur Aini', '#ec4899'], ['Agus Pratama', '#0ea5e9'],
        ['Rina Wulandari', '#8b5cf6'], ['Dedi Kurniawan', '#10b981'], ['Sri Wahyuni', '#f59e0b'],
    ];
    $st = $pdo->prepare('INSERT INTO pelapor (nik,nama,avatar_tone,alamat,latitude,longitude) VALUES (?,?,?,?,?,?)');
    foreach ($pelapor as $i => [$nama, $tone]) {
        $st->execute([str_pad((string)($i + 1), 16, '0', STR_PAD_LEFT), $nama, $tone,
            'Jl. Melati No. ' . (10 + $i) . ', Kecamatan Sentral', -7.250445 + $i * .002, 112.768845 + $i * .0015]);
    }

    $topik = [
        'infrastruktur' => ['Jalan berlubang', 'Drainase tersumbat', 'Lampu jalan padam', 'Jembatan perlu perbaikan'],
        'pendidikan'    => ['Atap ruang kelas bocor', 'Perbaikan toilet sekolah', 'Fasilitas belajar rusak', 'Pagar sekolah rusak'],
        'parkir'        => ['Parkir liar di bahu jalan', 'Kemacetan persimpangan', 'Rambu lalu lintas rusak', 'Penataan zona parkir'],
        'kebersihan'    => ['Sampah belum diangkut', 'Kontainer sampah penuh', 'Pembersihan saluran air', 'Penumpukan sampah pasar'],
    ];
    $lokasi = ['Kelurahan Melati', 'Kecamatan Sentral', 'Jl. Sudirman', 'Kelurahan Klayan', 'Kecamatan Utara', 'Kelurahan Sukamaju'];
    $statusList = ['Verifikasi', 'Diproses', 'Selesai', 'Selesai'];
    $persen = ['Verifikasi' => 25, 'Diproses' => 65, 'Selesai' => 100];

    $aduan = $pdo->prepare('INSERT INTO aduan (id_aduan,id_pelapor,id_kategori,topik,deskripsi,lokasi_kejadian,status,tanggal_aduan) VALUES (?,?,?,?,?,?,?,?)');
    $prog  = $pdo->prepare('INSERT INTO progres_aduan (id_aduan,status_tahapan,persentase_progres,catatan_petugas,updated_at) VALUES (?,?,?,?,?)');
    $n = 0;
    foreach (array_keys($topik) as $kat) {
        for ($i = 0; $i < 15; $i++, $n++) {
            $id = 'ADR-' . (9100 - $n);
            $status = $statusList[($n * 7 + $i) % 4];
            $tgl = sprintf('2026-09-%02d 0%d:30:00', 1 + $n % 28, 8 + $n % 2);
            $aduan->execute([$id, 1 + $n % 6, $kat, $topik[$kat][$i % 4],
                'Warga melaporkan kondisi yang memerlukan peninjauan dan penanganan petugas.',
                $lokasi[$n % 6], $status, $tgl]);
            $prog->execute([$id, $status, $persen[$status], 'Status awal dari data simulasi.', $tgl]);
        }
    }

    $cctv = $pdo->prepare('INSERT INTO cctv_publik VALUES (?,?,?,?,?,?)');
    foreach ([
        ['CAM-01', 'Pasar Sentral', 'Area parkir pasar', 'Dishub', 1, 'Padat'],
        ['CAM-02', 'SDN 03 Melati', 'Gerbang sekolah', 'Disdik', 1, 'Lancar'],
        ['CAM-03', 'Taman Kota', 'Area fasilitas publik', 'Diskominfo', 0, 'Koneksi terputus'],
        ['CAM-04', 'Simpang Sudirman', 'Persimpangan utama', 'Dishub', 1, 'Lancar'],
    ] as $row) $cctv->execute($row);

    $survei = $pdo->prepare('INSERT INTO survei_kepuasan (id_pelapor,skor,label_kepuasan,emoji,created_at) VALUES (?,?,?,?,?)');
    $opsi = [2 => ['Kurang Puas', '😞', 3], 3 => ['Cukup Puas', '😐', 9], 4 => ['Puas', '😊', 60], 5 => ['Sangat Puas', '🤩', 128]];
    $n = 0;
    foreach ($opsi as $skor => [$label, $emoji, $jumlah]) {
        for ($i = 0; $i < $jumlah; $i++, $n++) {
            $survei->execute([1 + $n % 6, $skor, $label, $emoji, sprintf('2026-09-%02d 10:00:00', 1 + $n % 30)]);
        }
    }
}
