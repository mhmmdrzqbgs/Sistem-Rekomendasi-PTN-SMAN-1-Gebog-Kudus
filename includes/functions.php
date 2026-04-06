<?php
/**
 * Helper Functions
 * Updated: Fix Deprecated trim(null) issue on PHP 8.1+
 */

require_once __DIR__ . '/../config/app.php';

/**
 * Redirect to URL
 * PERBAIKAN UTAMA: Menambahkan session_write_close()
 */
function redirect($url)
{
    // FIX: Paksa simpan session sebelum pindah halaman agar notifikasi tidak hilang
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        // Fallback jika header sudah terkirim
        echo '<script>window.location.href="' . $url . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
        exit;
    }
}

/**
 * Sanitize input
 * FIX: Menambahkan (string) casting sebelum trim() untuk mencegah error di PHP 8.1+
 */
function sanitize($input)
{
    // Pastikan input adalah string, jika null ubah jadi string kosong
    $str = $input ?? '';
    return htmlspecialchars(strip_tags(trim((string)$str)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format number to Indonesian format
 */
function formatNumber($number, $decimals = 2)
{
    return number_format((float)$number, $decimals, ',', '.');
}

/**
 * Get flash message
 */
function getFlash($key)
{
    // Pastikan session aktif
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

/**
 * Set flash message
 */
function setFlash($key, $message, $type = 'success')
{
    // Pastikan session aktif
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Jika pesan string biasa, ubah jadi array agar konsisten dengan header-admin
    if (!is_array($message)) {
        $_SESSION['flash'][$key] = [
            'message' => $message,
            'type' => $type
        ];
    } else {
        $_SESSION['flash'][$key] = $message;
    }
}

/**
 * Check if request is POST
 */
function isPost()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get POST data
 */
function post($key, $default = '')
{
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}

/**
 * Get GET data
 */
function get($key, $default = '')
{
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

/**
 * Upload file
 */
function uploadFile($file, $allowedTypes = [], $maxSize = null)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error'];
    }

    $maxSize = $maxSize ?? (defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 2097152);
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowedTypes) && !in_array($ext, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    $filename = uniqid() . '_' . time() . '.' . $ext;
    
    // Gunakan path dinamis atau default
    $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads/';
    $destination = $uploadPath . $filename;

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename, 'path' => $destination];
    }

    return ['success' => false, 'message' => 'Failed to move file'];
}

/**
 * Get rumpun label dari jurusan SMA
 */
function getRumpunFromJurusan($jurusan)
{
    // Fix Deprecated trim
    $jurusan = trim((string)($jurusan ?? ''));
    
    switch (strtoupper($jurusan)) {
        case 'IPA':
        case 'TEKNIK':
        case 'SAINTEK':
            return 'Saintek';
        case 'IPS':
        case 'BAHASA':
        case 'SOSHUM':
            return 'Soshum';
        default:
            return 'Campuran';
    }
}

/**
 * Get badge class berdasarkan skor
 */
function getScoreBadgeClass($score)
{
    if ($score >= 80) return 'bg-success'; 
    if ($score >= 60) return 'bg-primary'; 
    if ($score >= 40) return 'bg-warning';
    return 'bg-danger';
}

/**
 * Get akreditasi badge class
 * FIX: Deprecated trim(null)
 */
function getAkreditasiBadge($akreditasi)
{
    // Fix: Pastikan string sebelum trim
    $akreditasi = strtoupper(trim((string)($akreditasi ?? '')));
    
    switch ($akreditasi) {
        case 'A':
        case 'UNGGUL':
            return 'bg-success';
        case 'B':
        case 'BAIK SEKALI':
            return 'bg-primary';
        case 'C':
        case 'BAIK':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...')
{
    $text = (string)($text ?? ''); // Fix Deprecated strlen(null)
    
    if (strlen($text) <= $length)
        return $text;
    return substr($text, 0, $length) . $suffix;
}

/**
 * Time ago format
 */
function timeAgo($datetime)
{
    if (!$datetime) return '-';
    
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 2592000) return floor($diff / 86400) . ' hari lalu';

    return date('d M Y', $time);
}