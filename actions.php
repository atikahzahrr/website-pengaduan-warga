<?php
declare(strict_types=1);
/** Handler POST: simpan/arsip aduan, update progress, simpan pengadu. */

if (!hash_equals(csrfToken(), $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('Token keamanan tidak valid.');
}

$pdo    = db();
$action = $_POST['action'] ?? '';
$back   = $_POST['back'] ?? 'kelola-aduan';

function field(string $key, int $max = 255): string
{
    return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max);
}

try {
    switch ($action) {
        case 'save_aduan':
            $id = field('id_aduan');
            $data = [field('id_pelapor'), field('id_kategori'), field('topik', 180), field('deskripsi', 1000), field('lokasi_kejadian', 180)];
            if (in_array('', [$data[0], $data[1], $data[2], $data[4]], true)) throw new RuntimeException('Lengkapi pengadu, kategori, topik, dan lokasi.');

            if ($id !== '') {
                $pdo->prepare('UPDATE aduan SET id_pelapor=?, id_kategori=?, topik=?, deskripsi=?, lokasi_kejadian=? WHERE id_aduan=?')
                    ->execute([...$data, $id]);
            } else {
                $next = (int)scalar("SELECT COALESCE(MAX(CAST(SUBSTR(id_aduan,5) AS INTEGER)),9000)+1 FROM aduan");
                $id = 'ADR-' . $next;
                $pdo->prepare('INSERT INTO aduan (id_aduan,id_pelapor,id_kategori,topik,deskripsi,lokasi_kejadian) VALUES (?,?,?,?,?,?)')
                    ->execute([$id, ...$data]);
                $pdo->prepare("INSERT INTO progres_aduan (id_aduan,status_tahapan,persentase_progres,catatan_petugas) VALUES (?,'Verifikasi',0,'Aduan dibuat.')")
                    ->execute([$id]);
            }
            saveUpload('foto', $id, 'foto_sebelum');
            flash("Aduan {$id} berhasil disimpan.");
            break;

        case 'toggle_arsip':
            $pdo->prepare('UPDATE aduan SET is_arsip = 1 - is_arsip WHERE id_aduan=?')->execute([field('id_aduan')]);
            flash('Status arsip aduan diperbarui.');
            break;

        case 'save_progress':
            $id = field('id_aduan');
            $status = field('status');
            $persen = (int)($_POST['persen'] ?? -1);
            $catatan = field('catatan', 1000);
            if (!in_array($status, STATUSES, true) || $persen < 0 || $persen > 100 || $catatan === '') throw new RuntimeException('Status, progress (0–100), dan catatan wajib diisi.');
            if ($status === 'Selesai' && $persen !== 100) throw new RuntimeException('Status Selesai harus berprogress 100%.');
            $pdo->prepare('INSERT INTO progres_aduan (id_aduan,status_tahapan,persentase_progres,catatan_petugas) VALUES (?,?,?,?)')
                ->execute([$id, $status, $persen, $catatan]);
            $pdo->prepare('UPDATE aduan SET status=? WHERE id_aduan=?')->execute([$status, $id]);
            saveUpload('foto', $id, 'foto_sesudah');
            flash("Progress {$id} diperbarui.");
            $back = 'progress';
            break;

        case 'save_pelapor':
            $id = (int)($_POST['id_pelapor'] ?? 0);
            $nik = field('nik', 16);
            $lat = (float)($_POST['latitude'] ?? 0);
            $lng = (float)($_POST['longitude'] ?? 0);
            if (!preg_match('/^\d{16}$/', $nik)) throw new RuntimeException('NIK harus terdiri dari 16 digit.');
            if (field('nama', 100) === '' || abs($lat) > 90 || abs($lng) > 180) throw new RuntimeException('Nama, latitude (-90..90), dan longitude (-180..180) harus valid.');
            $values = [$nik, field('nama', 100), field('avatar_tone', 7) ?: '#2563eb', field('alamat', 180), $lat, $lng, isset($_POST['status_aktif']) ? 1 : 0];
            if ($id) {
                $pdo->prepare('UPDATE pelapor SET nik=?, nama=?, avatar_tone=?, alamat=?, latitude=?, longitude=?, status_aktif=? WHERE id_pelapor=?')->execute([...$values, $id]);
            } else {
                $pdo->prepare('INSERT INTO pelapor (nik,nama,avatar_tone,alamat,latitude,longitude,status_aktif) VALUES (?,?,?,?,?,?,?)')->execute($values);
            }
            flash('Data pengadu berhasil disimpan.');
            $back = 'kelola-pengadu';
            break;
    }
} catch (PDOException $ex) {
    flash(str_contains($ex->getMessage(), 'UNIQUE') ? 'NIK sudah digunakan pengadu lain.' : 'Kesalahan database: ' . $ex->getMessage());
    $back = $_POST['error_page'] ?? $back;
} catch (RuntimeException $ex) {
    flash($ex->getMessage());
    $back = $_POST['error_page'] ?? $back;
}

header('Location: ?page=' . urlencode($back));
exit;
