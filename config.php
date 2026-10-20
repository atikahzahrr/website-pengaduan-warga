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

function progressBar(int $value, string $status): string
{
    $color = $status === 'Selesai' ? '#10b981' : '#2563eb';
    return '<div class="progress-mini"><div class="small muted">Progress ' . $value . '%</div>'
         . '<div class="track"><div class="fill" style="width:' . $value . '%;background:' . $color . '"></div></div></div>';
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

function avatar(array $p): string
{
    $initial = mb_strtoupper(mb_substr($p['nama'], 0, 1));
    return '<span class="person-avatar" style="--avatar-tone:' . e($p['avatar_tone'] ?: '#2563eb') . '">' . e($initial) . '</span>';
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
