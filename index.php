<?php
/**
 * Landing Page - Redirect to Login or Dashboard
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/config/app.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    if ($auth->isAdmin()) {
        redirect(APP_URL . '/admin/dashboard.php');
    } else {
        redirect(APP_URL . '/siswa/dashboard.php');
    }
} else {
    redirect(APP_URL . '/auth/login.php');
}