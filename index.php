<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$views = require __DIR__ . '/views.php';
$page  = $_GET['page'] ?? 'ringkasan';
if (!isset($views[$page])) $page = 'ringkasan';
[$title, $description, $file] = $views[$page];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/actions.php';   // selalu berakhir dengan redirect
}

require __DIR__ . '/partials/header.php';
require __DIR__ . "/pages/{$file}.php";
require __DIR__ . '/partials/footer.php';
