<?php
/**
 * Header Template for Siswa
 * Updated: Add Menu "Nilai TKA"
 */
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireSiswa();

// --- TAMBAHAN: CEK SESSION TIMEOUT ---
$auth->checkSessionTimeout(1800); // 30 Menit
// -------------------------------------

$user = $auth->getUser();
$initial = strtoupper(substr($user['nama'] ?? 'S', 0, 1));
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

function navClass($isActive) {
    if ($isActive) {
        return 'flex items-center gap-3 px-4 py-3 bg-sky-50 text-sky-700 border-r-4 border-sky-500 font-medium transition-all duration-200 dark:bg-sky-900/20 dark:text-sky-300 dark:border-sky-400';
    } else {
        return 'flex items-center gap-3 px-4 py-3 text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition-all duration-200 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-sky-300';
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>Sistem Rekomendasi PTN</title>

    <link rel="icon" href="<?= APP_URL ?>/assets/img/logo.jpeg" type="image/jpeg">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/img/logo.png" type="png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', 
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#0ea5e9',
                        secondary: '#0284c7',
                        dark: '#0f172a',
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Cek LocalStorage atau Preferensi Sistem
        if (localStorage.getItem('spk-theme') === 'dark' || (!('spk-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 antialiased dark:bg-slate-900 dark:text-slate-100 transition-colors duration-300">

    <div class="flex h-screen overflow-hidden">

        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

        <aside id="sidebar" class="bg-white border-r border-slate-200 dark:bg-slate-800 dark:border-slate-700 fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full transition-transform duration-300 md:relative md:translate-x-0 flex flex-col shadow-sm">
            
            <div class="h-16 flex items-center px-6 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 transition-colors justify-between">
                <div class="flex items-center gap-3">
                    <img src="<?= APP_URL ?>/assets/img/logo.jpeg" alt="Logo" class="h-8 w-8 rounded bg-slate-50 p-0.5 object-contain border border-slate-200 dark:border-slate-600">
                    <div class="flex flex-col">
                        <span class="font-bold text-xs text-slate-500 dark:text-slate-400 uppercase tracking-widest">SISTEM</span>
                        <span class="font-bold text-sm tracking-wide text-slate-800 dark:text-white">REKOMENDASI PTN</span>
                    </div>
                </div>
                <button id="sidebarClose" class="md:hidden text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-4 space-y-1 bg-white dark:bg-slate-800 transition-colors">
                
                <a href="<?= APP_URL ?>/siswa/dashboard.php" class="<?= navClass($currentPage === 'dashboard') ?>">
                    <i class="fas fa-home w-5 text-center"></i> 
                    <span class="text-sm">Dashboard</span>
                </a>

                <a href="<?= APP_URL ?>/siswa/profil.php" class="<?= navClass($currentPage === 'profil') ?>">
                    <i class="fas fa-user w-5 text-center"></i> 
                    <span class="text-sm">Profil Saya</span>
                </a>

                <div class="px-6 pt-6 pb-2 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Akademik</div>

                <a href="<?= APP_URL ?>/siswa/nilai-rapor.php" class="<?= navClass($currentPage === 'nilai-rapor') ?>">
                    <i class="fas fa-book w-5 text-center"></i> 
                    <span class="text-sm">Nilai Rapor</span>
                </a>

                <a href="<?= APP_URL ?>/siswa/nilai-tryout.php" class="<?= navClass($currentPage === 'nilai-tryout') ?>">
                    <i class="fas fa-file-alt w-5 text-center"></i> 
                    <span class="text-sm">Nilai Try Out</span>
                </a>

                <a href="<?= APP_URL ?>/siswa/nilai-tka.php" class="<?= navClass($currentPage === 'nilai-tka') ?>">
                    <i class="fas fa-graduation-cap w-5 text-center"></i> 
                    <span class="text-sm">Nilai TKA</span>
                </a>

                <div class="px-6 pt-6 pb-2 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Analisis</div>

                <a href="<?= APP_URL ?>/siswa/rekomendasi.php" class="<?= navClass($currentPage === 'rekomendasi') ?>">
                    <i class="fas fa-star w-5 text-center"></i> 
                    <span class="text-sm">Rekomendasi</span>
                </a>

                <a href="<?= APP_URL ?>/siswa/analisis.php" class="<?= navClass($currentPage === 'analisis') ?>">
                    <i class="fas fa-chart-line w-5 text-center"></i> 
                    <span class="text-sm">Analisis Nilai</span>
                </a>

            </nav>

            <div class="p-4 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 transition-colors">
                <a href="<?= APP_URL ?>/auth/logout.php" class="flex items-center gap-3 px-4 py-2 text-red-500 hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300 rounded-lg transition-colors">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i> 
                    <span class="text-sm font-medium">Logout</span>
                </a>
            </div>
        </aside>
        
        <div class="flex-1 flex flex-col overflow-hidden relative">
            
            <header class="bg-white h-16 shadow-sm border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 z-40 dark:bg-slate-800 dark:border-slate-700 transition-colors duration-300">
                <div class="flex items-center gap-4">
                    <button id="sidebarToggle" class="p-2 text-slate-500 hover:text-sky-600 hover:bg-sky-50 rounded-lg focus:outline-none md:hidden dark:text-slate-400 dark:hover:bg-slate-700 transition-colors">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-slate-800 tracking-tight hidden sm:block dark:text-white">
                        <?= isset($pageTitle) ? $pageTitle : 'Dashboard' ?>
                    </h1>
                </div>

                <div class="flex items-center gap-4">
                    
                    <button id="theme-toggle" type="button" class="text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 focus:outline-none focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-700 rounded-lg text-sm p-2.5 transition-colors">
                        <i id="theme-toggle-dark-icon" class="fas fa-moon hidden"></i> <i id="theme-toggle-light-icon" class="fas fa-sun hidden"></i>  </button>

                    <div class="flex items-center gap-3 pl-4 border-l border-slate-200 dark:border-slate-600">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= sanitize($user['nama']) ?></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">Siswa</div>
                        </div>
                        <div class="h-10 w-10 rounded-full bg-sky-100 border border-sky-200 flex items-center justify-center overflow-hidden text-sky-600 font-bold text-lg shadow-sm dark:bg-sky-900 dark:text-sky-300 dark:border-sky-700">
                            <?= $initial ?>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8 dark:bg-slate-900 transition-colors duration-300">
                
                <?php if ($flash = getFlash('message')): ?>
                    <?php
                        $alertColor = match($flash['type']) {
                            'success' => 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800',
                            'error'   => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
                            'warning' => 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800',
                            default   => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800'
                        };
                    ?>
                    <div class="mb-6 px-4 py-3 rounded-lg border flex items-start gap-3 <?= $alertColor ?> shadow-sm relative animate-fade-in-down" role="alert">
                        <span class="block sm:inline font-medium text-sm"><?= $flash['message'] ?></span>
                        <button onclick="this.parentElement.remove()" class="absolute top-0 bottom-0 right-0 px-4 py-3 opacity-50 hover:opacity-100 text-current">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // === 1. SIDEBAR LOGIC (MOBILE FIX) ===
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');

        function toggleSidebar() {
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                // Buka
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
            } else {
                // Tutup
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        }

        if(sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
        if(sidebarClose) sidebarClose.addEventListener('click', toggleSidebar);
        if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

        // === 2. DARK MODE LOGIC ===
        var toggleBtn = document.getElementById('theme-toggle');
        var darkIcon = document.getElementById('theme-toggle-dark-icon');
        var lightIcon = document.getElementById('theme-toggle-light-icon');

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

        // === 3. AUTO LOGOUT (IDLE TIMER) ===
        const idleDuration = 1795000; // 29m 55s
        let idleTimer;

        function resetIdleTimer() {
            clearTimeout(idleTimer);
            idleTimer = setTimeout(function() {
                window.location.href = "<?= APP_URL ?>/auth/logout.php"; 
            }, idleDuration);
        }

        window.onload = resetIdleTimer;
        window.onmousemove = resetIdleTimer;
        window.onmousedown = resetIdleTimer; 
        window.ontouchstart = resetIdleTimer;
        window.onclick = resetIdleTimer;
        window.onkeypress = resetIdleTimer;
        window.addEventListener('scroll', resetIdleTimer, true);
    });
</script>