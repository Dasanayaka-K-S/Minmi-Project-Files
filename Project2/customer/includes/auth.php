<?php
// ============================================================
//  CUSTOMER AUTH GUARD
//  Place in: customer/includes/auth.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('minmi_customer');
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header('Location: ' . (strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '../login.php' : 'login.php'));
    exit;
}
