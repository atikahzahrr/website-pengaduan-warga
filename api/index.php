<?php
declare(strict_types=1);
/**
 * API untuk aplikasi mobile (Expo) dan pelengkap dashboard.
 * Meniru server/server.js dari pengaduan-baru: rute, aturan validasi, format respons,
 * dan batas unggahan sama. Dijalankan lewat router.php:  php -S 0.0.0.0:3000 router.php
 *
 * Variabel lingkungan (opsional):
 *   TOKEN_SECRET  rahasia penanda sesi warga (WAJIB diganti sebelum dipakai bersama)
 *   ADMIN_KEY     kunci untuk rute /api/admin/* (header x-admin-key)
 */

require __DIR__ . '/../config.php';

const MAX_GAGAL        = 5;          // percobaan salah sebelum dikunci
const KUNCI_MS         = 300000;     // lama kunci: 5 menit
const TOKEN_DAYS       = 7;
const MAKS_FOTO        = 5;
const MAKS_FOTO_BYTES  = 2097152;    // 2 MB per foto
const MAKS_VIDEO_BYTES = 31457280;   // 30 MB
const MAKS_AVATAR_BYTES = 4194304;   // 4 MB
const PELAPOR_AMAN     = 'id_pelapor, nik, nama, username, foto_url, avatar_tone, alamat, latitude, longitude, status_aktif';

/* ───────────── CORS & pembuka ───────────── */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-admin-key');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ambil bagian setelah "/api" (berfungsi juga bila aplikasi berada di sub-folder, mis. /aduan/api/...)
$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$pos  = strpos($path, '/api');
$rest = $pos !== false ? substr($path, $pos + 4) : $path;
$segmen = array_values(array_filter(explode('/', trim(rawurldecode($rest), '/')), 'strlen'));

try {
    route((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), $segmen);
    jsonOut(['error' => 'Rute tidak ditemukan'], 404);
} catch (Throwable $ex) {
    error_log('[API] ' . $ex->getMessage());
    jsonOut(['error' => 'Terjadi kesalahan di server.'], 500);
}

/* ───────────── ROUTER ───────────── */
function route(string $m, array $s): void
{
    if ($s === ['kategori'] && $m === 'GET') jsonOut(kategoriList());
    if ($s === ['cctv'] && $m === 'GET')     jsonOut(rows('SELECT * FROM cctv_publik'));
    if ($s === ['register'] && $m === 'POST') registerWarga();
    if ($s === ['login'] && $m === 'POST')    loginWarga();
    if (($s[0] ?? '') === 'admin') { adminRoute($m, array_slice($s, 1)); return; }

    // Rute berikut wajib login (token warga)
    $id = requireWarga();

    if ($s === ['me'] && $m === 'GET')            meGet($id);
    if ($s === ['me'] && $m === 'PATCH')          mePatch($id);
    if ($s === ['me', 'foto'] && $m === 'POST')   fotoUpload($id);
    if ($s === ['me', 'foto'] && $m === 'DELETE') fotoHapus($id);
    if ($s === ['me', 'password'] && $m === 'POST') gantiPassword($id);

    if ($s === ['aduan'] && $m === 'GET')  aduanSaya($id);
    if ($s === ['aduan'] && $m === 'POST') aduanBuat($id);
    if (count($s) === 2 && $s[0] === 'aduan' && $m === 'PATCH')  aduanEdit($id, $s[1]);
    if (count($s) === 2 && $s[0] === 'aduan' && $m === 'DELETE') aduanBatal($id, $s[1]);
    if (count($s) === 3 && $s[0] === 'aduan' && $s[2] === 'progres' && $m === 'GET') progresAduan($id, $s[1]);

    if ($s === ['notifikasi'] && $m === 'GET') notifikasi($id);
    if ($s === ['survei'] && $m === 'POST')    survei($id);
}

/* ───────────── UTILITAS ───────────── */
function jsonOut(mixed $data, int $kode = 200): void
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/** Isi body JSON (aplikasi) atau form biasa. */
function jsonBody(): array
{
    static $body = null;
    if ($body !== null) return $body;
    $body = [];
    if (str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (is_array($data)) $body = $data;
    } else {
        $body = $_POST;
    }
    return $body;
}

/** Ambil teks dari input; nilai yang bukan skalar dianggap kosong. */
function teks(mixed $v, int $maks = 0): string
{
    $s = is_scalar($v) ? trim((string)$v) : '';
    return $maks > 0 ? mb_substr($s, 0, $maks) : $s;
}

function nowMs(): int
{
    return (int)floor(microtime(true) * 1000);
}

function clientIp(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function apiSecret(): string
{
    return (string)(getenv('TOKEN_SECRET') ?: 'ganti-ini-sebelum-dipakai');
}

function adminKunci(): string
{
    return (string)(getenv('ADMIN_KEY') ?: 'admin123');
}

/** Ekspresi label kategori yang dipakai aplikasi mobile (sama dengan label di pengaduan-baru). */
function labelKategoriSql(): string
{
    return "CASE k.id_kategori WHEN 'infrastruktur' THEN 'Infrastruktur' WHEN 'parkir' THEN 'Parkir Liar'
            WHEN 'pendidikan' THEN 'Pendidikan' WHEN 'kebersihan' THEN 'Kebersihan' ELSE k.label_kategori END";
}

function aduanSql(): string
{
    return "SELECT a.id_aduan, a.id_pelapor, a.id_kategori, " . labelKategoriSql() . " AS label_kategori,
                   k.dinas_penanggung_jawab AS dinas, a.topik, a.deskripsi, a.lokasi_kejadian, a.status,
                   a.tanggal_aduan, a.is_arsip,
                   COALESCE((SELECT persentase_progres FROM progres_aduan p WHERE p.id_aduan = a.id_aduan
                             ORDER BY p.updated_at DESC, p.id_progres DESC LIMIT 1), 0) AS progres
            FROM aduan a JOIN kategori k ON k.id_kategori = a.id_kategori";
}

function kategoriAda(string $id): bool
{
    return $id !== '' && (bool)row('SELECT 1 AS ok FROM kategori WHERE id_kategori = ?', [$id]);
}

function kategoriList(): array
{
    return rows('SELECT k.id_kategori, ' . labelKategoriSql() . ' AS label_kategori,
                        k.dinas_penanggung_jawab, k.warna_tema FROM kategori k');
}

function attachMedia(array $list): array
{
    foreach ($list as &$r) {
        $r['media'] = rows('SELECT * FROM media_aduan WHERE id_aduan = ?', [$r['id_aduan']]);
    }
    unset($r);
    return $list;
}

/** Jenis berkas sebenarnya (bukan dari klien). */
function mimeOf(string $path): string
{
    return (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
}

/** Gabungkan semua berkas dari satu field form (baik tunggal maupun banyak). */
function filesOf(string $field): array
{
    if (!isset($_FILES[$field])) return [];
    $f = $_FILES[$field];
    $items = is_array($f['name'])
        ? array_map(null, $f['name'], $f['tmp_name'], $f['error'], $f['size'])
        : [[$f['name'], $f['tmp_name'], $f['error'], $f['size']]];
    $out = [];
    foreach ($items as [$nama, $tmp, $err, $size]) {
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => (string)$nama, 'tmp' => (string)$tmp, 'error' => (int)$err, 'size' => (int)$size];
    }
    return $out;
}

function cekUkuranPost(): void
{
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > 0 && empty($_POST) && empty($_FILES)) {
        jsonOut(['error' => 'Ukuran unggahan melebihi batas server. Naikkan upload_max_filesize dan post_max_size di php.ini.'], 413);
    }
}

function namaFileAman(string $nama): string
{
    return nowMs() . '-' . preg_replace('/[^\w.\-]/', '_', $nama);
}

function hapusFileLama(?string $url): void
{
    if ($url) @unlink(UPLOAD_DIR . basename($url));
}

function kodeUploadTerlalu(int $err): bool
{
    return $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE;
}

/* ───────────── TOKEN WARGA ───────────── */
function b64uEnc(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64uDec(string $s): string
{
    return (string)base64_decode(strtr($s, '-_', '+/'));
}

function tokenSign(array $payload): string
{
    $b = b64uEnc((string)json_encode($payload));
    return $b . '.' . b64uEnc(hash_hmac('sha256', $b, apiSecret(), true));
}

function tokenVerify(?string $token): ?array
{
    if (!$token || !str_contains($token, '.')) return null;
    [$b, $sig] = explode('.', $token, 2);
    $harap = b64uEnc(hash_hmac('sha256', $b, apiSecret(), true));
    if (!hash_equals($harap, $sig)) return null;
    $p = json_decode(b64uDec($b), true);
    if (!is_array($p) || !isset($p['exp']) || (int)$p['exp'] <= nowMs()) return null;
    return $p;
}

function bearer(): ?string
{
    $h = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/^Bearer (.+)$/', $h, $m) ? $m[1] : null;
}

function requireWarga(): int
{
    $p = tokenVerify(bearer());
    if (!$p) jsonOut(['error' => 'Belum login atau sesi habis'], 401);
    return (int)$p['id'];
}

/* ───────────── PEMBATAS PERCOBAAN (disimpan di database) ───────────── */
function pesanKunci(int $detik): string
{
    return 'Terlalu banyak percobaan. Coba lagi dalam ' . max(1, (int)ceil($detik / 60)) . ' menit.';
}

function sisaKunci(string $kunci): int
{
    $r = row('SELECT until_ts FROM percobaan_login WHERE kunci = ?', [$kunci]);
    $sisa = $r ? (int)$r['until_ts'] - nowMs() : 0;
    return $sisa > 0 ? (int)ceil($sisa / 1000) : 0;
}

function catatGagal(string $kunci): int
{
    $now = nowMs();
    $a = row('SELECT n, until_ts, last_ts FROM percobaan_login WHERE kunci = ?', [$kunci]);
    $n = (int)($a['n'] ?? 0);
    $until = (int)($a['until_ts'] ?? 0);
    $last = (int)($a['last_ts'] ?? 0);
    if (!$a || ($until && $until <= $now) || $now - $last > KUNCI_MS) {
        $n = 0;
        $until = 0;
    }
    $n++;
    if ($n >= MAX_GAGAL) $until = $now + KUNCI_MS;
    db()->prepare('INSERT INTO percobaan_login (kunci, n, until_ts, last_ts) VALUES (?, ?, ?, ?)
                   ON CONFLICT(kunci) DO UPDATE SET n = excluded.n, until_ts = excluded.until_ts, last_ts = excluded.last_ts')
        ->execute([$kunci, $n, $until, $now]);
    return sisaKunci($kunci);
}

function bersihkanGagal(string $kunci): void
{
    db()->prepare('DELETE FROM percobaan_login WHERE kunci = ?')->execute([$kunci]);
}

/* ───────────── REGISTER & LOGIN ───────────── */
function registerWarga(): void
{
    $b = jsonBody();
    $nik = teks($b['nik'] ?? '');
    $nama = teks($b['nama'] ?? '');
    $username = teks($b['username'] ?? '');
    $password = (string)($b['password'] ?? '');
    if ($nik === '' || $nama === '' || $username === '' || $password === '' || strlen($password) < 6) {
        jsonOut(['error' => 'nik, nama, username, password (min 6 karakter) wajib diisi'], 400);
    }
    if (!preg_match('/^\d{16}$/', $nik)) jsonOut(['error' => 'NIK harus 16 digit angka'], 400);
    try {
        db()->prepare('INSERT INTO pelapor (nik, nama, username, password_hash) VALUES (?, ?, ?, ?)')
            ->execute([$nik, $nama, mb_strtolower($username), password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int)db()->lastInsertId();
    } catch (PDOException $ex) {
        jsonOut(['error' => 'NIK atau username sudah terdaftar'], 400);
    }
    jsonOut(['id_pelapor' => $id], 201);
}

function loginWarga(): void
{
    $b = jsonBody();
    $uname = mb_strtolower(teks($b['username'] ?? ''));
    $pw = (string)($b['password'] ?? '');
    $kunci = 'login:' . clientIp() . ':' . $uname;

    $tunggu = sisaKunci($kunci);
    if ($tunggu) jsonOut(['error' => pesanKunci($tunggu)], 429);

    $p = row('SELECT * FROM pelapor WHERE username = ?', [$uname]);
    if (!$p || !password_verify($pw, (string)($p['password_hash'] ?? ''))) {
        $kini = catatGagal($kunci);
        if ($kini) jsonOut(['error' => pesanKunci($kini)], 429);
        jsonOut(['error' => 'Username atau kata sandi salah.'], 401);
    }
    if (!(int)$p['status_aktif']) jsonOut(['error' => 'Akun tidak aktif.'], 403);

    bersihkanGagal($kunci);
    unset($p['password_hash']);
    $token = tokenSign(['id' => (int)$p['id_pelapor'], 'exp' => nowMs() + TOKEN_DAYS * 86400000]);
    jsonOut(['token' => $token, 'pelapor' => $p]);
}

/* ───────────── PROFIL WARGA ───────────── */
function meGet(int $id): void
{
    $r = row('SELECT ' . PELAPOR_AMAN . ' FROM pelapor WHERE id_pelapor = ?', [$id]);
    $r ? jsonOut($r) : jsonOut(['error' => 'Pelapor tidak ditemukan'], 404);
}

function mePatch(int $id): void
{
    $b = jsonBody();
    $nama = teks($b['nama'] ?? '');
    $alamat = teks($b['alamat'] ?? '');
    if ($nama === '') jsonOut(['error' => 'Nama wajib diisi'], 400);
    db()->prepare('UPDATE pelapor SET nama = ?, alamat = ? WHERE id_pelapor = ?')
        ->execute([$nama, $alamat !== '' ? $alamat : null, $id]);
    jsonOut(['ok' => true]);
}

function fotoUpload(int $id): void
{
    cekUkuranPost();
    $f = filesOf('file')[0] ?? null;
    if (!$f) jsonOut(['error' => 'file wajib'], 400);
    if ($f['error'] !== UPLOAD_ERR_OK) {
        jsonOut(['error' => kodeUploadTerlalu($f['error']) ? 'Foto profil maksimal 4 MB.' : 'Unggahan gagal.'], 400);
    }
    if ($f['size'] > MAKS_AVATAR_BYTES) jsonOut(['error' => 'Foto profil maksimal 4 MB.'], 400);
    if (!str_starts_with(mimeOf($f['tmp']), 'image/')) jsonOut(['error' => 'File harus berupa gambar.'], 400);

    $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)));
    $nama = 'avatar-' . nowMs() . ($ext !== '' ? '.' . $ext : '');
    if (!move_uploaded_file($f['tmp'], UPLOAD_DIR . $nama)) jsonOut(['error' => 'Gagal menyimpan foto.'], 500);

    $lama = row('SELECT foto_url FROM pelapor WHERE id_pelapor = ?', [$id]);
    $url = '/uploads/' . $nama;
    db()->prepare('UPDATE pelapor SET foto_url = ? WHERE id_pelapor = ?')->execute([$url, $id]);
    hapusFileLama($lama['foto_url'] ?? null);
    jsonOut(['foto_url' => $url]);
}

function fotoHapus(int $id): void
{
    $lama = row('SELECT foto_url FROM pelapor WHERE id_pelapor = ?', [$id]);
    db()->prepare('UPDATE pelapor SET foto_url = NULL WHERE id_pelapor = ?')->execute([$id]);
    hapusFileLama($lama['foto_url'] ?? null);
    jsonOut(['ok' => true]);
}

function gantiPassword(int $id): void
{
    $b = jsonBody();
    $lama = (string)($b['password_lama'] ?? '');
    $baru = (string)($b['password_baru'] ?? '');
    if ($lama === '' || $baru === '') jsonOut(['error' => 'Kata sandi lama dan baru wajib diisi.'], 400);
    if (strlen($baru) < 6) jsonOut(['error' => 'Kata sandi baru minimal 6 karakter.'], 400);
    $p = row('SELECT password_hash FROM pelapor WHERE id_pelapor = ?', [$id]);
    if (!$p || !password_verify($lama, (string)($p['password_hash'] ?? ''))) {
        jsonOut(['error' => 'Kata sandi lama salah.'], 400);
    }
    if ($lama === $baru) jsonOut(['error' => 'Kata sandi baru harus berbeda dari yang lama.'], 400);
    db()->prepare('UPDATE pelapor SET password_hash = ? WHERE id_pelapor = ?')
        ->execute([password_hash($baru, PASSWORD_DEFAULT), $id]);
    jsonOut(['ok' => true]);
}

/* ───────────── ADUAN WARGA ───────────── */
function aduanSaya(int $id): void
{
    $sql = aduanSql() . ' WHERE a.is_arsip = 0 AND a.id_pelapor = ?';
    $args = [$id];
    $status = teks($_GET['status'] ?? '');
    if ($status !== '') {
        $sql .= ' AND a.status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY a.tanggal_aduan DESC';
    jsonOut(attachMedia(rows($sql, $args)));
}

function progresAduan(int $id, string $aduan): void
{
    $milik = row('SELECT 1 AS ok FROM aduan WHERE id_aduan = ? AND id_pelapor = ?', [$aduan, $id]);
    if (!$milik) jsonOut(['error' => 'Aduan tidak ditemukan'], 404);
    jsonOut(rows('SELECT * FROM progres_aduan WHERE id_aduan = ? ORDER BY updated_at DESC, id_progres DESC', [$aduan]));
}

function notifikasi(int $id): void
{
    jsonOut(rows('SELECT p.*, a.topik FROM progres_aduan p JOIN aduan a ON a.id_aduan = p.id_aduan
                  WHERE a.id_pelapor = ? ORDER BY p.updated_at DESC, p.id_progres DESC', [$id]));
}

function aduanBuat(int $id): void
{
    cekUkuranPost();
    $b = $_POST;
    $idKategori = teks($b['id_kategori'] ?? '');
    $topik = teks($b['topik'] ?? '');
    $deskripsi = teks($b['deskripsi'] ?? '');
    $lokasi = teks($b['lokasi_kejadian'] ?? '');

    $semua = array_merge(filesOf('foto'), filesOf('video'), filesOf('file'));
    foreach ($semua as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            jsonOut(['error' => kodeUploadTerlalu($f['error']) ? 'File terlalu besar (foto maks 2 MB, video maks 30 MB).' : 'Unggahan gagal.'], 400);
        }
    }
    if ($idKategori === '' || $topik === '') jsonOut(['error' => 'id_kategori dan topik wajib diisi'], 400);

    $foto = [];
    $video = [];
    foreach ($semua as $f) {
        $mime = mimeOf($f['tmp']);
        if (str_starts_with($mime, 'image/')) $foto[] = $f;
        elseif (str_starts_with($mime, 'video/')) $video[] = $f;
        else jsonOut(['error' => 'Lampiran harus berupa foto atau video.'], 400);
    }
    if (count($foto) > MAKS_FOTO) jsonOut(['error' => 'Maksimal ' . MAKS_FOTO . ' foto.'], 400);
    if (count($video) > 1) jsonOut(['error' => 'Maksimal 1 video.'], 400);
    foreach ($foto as $f) if ($f['size'] > MAKS_FOTO_BYTES) jsonOut(['error' => 'Setiap foto maksimal 2 MB.'], 400);
    foreach ($video as $f) if ($f['size'] > MAKS_VIDEO_BYTES) jsonOut(['error' => 'Video maksimal 30 MB.'], 400);
    if (!kategoriAda($idKategori)) jsonOut(['error' => 'Kategori tidak valid'], 400);

    $idAduan = 'ADR-' . strtoupper(base_convert((string)nowMs(), 10, 36));
    $berkas = array_merge(
        array_map(fn($f) => [$f, 'foto_sebelum'], $foto),
        array_map(fn($f) => [$f, 'video'], $video)
    );
    $tersimpan = [];
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO aduan (id_aduan, id_pelapor, id_kategori, topik, deskripsi, lokasi_kejadian)
                       VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$idAduan, $id, $idKategori, $topik, $deskripsi !== '' ? $deskripsi : null, $lokasi !== '' ? $lokasi : null]);
        $pdo->prepare("INSERT INTO progres_aduan (id_aduan, status_tahapan, persentase_progres, catatan_petugas)
                       VALUES (?, 'Verifikasi', 0, 'Aduan berhasil dibuat dan menunggu verifikasi.')")
            ->execute([$idAduan]);
        foreach ($berkas as [$f, $tipe]) {
            $nama = namaFileAman($f['name']);
            if (!move_uploaded_file($f['tmp'], UPLOAD_DIR . $nama)) throw new RuntimeException('Gagal menyimpan lampiran.');
            $tersimpan[] = UPLOAD_DIR . $nama;
            $pdo->prepare('INSERT INTO media_aduan (id_aduan, tipe_media, url_file, keterangan_media) VALUES (?, ?, ?, ?)')
                ->execute([$idAduan, $tipe, '/uploads/' . $nama, $f['name']]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        foreach ($tersimpan as $p) @unlink($p);
        error_log('[API aduanBuat] ' . $ex->getMessage());
        jsonOut(['error' => $ex instanceof RuntimeException ? $ex->getMessage() : 'Gagal menyimpan aduan.'], 400);
    }
    jsonOut(['id_aduan' => $idAduan, 'foto' => count($foto), 'video' => count($video)], 201);
}

function aduanEdit(int $id, string $aduan): void
{
    $ada = row('SELECT status, is_arsip FROM aduan WHERE id_aduan = ? AND id_pelapor = ?', [$aduan, $id]);
    if (!$ada || (int)$ada['is_arsip']) jsonOut(['error' => 'Aduan tidak ditemukan'], 404);

    $b = jsonBody();
    $topik = teks($b['topik'] ?? '', 120);
    $deskripsi = teks($b['deskripsi'] ?? '', 2000);
    $lokasi = teks($b['lokasi_kejadian'] ?? '', 200);
    if ($topik === '' || $deskripsi === '' || $lokasi === '') {
        jsonOut(['error' => 'Judul, uraian, dan lokasi wajib diisi'], 400);
    }
    $idKategori = teks($b['id_kategori'] ?? '');
    if (!kategoriAda($idKategori)) jsonOut(['error' => 'Kategori tidak valid'], 400);

    $st = db()->prepare("UPDATE aduan SET id_kategori = ?, topik = ?, deskripsi = ?, lokasi_kejadian = ?
                         WHERE id_aduan = ? AND id_pelapor = ? AND status = 'Verifikasi' AND is_arsip = 0");
    $st->execute([$idKategori, $topik, $deskripsi, $lokasi, $aduan, $id]);
    if (!$st->rowCount()) {
        jsonOut(['error' => 'Aduan sudah diproses petugas dan tidak bisa diubah lagi.'], 409);
    }
    jsonOut(['ok' => true]);
}

/** Pembatalan = aduan diarsipkan (bukan dihapus) dan dicatat sebagai progres "Dibatalkan". */
function aduanBatal(int $id, string $aduan): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare("UPDATE aduan SET is_arsip = 1
                         WHERE id_aduan = ? AND id_pelapor = ? AND status = 'Verifikasi' AND is_arsip = 0");
    $st->execute([$aduan, $id]);
    if ($st->rowCount()) {
        $pdo->prepare("INSERT INTO progres_aduan (id_aduan, status_tahapan, persentase_progres, catatan_petugas)
                       VALUES (?, 'Dibatalkan', 0, 'Aduan dibatalkan oleh pelapor.')")
            ->execute([$aduan]);
        $pdo->commit();
        jsonOut(['ok' => true]);
    }
    $pdo->rollBack();
    $ada = row('SELECT is_arsip FROM aduan WHERE id_aduan = ? AND id_pelapor = ?', [$aduan, $id]);
    if (!$ada || (int)$ada['is_arsip']) jsonOut(['error' => 'Aduan tidak ditemukan'], 404);
    jsonOut(['error' => 'Aduan sudah diproses petugas dan tidak bisa dibatalkan lagi.'], 409);
}

function survei(int $id): void
{
    $b = jsonBody();
    try {
        db()->prepare('INSERT INTO survei_kepuasan (id_pelapor, skor, label_kepuasan, emoji) VALUES (?, ?, ?, ?)')
            ->execute([$id, $b['skor'] ?? null, $b['label_kepuasan'] ?? null, $b['emoji'] ?? null]);
    } catch (PDOException $ex) {
        jsonOut(['error' => 'Skor harus angka 1 sampai 5.'], 400);
    }
    jsonOut(['ok' => true], 201);
}

/* ───────────── ADMIN (x-admin-key) ───────────── */
function adminRoute(string $m, array $s): void
{
    $kunci = 'admin:' . clientIp();
    $tunggu = sisaKunci($kunci);
    if ($tunggu) jsonOut(['error' => pesanKunci($tunggu)], 429);
    if (!hash_equals(adminKunci(), (string)($_SERVER['HTTP_X_ADMIN_KEY'] ?? ''))) {
        $kini = catatGagal($kunci);
        if ($kini) jsonOut(['error' => pesanKunci($kini)], 429);
        jsonOut(['error' => 'Kunci admin salah'], 401);
    }
    bersihkanGagal($kunci);

    if ($s === ['aduan'] && $m === 'GET')                                  adminAduanList();
    if (count($s) === 2 && $s[0] === 'aduan' && $m === 'GET')             adminAduanDetail($s[1]);
    if ($s === ['statistik'] && $m === 'GET')                              adminStatistik();
    if (count($s) === 3 && $s[0] === 'aduan' && $s[2] === 'status' && $m === 'PATCH') adminStatus($s[1]);
    if (count($s) === 3 && $s[0] === 'aduan' && $s[2] === 'arsip' && $m === 'PATCH')  adminArsip($s[1]);
    if (count($s) === 2 && $s[0] === 'aduan' && $m === 'DELETE')           adminHapus($s[1]);
    if (count($s) === 3 && $s[0] === 'aduan' && $s[2] === 'media' && $m === 'POST') adminMedia($s[1]);
    if (count($s) === 2 && $s[0] === 'cctv' && $m === 'PUT')               adminCctv($s[1]);
    if ($s === ['pelapor'] && $m === 'GET')                                adminPelapor();
    jsonOut(['error' => 'Rute tidak ditemukan'], 404);
}

function adminAduanList(): void
{
    $arsip = teks($_GET['arsip'] ?? '') === '1' ? 1 : 0;
    $sql = 'SELECT x.*, p.nama AS nama_pelapor, p.nik FROM (' . aduanSql() . ') x
            JOIN pelapor p ON p.id_pelapor = x.id_pelapor WHERE x.is_arsip = ?';
    $args = [$arsip];
    if (teks($_GET['status'] ?? '') !== '') { $sql .= ' AND x.status = ?'; $args[] = teks($_GET['status']); }
    if (teks($_GET['id_kategori'] ?? '') !== '') { $sql .= ' AND x.id_kategori = ?'; $args[] = teks($_GET['id_kategori']); }
    if (teks($_GET['q'] ?? '') !== '') {
        $q = '%' . teks($_GET['q']) . '%';
        $sql .= ' AND (x.topik LIKE ? OR x.deskripsi LIKE ? OR p.nama LIKE ?)';
        array_push($args, $q, $q, $q);
    }
    $sql .= ' ORDER BY x.tanggal_aduan DESC';
    jsonOut(rows($sql, $args));
}

function adminAduanDetail(string $aduan): void
{
    $a = row('SELECT x.*, p.nama AS nama_pelapor, p.nik FROM (' . aduanSql() . ') x
              JOIN pelapor p ON p.id_pelapor = x.id_pelapor WHERE x.id_aduan = ?', [$aduan]);
    if (!$a) jsonOut(['error' => 'Aduan tidak ditemukan'], 404);
    $a['media'] = rows('SELECT * FROM media_aduan WHERE id_aduan = ?', [$aduan]);
    $a['progres_list'] = rows('SELECT * FROM progres_aduan WHERE id_aduan = ?
                               ORDER BY updated_at DESC, id_progres DESC', [$aduan]);
    jsonOut($a);
}

function adminStatistik(): void
{
    jsonOut([
        'per_status' => rows('SELECT status, COUNT(*) AS jumlah FROM aduan WHERE is_arsip = 0 GROUP BY status'),
        'per_kategori' => rows('SELECT ' . labelKategoriSql() . ' AS label_kategori, COUNT(a.id_aduan) AS jumlah
                                FROM kategori k LEFT JOIN aduan a ON a.id_kategori = k.id_kategori AND a.is_arsip = 0
                                GROUP BY k.id_kategori'),
        'survei' => row('SELECT ROUND(AVG(skor), 2) AS rata_rata, COUNT(*) AS jumlah FROM survei_kepuasan'),
        'total_pelapor' => (int)scalar('SELECT COUNT(*) FROM pelapor'),
    ]);
}

/** Selesai otomatis 100%; catatan boleh kosong (sama seperti server API). */
function adminStatus(string $aduan): void
{
    $b = jsonBody();
    $status = teks($b['status'] ?? '');
    if (!in_array($status, STATUSES, true)) {
        jsonOut(['error' => 'status harus salah satu: ' . implode(', ', STATUSES)], 400);
    }
    $persen = $status === 'Selesai' ? 100 : ($b['persentase_progres'] ?? 0);
    $persenInt = filter_var($persen, FILTER_VALIDATE_INT);
    if ($persenInt === false || $persenInt < 0 || $persenInt > 100) {
        jsonOut(['error' => 'persentase_progres harus 0-100'], 400);
    }
    $catatan = teks($b['catatan_petugas'] ?? '');

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare('UPDATE aduan SET status = ? WHERE id_aduan = ?');
    $st->execute([$status, $aduan]);
    if (!$st->rowCount()) {
        $pdo->rollBack();
        jsonOut(['error' => 'Aduan tidak ditemukan'], 404);
    }
    $pdo->prepare('INSERT INTO progres_aduan (id_aduan, status_tahapan, persentase_progres, catatan_petugas) VALUES (?, ?, ?, ?)')
        ->execute([$aduan, $status, $persenInt, $catatan !== '' ? $catatan : null]);
    $pdo->commit();
    jsonOut(['ok' => true]);
}

function adminArsip(string $aduan): void
{
    $b = jsonBody();
    $st = db()->prepare('UPDATE aduan SET is_arsip = ? WHERE id_aduan = ?');
    $st->execute([!empty($b['is_arsip']) ? 1 : 0, $aduan]);
    $st->rowCount() ? jsonOut(['ok' => true]) : jsonOut(['error' => 'Aduan tidak ditemukan'], 404);
}

function adminHapus(string $aduan): void
{
    $st = db()->prepare('DELETE FROM aduan WHERE id_aduan = ?');
    $st->execute([$aduan]);
    $st->rowCount() ? jsonOut(['ok' => true]) : jsonOut(['error' => 'Aduan tidak ditemukan'], 404);
}

function adminMedia(string $aduan): void
{
    cekUkuranPost();
    $f = filesOf('file')[0] ?? null;
    if (!$f) jsonOut(['error' => 'file wajib'], 400);
    if ($f['error'] !== UPLOAD_ERR_OK) jsonOut(['error' => 'Unggahan gagal.'], 400);

    $nama = namaFileAman($f['name']);
    if (!move_uploaded_file($f['tmp'], UPLOAD_DIR . $nama)) jsonOut(['error' => 'Gagal menyimpan file.'], 500);
    $b = $_POST;
    try {
        db()->prepare('INSERT INTO media_aduan (id_aduan, tipe_media, url_file, keterangan_media) VALUES (?, ?, ?, ?)')
            ->execute([$aduan, teks($b['tipe_media'] ?? '') ?: 'foto_sesudah', '/uploads/' . $nama, teks($b['keterangan_media'] ?? '') ?: null]);
    } catch (PDOException $ex) {
        @unlink(UPLOAD_DIR . $nama);
        jsonOut(['error' => 'Data media tidak valid (cek id aduan dan tipe media).'], 400);
    }
    jsonOut(['ok' => true], 201);
}

function adminCctv(string $id): void
{
    $b = jsonBody();
    $st = db()->prepare('UPDATE cctv_publik SET status_online = ?, kondisi_arus = ? WHERE id_cctv = ?');
    $st->execute([!empty($b['status_online']) ? 1 : 0, $b['kondisi_arus'] ?? null, $id]);
    $st->rowCount() ? jsonOut(['ok' => true]) : jsonOut(['error' => 'CCTV tidak ditemukan'], 404);
}

function adminPelapor(): void
{
    jsonOut(rows('SELECT ' . PELAPOR_AMAN . ' FROM pelapor ORDER BY nama'));
}
