<?php
/**
 * Penjalan server PHP bawaan untuk API aplikasi mobile sekaligus dashboard.
 *
 *   php -S 0.0.0.0:3000 router.php
 *
 * Permintaan /api/... diteruskan ke api/index.php. Permintaan lain dilayani seperti biasa
 * (dashboard di /, file di uploads/ dan assets/).
 */
$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if ($path === '/api' || str_starts_with($path, '/api/')) {
    require __DIR__ . '/api/index.php';
    return true;
}
return false;
