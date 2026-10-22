<?php
declare(strict_types=1);

const DB_PATH    = __DIR__ . '/database/aduan.sqlite';
const UPLOAD_DIR = __DIR__ . '/uploads/';
const PAGE_SIZE  = 10;
const STATUSES   = ['Verifikasi', 'Diproses', 'Selesai'];

date_default_timezone_set('Asia/Jakarta');

/** Koneksi PDO tunggal. Database dibuat dari schema.sql + seed bila belum ada. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $fresh = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ($fresh) {
        $pdo->exec(file_get_contents(__DIR__ . '/database/schema.sql'));
        require_once __DIR__ . '/database/seed.php';
        seedDatabase($pdo);
    }
    return $pdo;
}

function rows(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    return rows($sql, $params)[0] ?? null;
}

function scalar(string $sql, array $params = []): mixed
{
    $r = row($sql, $params);
    return $r ? array_values($r)[0] : null;
}

function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function nf(int|float $n): string { return number_format($n, 0, ',', '.'); }
function pct(int|float $n, int|float $total): string { return $total ? number_format($n / $total * 100, 1, ',', '.') . '%' : '0,0%'; }

function tanggal(string $dt): string
{
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($dt);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t) . ', ' . date('H.i', $t) . ' WIB';
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/** Bangun URL dengan parameter query yang digabung dengan query saat ini. */
function url(array $params = [], bool $merge = true): string
{
    $query = array_filter($merge ? array_merge($_GET, $params) : $params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($query);
}

function statusBadge(string $s): string
{
    $cls = ['Selesai' => 'done', 'Diproses' => 'work', 'Verifikasi' => 'verify'][$s] ?? 'verify';
    return '<span class="status ' . $cls . '">' . e($s) . '</span>';
}

function progressBar(int $value, string $status, string $id = ''): string
{
    $color = $status === 'Selesai' ? '#10b981' : '#2563eb';
    return '<div class="progress-mini"><div class="small muted">Progress ' . $value . '%</div>'
         . '<div class="track" role="progressbar" aria-label="Progress aduan ' . e($id) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $value . '">'
         . '<div class="fill" style="width:' . $value . '%;background:' . $color . '"></div></div></div>';
}

function statCard(string $label, string $value, string $note, string $tone = ''): string
{
    return '<div class="stat ' . $tone . '"><p class="small muted">' . e($label) . '</p><strong>' . e($value)
         . '</strong><p class="small muted">' . e($note) . '</p></div>';
}

function barRow(string $label, int $value, int $total, string $color): string
{
    $w = $total ? min(100, $value / $total * 100) : 0;
    return '<div class="bar-row"><div class="bar-label"><span>' . e($label) . '</span><strong>' . nf($value) . ' · ' . pct($value, $total)
         . '</strong></div><div class="track"><div class="fill" style="width:' . $w . '%;background:' . e($color) . '"></div></div></div>';
}

/** Perkiraan jenis kelamin dari nama depan (data simulasi; skema DB tidak menyimpan jenis kelamin). */
function genderOf(string $nama): string
{
    static $female = ['dewi', 'siti', 'nur', 'rina', 'sri', 'putri', 'ani', 'ayu', 'sari', 'wati', 'lestari', 'fitri', 'indah', 'maya', 'rini', 'yuni', 'tika', 'lia'];
    $first = mb_strtolower(explode(' ', trim($nama))[0] ?? '');
    return in_array($first, $female, true) ? 'Perempuan' : 'Laki-laki';
}

/** Avatar foto (sama seperti dashboard asli). $size: '', 'large', atau 'report-avatar'. */
function avatar(array $p, string $size = ''): string
{
    $female = genderOf($p['nama']) === 'Perempuan';
    $active = !isset($p['status_aktif']) || $p['status_aktif'];
    return '<span class="person-avatar photo-avatar ' . ($female ? 'female' : 'male') . ' ' . $size . '" role="img" aria-label="Potret sintetis '
         . ($female ? 'perempuan' : 'laki-laki') . ' untuk ' . e($p['nama']) . '"><img src="assets/img/avatar-' . ($female ? 'female' : 'male')
         . '.jpg" alt="" width="320" height="320" loading="lazy" decoding="async"><span class="avatar-status-dot ' . ($active ? 'active' : 'inactive') . '" aria-hidden="true"></span></span>';
}

function icon(string $name): string
{
    static $icons = null;
    $icons ??= require __DIR__ . '/partials/icons.php';
    return $icons[$name] ?? '';
}

function svgPin(): string
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>';
}

/** Tautan titik koordinat ke Google Maps. */
function pointLink(float $lat, float $lng, string $extraClass = ''): string
{
    return '<a class="location-link ' . $extraClass . '" href="https://www.google.com/maps?q=' . $lat . ',' . $lng . '" target="_blank" rel="noopener noreferrer">'
         . svgPin() . number_format($lat, 6, '.', '') . ', ' . number_format($lng, 6, '.', '') . '</a>';
}

/** Foto bukti: unggahan bila ada, jika tidak memakai foto contoh per kategori. */
function evidenceSrc(?string $url, string $kategori, string $prefix = 'aduan'): string
{
    return $url ?: 'assets/img/' . $prefix . '-' . $kategori . '.jpg';
}

/** Simpan unggahan foto ke /uploads dan catat di media_aduan. */
function saveUpload(string $field, string $idAduan, string $tipe): void
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 8 * 1024 * 1024) throw new RuntimeException('Foto gagal diunggah (maksimal 8 MB).');
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][mime_content_type($f['tmp_name'])] ?? null;
    if (!$ext) throw new RuntimeException('Gunakan foto JPG, PNG, atau WebP.');
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) throw new RuntimeException('Foto tidak dapat disimpan.');
    db()->prepare('INSERT INTO media_aduan (id_aduan,tipe_media,url_file,keterangan_media) VALUES (?,?,?,?)')
        ->execute([$idAduan, $tipe, 'uploads/' . $name, $tipe === 'foto_sesudah' ? 'Setelah penanganan' : 'Bukti awal']);
}

/* ---------- Helper tampilan tambahan (disamakan dengan dashboard asli) ---------- */

/** Contoh: "30 September 2026, 08.00 WIB". */
function tanggalPanjang(string $dt, bool $withTime = true): string
{
    static $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($dt);
    $s = date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
    return $withTime ? $s . ', ' . date('H.i', $t) . ' WIB' : $s;
}

function idPelapor(int $id): string { return 'PLR-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT); }

/** Foto bukti sebelum penanganan. $r berisi id_aduan, id_kategori, label_kategori, foto_sebelum. */
function reportPhotoHtml(array $r, bool $large = false): string
{
    $upload = !empty($r['foto_sebelum']);
    $src = evidenceSrc($r['foto_sebelum'] ?? null, $r['id_kategori'], 'aduan');
    $img = '<img src="' . e($src) . '" alt="' . ($upload ? 'Foto unggahan aduan' : 'Foto ilustrasi dummy kategori') . ' ' . e($r['label_kategori']) . '" loading="lazy" decoding="async">';
    if (!$large) $img = '<button class="report-photo-button" type="button" data-report="' . e($r['id_aduan']) . '" aria-label="Lihat foto dan detail aduan ' . e($r['id_aduan']) . '">' . $img . '</button>';
    return '<figure class="report-photo ' . ($large ? 'large' : '') . '">' . $img . '<figcaption><strong>Foto aduan</strong> · '
         . ($upload ? 'Unggahan pengadu' : 'Ilustrasi kategori · dummy AI') . '</figcaption></figure>';
}

/** Foto setelah penanganan; kosong bila belum selesai dan belum ada unggahan. */
function afterMediaHtml(array $r, bool $large = false): string
{
    $upload = !empty($r['foto_sesudah']);
    if (!$upload && $r['status'] !== 'Selesai') {
        return '<div class="after-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="2"/><path d="m3 17 6-5 4 4 3-3 5 4"/></svg><p>Belum ada foto / video setelah penanganan.</p><span>Tambahkan melalui Update Progress</span></div>';
    }
    $img = '<img src="' . e(evidenceSrc($r['foto_sesudah'] ?? null, $r['id_kategori'], 'sesudah')) . '" alt="Foto setelah penanganan ' . e($r['topik']) . '" loading="lazy" decoding="async">';
    if (!$large) $img = '<button class="report-photo-button" type="button" data-report="' . e($r['id_aduan']) . '" aria-label="Lihat perbandingan sebelum dan sesudah aduan ' . e($r['id_aduan']) . '">' . $img . '</button>';
    return '<figure class="report-photo after-photo ' . ($large ? 'large' : '') . '">' . $img . '<figcaption><strong>Setelah penanganan</strong> · '
         . ($upload ? 'Unggahan petugas' : 'Ilustrasi dummy AI') . '</figcaption></figure>';
}

/** Konfigurasi grafik tren untuk app.js. $view memakai nama halaman asli dashboard. */
function trendConfig(string $page, array $rows, array $kategoriList = [], ?string $fixedKategori = null, int $cameraTotal = 0): array
{
    $view = ['kelola-aduan' => 'kelola-laporan', 'kelola-pengadu' => 'kelola-pelapor', 'progress' => 'progress-laporan'][$page] ?? $page;
    $cfg = ['view' => $view, 'rows' => $rows, 'cameraTotal' => $cameraTotal, 'cumulative' => true, 'unit' => 'laporan'];
    if ($page === 'cctv') {
        return ['title' => 'Tren Ketersediaan Kamera', 'description' => 'Jumlah kamera online per hari dari fixture simulasi September 2026.', 'unit' => 'kamera', 'max' => $cameraTotal, 'cumulative' => false,
            'series' => [['key' => 'online', 'label' => 'Kamera Online', 'color' => '#2563eb', 'getter' => 'online'], ['key' => 'offline', 'label' => 'Kamera Offline', 'color' => '#f59e0b', 'getter' => 'offline']]] + $cfg;
    }
    if ($page === 'kepuasan') {
        return ['title' => 'Tren Kepuasan Harian', 'description' => 'Rata-rata skor harian dari respons survei kepuasan layanan.', 'unit' => 'skor / 5', 'max' => 5, 'cumulative' => false,
            'series' => [['key' => 'score', 'label' => 'Rata-rata Kepuasan', 'color' => '#8b5cf6', 'getter' => 'score']]] + $cfg;
    }
    if ($page === 'distribusi') {
        return ['title' => 'Tren Aduan Lintas Sektor', 'description' => 'Jumlah aduan menurut tanggal aduan dan filter yang dipilih.',
            'series' => array_map(fn($k) => ['key' => $k['id_kategori'], 'label' => $k['label_kategori'], 'color' => $k['warna_tema'], 'getter' => 'category'], $kategoriList)] + $cfg;
    }
    if ($page === 'progress') {
        return ['title' => 'Progress Rata-rata per Tanggal Aduan', 'description' => 'Progress saat ini dikelompokkan menurut tanggal aduan; bukan riwayat perkembangan harian satu aduan.', 'unit' => '%', 'max' => 100, 'cumulative' => false,
            'series' => [['key' => 'progress', 'label' => 'Rata-rata Progress', 'color' => '#10b981', 'getter' => 'progress']]] + $cfg;
    }
    $label = $fixedKategori ? ($kategoriList[array_search($fixedKategori, array_column($kategoriList, 'id_kategori'), true)]['label_kategori'] ?? '') : '';
    return ['title' => $fixedKategori ? 'Tren Aduan ' . $label : 'Tren Pengaduan Masyarakat',
        'description' => 'Jumlah menurut tanggal aduan. Seri selesai memakai status saat ini, bukan tanggal penyelesaian. Mengikuti filter tabel.',
        'series' => [['key' => 'total', 'label' => 'Aduan Masuk', 'color' => '#2563eb', 'getter' => 'total'], ['key' => 'done', 'label' => 'Sudah Selesai', 'color' => '#10b981', 'getter' => 'done']]] + $cfg;
}
