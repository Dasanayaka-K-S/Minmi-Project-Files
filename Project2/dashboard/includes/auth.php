<?php
// ============================================================
//  AUTH GUARD — Minmi Restaurent Admin
//  Place in: dashboard/includes/auth.php
//  Include at the TOP of every protected page
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Pages cashier is NOT allowed to access
$cashier_blocked = ['dashboard', 'menu', 'inventory', 'staff', 'reports', 'settings'];
$current = basename($_SERVER['PHP_SELF'], '.php');

if ($_SESSION['user_role'] === 'cashier' && in_array($current, $cashier_blocked)) {
    header('Location: orders.php');
    exit;
}
