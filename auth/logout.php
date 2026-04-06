<?php
/**
 * Logout Logic
 * Updated: Support SweetAlert Notification & Fix Path
 */

// 1. Load Dependency (Naik 1 folder ke root)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/functions.php'; 

$auth = new Auth();

// 2. Proses Logout (Hapus Session Lama)
// Ini akan melakukan session_destroy()
$auth->logout();

// 3. Set Pesan Notifikasi (Akan membuat session baru)
// PENTING: Gunakan key 'message' sebagai parameter pertama agar terbaca di footer/login
setFlash('message', 'Anda telah berhasil logout.', 'success');

// 4. Redirect ke Halaman Login
// Fungsi redirect() di functions.php sudah ada session_write_close(), jadi aman.
redirect(APP_URL . '/auth/login.php');