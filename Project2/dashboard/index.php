<?php
// ============================================================
//  INDEX — Minmi Restaurent Admin
//  Place in: dashboard/index.php
// ============================================================

session_start();

// If already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

// Not logged in → go to login
header('Location: login.php');
exit;
