<?php
/**
 * Login Page - Modern Minimalist Design
 * Features: Dark Mode, Glassmorphism, Responsive
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php'; 

$auth = new Auth();

if ($auth->isLoggedIn()) {
    if ($auth->isAdmin()) {
        redirect(APP_URL . '/admin/dashboard.php');
    } else {
        redirect(APP_URL . '/siswa/dashboard.php');
    }
}

if (isPost()) {
    $nisn = post('nisn');
    $password = $_POST['password'] ?? '';

    if (empty($nisn) || empty($password)) {
        setFlash('message', 'NISN dan password harus diisi!', 'warning');
    } else {
        $result = $auth->login($nisn, $password);

        if ($result['success']) {
            setFlash('message', 'Login berhasil! Selamat datang.', 'success');
            
            if ($result['role'] === 'admin') {
                redirect(APP_URL . '/admin/dashboard.php');
            } else {
                redirect(APP_URL . '/siswa/dashboard.php');
            }
        } else {
            setFlash('message', $result['message'], 'error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Sistem Rekomendasi PTN</title>

    <link rel="icon" href="<?= APP_URL ?>/assets/img/logo.jpeg" type="image/jpeg">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/img/logo.png" type="png">
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: { 
                        primary: '#3b82f6', 
                        dark: '#0f172a',
                        darkcard: '#1e293b'
                    }
                }
            }
        }
        
        // Check theme preference
        if (localStorage.getItem('spk-theme') === 'dark' || (!('spk-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Autofill Style Fix */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: #1e293b !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        .dark input:-webkit-autofill,
        .dark input:-webkit-autofill:hover, 
        .dark input:-webkit-autofill:focus, 
        .dark input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #1e293b inset !important;
            -webkit-text-fill-color: #e2e8f0 !important;
        }
    </style>
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 transition-colors duration-300">

    <button id="theme-toggle" class="absolute top-6 right-6 p-2.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all focus:outline-none z-50">
        <i id="theme-toggle-dark-icon" class="fas fa-moon hidden text-lg"></i>
        <i id="theme-toggle-light-icon" class="fas fa-sun hidden text-lg text-yellow-500"></i>
    </button>

    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-[20%] -left-[10%] w-[50%] h-[50%] rounded-full bg-blue-400/20 blur-3xl dark:bg-blue-600/10"></div>
        <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] rounded-full bg-indigo-400/20 blur-3xl dark:bg-indigo-600/10"></div>
    </div>

    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative z-10">
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center mb-6">
                <div class="relative w-20 h-20 bg-white dark:bg-slate-800 rounded-2xl shadow-xl flex items-center justify-center transform hover:scale-105 transition-transform duration-300 border border-slate-100 dark:border-slate-700">
                    <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo" class="h-12 w-auto object-contain">
                </div>
            </div>
            
            <h2 class="text-center text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                Selamat Datang
            </h2>
            <p class="mt-2 text-center text-sm text-slate-600 dark:text-slate-400">
                Sistem Rekomendasi PTN SMA N 1 Gebog
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-[420px]">
            <div class="bg-white dark:bg-slate-800 py-8 px-4 shadow-2xl shadow-slate-200/50 dark:shadow-none sm:rounded-2xl sm:px-10 border border-slate-100 dark:border-slate-700 backdrop-blur-xl">
                
                <form id="loginForm" class="space-y-6" action="" method="POST">
                    
                    <div>
                        <label for="nisn" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            NISN / Username
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <i class="fas fa-user text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                            </div>
                            <input id="nisn" name="nisn" type="text" required autofocus 
                                class="appearance-none block w-full pl-10 pr-3 py-3 border border-slate-300 dark:border-slate-600 rounded-xl leading-5 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-200 sm:text-sm"
                                placeholder="Masukkan nomor induk siswa"
                                value="<?= post('nisn') ?>">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                            Password
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                            </div>
                            <input id="password" name="password" type="password" required
                                class="appearance-none block w-full pl-10 pr-3 py-3 border border-slate-300 dark:border-slate-600 rounded-xl leading-5 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-200 sm:text-sm"
                                placeholder="Masukkan kata sandi">
                        </div>
                    </div>

                    <div>
                        <button type="submit" id="btnSubmit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg shadow-blue-500/30 text-sm font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all active:scale-[0.98]">
                            Masuk Sekarang
                        </button>
                    </div>
                </form>

            </div>
            
            <p class="mt-8 text-center text-xs text-slate-400 dark:text-slate-500">
                &copy; <?= date('Y') ?> SMA Negeri 1 Gebog. All rights reserved.
            </p>
        </div>
    </div>

    <div id="global-loader" class="fixed inset-0 z-[9999] hidden bg-slate-900/60 backdrop-blur-[4px] flex items-center justify-center transition-all duration-300 opacity-0">
        <div class="bg-white/90 dark:bg-slate-800/90 rounded-2xl shadow-2xl p-8 flex flex-col items-center justify-center transform scale-100 border border-white/20 dark:border-slate-700">
            <svg class="animate-spin h-10 w-10 text-blue-600 dark:text-blue-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-slate-600 dark:text-slate-300 font-medium text-sm tracking-wide animate-pulse">Memverifikasi...</p>
        </div>
    </div>

    <script>
        // --- 1. DARK MODE TOGGLE ---
        const toggleBtn = document.getElementById('theme-toggle');
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');

        function updateIcon() {
            if (document.documentElement.classList.contains('dark')) {
                lightIcon.classList.remove('hidden');
                darkIcon.classList.add('hidden');
            } else {
                darkIcon.classList.remove('hidden');
                lightIcon.classList.add('hidden');
            }
        }
        updateIcon();

        toggleBtn.addEventListener('click', function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('spk-theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('spk-theme', 'dark');
            }
            updateIcon();
        });

        // --- 2. LOADER LOGIC ---
        const loader = document.getElementById('global-loader');
        const btnSubmit = document.getElementById('btnSubmit');

        function hideLoader() {
            if(loader) {
                loader.classList.add('opacity-0');
                setTimeout(() => loader.classList.add('hidden'), 300);
            }
            if(btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = 'Masuk Sekarang';
                btnSubmit.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        }

        // Handle Form Submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Terputus!',
                    text: 'Periksa internet Anda.',
                    confirmButtonColor: '#ef4444',
                    background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#fff',
                    color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                    customClass: { popup: 'rounded-xl shadow-xl' }
                });
                return;
            }

            if (this.checkValidity()) {
                // Show Overlay Loader
                if(loader) {
                    loader.classList.remove('hidden');
                    void loader.offsetWidth; 
                    loader.classList.remove('opacity-0');
                }

                // Change Button State
                const originalText = btnSubmit.innerHTML;
                btnSubmit.innerHTML = '<svg class="animate-spin h-5 w-5 text-white inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...';
                
                // Disable with slight delay to ensure form submit triggers
                setTimeout(() => {
                    btnSubmit.disabled = true;
                    btnSubmit.classList.add('opacity-75', 'cursor-not-allowed');
                }, 100);
            }
        });

        window.addEventListener('pageshow', function(event) {
            hideLoader(); 
        });

        // --- 3. ALERT MESSAGE ---
        <?php 
        $msgType = ''; $msgText = '';
        if (isset($_SESSION['flash']['message'])) {
            $data = $_SESSION['flash']['message'];
            $msgType = $data['type'];
            $msgText = $data['message'];
            unset($_SESSION['flash']['message']);
        }
        ?>

        <?php if ($msgText): ?>
            Swal.fire({
                icon: '<?= $msgType ?>',
                title: '<?= $msgType == "success" ? "Berhasil!" : "Perhatian" ?>',
                text: '<?= $msgText ?>',
                confirmButtonColor: '#3b82f6',
                timer: 2500, 
                timerProgressBar: true,
                background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fff' : '#000',
                customClass: { popup: 'rounded-xl shadow-xl' }
            });
        <?php endif; ?>
    </script>
</body>
</html>