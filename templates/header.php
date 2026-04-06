<?php
/**
 * Header Template
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= APP_NAME ?></title>

    <link rel="icon" href="<?= APP_URL ?>/assets/img/logo.jpeg" type="image/jpeg">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/img/logo.jpeg" type="image/jpeg">
    
    <meta name="description" content="Sistem Rekomendasi Jurusan dan Perguruan Tinggi Negeri untuk siswa SMA/SMK">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>RekomendasiPTN</span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= APP_URL ?>/index.php" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= APP_URL ?>/siswa.php" class="nav-item <?= $currentPage === 'siswa' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Data Siswa</span>
            </a>
            <a href="<?= APP_URL ?>/import.php" class="nav-item <?= $currentPage === 'import' ? 'active' : '' ?>">
                <i class="fas fa-file-import"></i>
                <span>Import Excel</span>
            </a>
            <a href="<?= APP_URL ?>/ptn.php" class="nav-item <?= $currentPage === 'ptn' ? 'active' : '' ?>">
                <i class="fas fa-university"></i>
                <span>Data PTN</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <p>v<?= APP_VERSION ?></p>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?= isset($pageTitle) ? $pageTitle : 'Dashboard' ?></h1>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Cari siswa..." id="globalSearch">
                </div>
                <div class="topbar-actions">
                    <button class="btn-icon" id="themeToggle" title="Toggle Theme">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <?php
            $flash = getFlash('message');
            if ($flash):
                ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <i
                        class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                    <span><?= $flash['message'] ?></span>
                    <button class="alert-close">&times;</button>
                </div>
            <?php endif; ?>