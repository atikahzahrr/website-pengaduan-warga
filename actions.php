<?php
declare(strict_types=1);
/** Handler POST: login/logout, simpan/arsip aduan, update progress, simpan pengadu, kelola admin. */

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

/** Validasi kata sandi baru (minimal 8 karakter). */
function passwordStrength(string $password): string
{
    if (strlen($password) < 8) return 'Kata sandi minimal 8 karakter.';
    return '';
}

try {
    switch ($action) {
        case 'register':
            $username = mb_strtolower(field('username', 60));
            $nama     = field('nama_lengkap', 100);
            $dinas    = field('dinas', 120);
            $password = (string)($_POST['password'] ?? '');
            $konfirm  = (string)($_POST['password2'] ?? '');
            if (!validUsername($username)) {
                throw new RuntimeException('Nama pengguna 3–60 karakter: huruf kecil, angka, titik, garis, atau garis bawah.');
            }
            if ($nama === '') throw new RuntimeException('Nama lengkap wajib diisi.');
            $err = validatePassword($password);
            if ($err !== '') throw new RuntimeException($err);
            if ($password !== $konfirm) throw new RuntimeException('Konfirmasi kata sandi tidak sama.');
            if (row('SELECT id_user FROM users WHERE username = ?', [$username])) {
                throw new RuntimeException('Nama pengguna sudah digunakan.');
            }
            $pdo->prepare('INSERT INTO users (username,nama_lengkap,password_hash,role,dinas,status_aktif) VALUES (?,?,?,?,?,1)')
                ->execute([$username, $nama, password_hash($password, PASSWORD_DEFAULT), 'admin', $dinas]);
            clearLoginFails();
            $_SESSION['old_username'] = $username;
            flash('Pendaftaran berhasil. Silakan masuk dengan akun yang baru dibuat.');
            $back = 'login';
            break;

        case 'login':
            $username = field('username', 60);
            $password = (string)($_POST['password'] ?? '');
            $kunci = loginLockedFor();
            if ($kunci > 0) {
                flash("Terlalu banyak percobaan login. Coba lagi dalam {$kunci} detik.");
                $_SESSION['old_username'] = $username;
                $back = 'login';
                break;
            }
            if ($username === '' || $password === '') {
                flash('Nama pengguna dan kata sandi wajib diisi.');
                $_SESSION['old_username'] = $username;
                $back = 'login';
                break;
            }
            if (loginUser($username, $password)) {
                clearLoginFails();
                unset($_SESSION['old_username']);
                flash('Selamat datang di Dashboard Aduan Masyarakat.');
                $back = 'ringkasan';
            } else {
                registerLoginFail();
                flash('Nama pengguna atau kata sandi salah.');
                $_SESSION['old_username'] = $username;
                $back = 'login';
            }
            break;

        case 'logout':
            logoutUser();
            header('Location: ?page=login');
            exit;

        case 'save_admin':
            requireSuperadmin();
            $username = mb_strtolower(field('username', 60));
            $nama     = field('nama_lengkap', 100);
            $dinas    = field('dinas', 120);
            $role     = ($_POST['role'] ?? 'admin') === 'superadmin' ? 'superadmin' : 'admin';
            $password = (string)($_POST['password'] ?? '');
            $konfirm  = (string)($_POST['password2'] ?? '');
            if (!validUsername($username)) {
                throw new RuntimeException('Nama pengguna 3–60 karakter: huruf kecil, angka, titik, garis, atau garis bawah.');
            }
            if ($nama === '') throw new RuntimeException('Nama lengkap wajib diisi.');
            $err = validatePassword($password);
            if ($err !== '') throw new RuntimeException($err);
            if ($password !== $konfirm) throw new RuntimeException('Konfirmasi kata sandi tidak sama.');
            if (row('SELECT id_user FROM users WHERE username = ?', [$username])) {
                throw new RuntimeException('Nama pengguna sudah digunakan akun lain.');
            }
            $pdo->prepare('INSERT INTO users (username,nama_lengkap,password_hash,role,dinas,status_aktif) VALUES (?,?,?,?,?,1)')
                ->execute([$username, $nama, password_hash($password, PASSWORD_DEFAULT), $role, $dinas]);
            flash("Akun admin {$username} berhasil dibuat.");
            $back = 'admin';
            break;

        case 'toggle_admin':
            requireSuperadmin();
            $id = (int)($_POST['id_user'] ?? 0);
            $u  = row('SELECT * FROM users WHERE id_user = ?', [$id]);
            if (!$u) throw new RuntimeException('Akun tidak ditemukan.');
            if ($u['role'] === 'superadmin' && (int)$u['status_aktif']) {
                $aktifSuper = (int)scalar("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND status_aktif = 1");
                if ($aktifSuper <= 1) throw new RuntimeException('Superadmin aktif terakhir tidak dapat dinonaktifkan.');
            }
            $pdo->prepare('UPDATE users SET status_aktif = 1 - status_aktif WHERE id_user = ?')->execute([$id]);
            flash('Status akun ' . $u['username'] . ' diperbarui.');
            $back = 'admin';
            break;

        case 'reset_admin_password':
            requireSuperadmin();
            $id       = (int)($_POST['id_user'] ?? 0);
            $password = (string)($_POST['password'] ?? '');
            $konfirm  = (string)($_POST['password2'] ?? '');
            $u        = row('SELECT username FROM users WHERE id_user = ?', [$id]);
            if (!$u) throw new RuntimeException('Akun tidak ditemukan.');
            $err = validatePassword($password);
            if ($err !== '') throw new RuntimeException($err);
            if ($password !== $konfirm) throw new RuntimeException('Konfirmasi kata sandi tidak sama.');
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('Kata sandi ' . $u['username'] . ' berhasil diperbarui.');
            $back = 'admin';
            break;

        case 'delete_admin':
            requireSuperadmin();
            $id = (int)($_POST['id_user'] ?? 0);
            $u  = row('SELECT * FROM users WHERE id_user = ?', [$id]);
            if (!$u) throw new RuntimeException('Akun tidak ditemukan.');
            if ($id === (int)(currentUser()['id_user'] ?? 0)) throw new RuntimeException('Akun yang sedang digunakan tidak dapat dihapus.');
            if ($u['role'] === 'superadmin') {
                $jumlahSuper = (int)scalar("SELECT COUNT(*) FROM users WHERE role = 'superadmin'");
                if ($jumlahSuper <= 1) throw new RuntimeException('Superadmin terakhir tidak dapat dihapus.');
            }
            $pdo->prepare('DELETE FROM users WHERE id_user = ?')->execute([$id]);
            flash('Akun ' . $u['username'] . ' dihapus.');
            $back = 'admin';
            break;

        case 'change_password':
            $u = currentUser();
            if (!$u) throw new RuntimeException('Silakan masuk kembali.');
            $lama  = (string)($_POST['password_lama'] ?? '');
            $baru  = (string)($_POST['password_baru'] ?? '');
            $kunci = (string)($_POST['password_konfirmasi'] ?? '');
            $rowUser = row('SELECT * FROM users WHERE id_user = ?', [$u['id_user']]);
            if (!$rowUser || !password_verify($lama, $rowUser['password_hash'])) throw new RuntimeException('Kata sandi lama tidak sesuai.');
            $err = validatePassword($baru);
            if ($err !== '') throw new RuntimeException($err);
            if ($baru !== $kunci) throw new RuntimeException('Konfirmasi kata sandi baru tidak sama.');
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?')
                ->execute([password_hash($baru, PASSWORD_DEFAULT), $u['id_user']]);
            flash('Kata sandi berhasil diperbarui.');
            $back = 'akun';
            break;

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
