<?php
/**
 * Application Configuration
 * Sistem Rekomendasi Jurusan & PTN
 */

define('APP_NAME', 'Sistem Rekomendasi Jurusan & PTN');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/webdinda');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Allowed file types for Excel import
define('ALLOWED_EXCEL_TYPES', ['xlsx', 'xls', 'csv']);

// Recommendation settings
define('TOP_RECOMMENDATIONS', 5); // Jumlah rekomendasi teratas yang ditampilkan
define('WEIGHT_AKADEMIK', 0.5);   // Bobot nilai akademik
define('WEIGHT_KESESUAIAN', 0.3); // Bobot kesesuaian jurusan
define('WEIGHT_MINAT', 0.2);      // Bobot minat
