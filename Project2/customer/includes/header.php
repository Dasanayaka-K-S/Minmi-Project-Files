<?php
// ============================================================
//  HEADER — Minmi Restaurent Customer Website
//  Place in: customer/includes/header.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('minmi_customer');
    session_start();
}

$cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) $cart_count += $item['qty'];
}

$is_logged_in  = isset($_SESSION['customer_id']);
$customer_name = $_SESSION['customer_name'] ?? '';
$base          = (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../' : '';
$current_page  = basename($_SERVER['PHP_SELF'], '.php');

// Asset base path
$_doc_root    = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']), '/');
$_script_file = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$_web_path    = str_replace($_doc_root, '', $_script_file);
$_project_dir = substr($_web_path, 0, strpos($_web_path, '/customer/'));
$asset_base   = $_project_dir . '/customer/assets/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — Minmi Restaurent' : 'Minmi Restaurent' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $asset_base ?>style.css">
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="<?= $base ?>index.php" class="nav-logo">
            <img src="<?= $asset_base ?>logo.png" alt="Minmi Restaurant" style="height:48px;width:auto;object-fit:contain">
            <span style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;color:var(--fire);letter-spacing:-.02em">Minmi</span>
        </a>
        <ul class="nav-links">
            <li><a href="<?= $base ?>index.php" class="<?= $current_page==='index'?'active':'' ?>">Home</a></li>
            <li><a href="<?= $base ?>menu.php" class="<?= $current_page==='menu'?'active':'' ?>">Menu</a></li>
            <?php if ($is_logged_in): ?>
            <li><a href="<?= $base ?>orders.php" class="<?= $current_page==='orders'?'active':'' ?>">My Orders</a></li>
            <li><a href="<?= $base ?>reservations.php" class="<?= $current_page==='reservations'?'active':'' ?>">Reservations</a></li>
            <li><a href="<?= $base ?>feedback.php" class="<?= $current_page==='feedback'?'active':'' ?>">Feedback</a></li>
            <?php endif; ?>
        </ul>
        <div class="nav-right">
            <?php if ($is_logged_in): ?>
            <a href="<?= $base ?>cart.php" class="cart-btn">
                🛒 Cart
                <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $base ?>profile.php" class="btn btn-ghost btn-sm">👤 <?= htmlspecialchars(explode(' ', $customer_name)[0]) ?></a>
            <a href="<?= $base ?>logout.php" class="btn btn-ghost btn-sm">Logout</a>
            <?php else: ?>
            <a href="<?= $base ?>login.php" class="btn btn-ghost btn-sm">Login</a>
            <a href="<?= $base ?>register.php" class="btn btn-primary btn-sm">Get Started →</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main>