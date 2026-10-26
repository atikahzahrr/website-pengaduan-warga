<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$views = require __DIR__ . '/views.php';
$page  = $_GET['page'] ?? 'ringkasan';

/* Autentikasi (login/logout/registrasi) diproses lebih dulu, sebelum gerbang akses. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['login', 'logout', 'register'], true)) {
    require __DIR__ . '/actions.php';   // selalu berakhir dengan redirect
    exit;
}

/* Halaman login & registrasi berdiri sendiri (tanpa sidebar dashboard). */
if ($page === 'login') {
    if (isLoggedIn()) {
        header('Location: ?page=ringkasan');
        exit;
    }
    require __DIR__ . '/pages/login.php';
    exit;
}

if ($page === 'register') {
    if (isLoggedIn()) {
        header('Location: ?page=ringkasan');
        exit;
    }
    require __DIR__ . '/pages/register.php';
    exit;
}

/* Semua halaman lain butuh sesi login yang valid. */
requireLogin();

if (!isset($views[$page])) $page = 'ringkasan';
[$title, $description, $file] = $views[$page];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/actions.php';   // selalu berakhir dengan redirect
}

if ($page === 'admin') requireSuperadmin();

require __DIR__ . '/partials/header.php';
require __DIR__ . "/pages/{$file}.php";
require __DIR__ . '/partials/footer.php';
