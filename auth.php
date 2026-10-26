<?php
declare(strict_types=1);


const LOGIN_MAX_ATTEMPT = 5;   
const LOGIN_LOCK_TIME   = 60;  

/** Akun yang sedang login (null bila belum masuk). */
function currentUser(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function isSuperadmin(): bool
{
    return (currentUser()['role'] ?? '') === 'superadmin';
}

/** Nama peran untuk ditampilkan. */
function roleLabel(?string $role): string
{
    if ($role === 'superadmin') return 'Superadmin';
    if ($role === 'admin') return 'Admin';
    return $role ? ucfirst((string)$role) : 'Pengguna';
}

/** Validasi format username untuk registrasi/login. */
function validUsername(string $username): bool
{
    return (bool)preg_match('/^[a-z0-9._-]{3,60}$/', $username);
}

/** Validasi kekuatan kata sandi. */
function validatePassword(string $password): string
{
    if (strlen($password) < 8) return 'Kata sandi minimal 8 karakter.';
    return '';
}

/**
 * Verifikasi kredensial lalu isi sesi. Mengembalikan false bila nama pengguna
 * tidak dikenal, kata sandi salah, atau akun sedang dinonaktifkan.
 */
function loginUser(string $username, string $password): bool
{
    $user = row('SELECT * FROM users WHERE username = ?', [$username]);
    if (!$user || !(int)$user['status_aktif']) return false;
    if (!password_verify($password, $user['password_hash'])) return false;

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id_user']]);
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id_user'  => (int)$user['id_user'],
        'username' => $user['username'],
        'nama'     => $user['nama_lengkap'],
        'role'     => $user['role'],
        'dinas'    => $user['dinas'] ?? '',
    ];

    db()->prepare('UPDATE users SET terakhir_masuk = ? WHERE id_user = ?')
        ->execute([date('Y-m-d H:i:s'), $user['id_user']]);

    return true;
}

/** Akhiri sesi login secara penuh. */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}

/* ---------- Pembatasan percobaan login sederhana ---------- */

function loginLockedFor(): int
{
    $lock = (int)($_SESSION['login_lock'] ?? 0);
    return $lock > time() ? $lock - time() : 0;
}

function registerLoginFail(): void
{
    $fail = (int)($_SESSION['login_fail'] ?? 0) + 1;
    $_SESSION['login_fail'] = $fail;
    if ($fail >= LOGIN_MAX_ATTEMPT) {
        $_SESSION['login_fail'] = 0;
        $_SESSION['login_lock'] = time() + LOGIN_LOCK_TIME;
    }
}

function clearLoginFails(): void
{
    unset($_SESSION['login_fail'], $_SESSION['login_lock']);
}

/* ---------- Gerbang akses ---------- */

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ?page=login');
        exit;
    }
}

function requireSuperadmin(): void
{
    requireLogin();
    if (!isSuperadmin()) {
        flash('Halaman tersebut hanya dapat diakses oleh superadmin.');
        header('Location: ?page=ringkasan');
        exit;
    }
}
