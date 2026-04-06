<?php
/**
 * Database Configuration
 * Sistem Rekomendasi Jurusan & PTN
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'rekomendasi_ptn');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// PDO Options
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);
