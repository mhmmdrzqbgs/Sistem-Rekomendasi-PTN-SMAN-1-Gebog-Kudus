<?php
/**
 * Authentication Class
 * Updated: User Data Safety, Database Integration, Session Timeout & Graduation Check
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check for session timeout due to inactivity
     * @param int $duration Timeout duration in seconds (default 1800s = 30m)
     */
    public function checkSessionTimeout($duration = 1800)
    {
        // Only check if user is logged in
        if ($this->isLoggedIn()) {
            if (isset($_SESSION['LAST_ACTIVITY'])) {
                // Calculate time since last activity
                if ((time() - $_SESSION['LAST_ACTIVITY']) > $duration) {
                    // Session expired
                    $this->logout();
                    
                    // Restart session briefly to set flash message
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    
                    if (function_exists('setFlash')) {
                        setFlash('message', 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.', 'warning');
                    } else {
                         $_SESSION['flash'] = [
                            'message' => [
                                'type' => 'warning',
                                'message' => 'Sesi Anda telah berakhir karena tidak ada aktivitas. Silakan login kembali.'
                            ]
                        ];
                    }

                    header("Location: " . APP_URL . "/auth/login.php");
                    exit();
                }
            }
            
            // Update last activity time stamp
            $_SESSION['LAST_ACTIVITY'] = time();
        }
    }

    /**
     * Register new user (Internal/Import use)
     */
    public function register($nama, $username, $password, $role = 'siswa', $data = [])
    {
        // Check if username exists
        $existing = $this->db->queryOne("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            return ['success' => false, 'message' => 'Username/NISN sudah terdaftar'];
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $this->db->beginTransaction();

            // Insert user
            $this->db->execute(
                "INSERT INTO users (username, password, role, nama, is_active) VALUES (?, ?, ?, ?, 1)",
                [$username, $hashedPassword, $role, $nama]
            );
            $userId = $this->db->lastInsertId();

            // Insert siswa profile if role is siswa
            if ($role === 'siswa') {
                $this->db->execute(
                    "INSERT INTO siswa_profile (user_id, nisn, kelas, jurusan_sma, asal_sekolah, tahun_lulus, minat, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Aktif')", // Default status Aktif
                    [
                        $userId,
                        $username, // NISN sama dengan Username
                        $data['kelas'] ?? null,
                        $data['jurusan_sma'] ?? 'IPA',
                        $data['asal_sekolah'] ?? null,
                        $data['tahun_lulus'] ?? null,
                        $data['minat'] ?? null
                    ]
                );
            }

            $this->db->commit();
            return ['success' => true, 'user_id' => $userId];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Registrasi gagal: ' . $e->getMessage()];
        }
    }

    /**
     * Login user
     * Updated: Cek Status Kelulusan Siswa
     */
    public function login($identifier, $password)
    {
        // Query ke username
        $user = $this->db->queryOne(
            "SELECT * FROM users WHERE username = ? AND is_active = 1",
            [$identifier]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'NISN/Username tidak ditemukan'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Password salah'];
        }

        // --- UPDATE PENTING: CEK STATUS KELULUSAN ---
        if ($user['role'] === 'siswa') {
            $profile = $this->db->queryOne("SELECT id, status FROM siswa_profile WHERE user_id = ?", [$user['id']]);
            
            // Jika siswa ditemukan dan statusnya 'Lulus' (atau bukan 'Aktif')
            if ($profile && $profile['status'] === 'Lulus') {
                return ['success' => false, 'message' => 'Akun Alumni (Lulus) tidak dapat mengakses sistem.'];
            }
            
            // Simpan ID profil juga untuk kemudahan
            $_SESSION['siswa_id'] = $profile['id'] ?? null;
        }

        // Set session minimal (User ID & Role)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        
        // Initialize LAST_ACTIVITY for timeout feature
        $_SESSION['LAST_ACTIVITY'] = time();

        return ['success' => true, 'role' => $user['role']];
    }

    /**
     * Logout user
     */
    public function logout()
    {
        session_unset(); // Hapus semua variabel session
        session_destroy(); // Hancurkan session
        return true;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    /**
     * Check if user is siswa
     */
    public function isSiswa()
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'siswa';
    }

    /**
     * Get current user
     */
    public function getUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $userId = $_SESSION['user_id'];

        // Ambil data terbaru dari database
        $user = $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$userId]);

        // Jika user dihapus dari DB tapi session masih ada
        if (!$user) {
            $this->logout();
            return null;
        }

        return $user; 
    }

    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin()
    {
        $this->checkSessionTimeout(); 

        if (!$this->isLoggedIn()) {
            setFlash('Silakan login terlebih dahulu.', 'warning');
            redirect(APP_URL . '/auth/login.php'); 
        }
    }

    /**
     * Require admin role
     */
    public function requireAdmin()
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            setFlash('Akses ditolak. Halaman ini hanya untuk Admin.', 'error');
            redirect(APP_URL . '/siswa/dashboard.php');
        }
    }

    /**
     * Require siswa role
     */
    public function requireSiswa()
    {
        $this->requireLogin();
        if (!$this->isSiswa()) {
            setFlash('Akses ditolak. Halaman ini hanya untuk Siswa.', 'error');
            redirect(APP_URL . '/admin/dashboard.php');
        }
    }

    /**
     * Get siswa profile
     */
    public function getSiswaProfile()
    {
        if (!$this->isSiswa()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'];

        return $this->db->queryOne(
            "SELECT sp.*, u.username, u.nama 
             FROM siswa_profile sp 
             JOIN users u ON sp.user_id = u.id 
             WHERE sp.user_id = ?",
            [$userId]
        );
    }

    /**
     * Update password
     */
    public function updatePassword($userId, $oldPassword, $newPassword)
    {
        $user = $this->db->queryOne("SELECT password FROM users WHERE id = ?", [$userId]);

        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Password lama salah'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->execute(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hashedPassword, $userId]
        );

        return ['success' => true];
    }
}