<?php
// ============================================================
//  HEADER — Minmi Restaurent Customer Website
//  Place in: customer/includes/header.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

// Cart count from session
$cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) $cart_count += $item['qty'];
}

$is_logged_in   = isset($_SESSION['customer_id']);
$customer_name  = $_SESSION['customer_name'] ?? '';

// Detect base path (are we in a subfolder?)
$base = (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../' : '';
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — Minmi Restaurent' : 'Minmi Restaurent' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:    #e8622a;
            --accent-d:  #c94f1e;
            --accent-l:  #ff8555;
            --bg:        #faf8f5;
            --bg-2:      #ffffff;
            --bg-3:      #f4f0ea;
            --text:      #1a1512;
            --text-2:    #4a3f35;
            --text-3:    #8a7a6e;
            --border:    #e8e0d5;
            --radius:    10px;
            --radius-lg: 16px;
            --shadow:    0 2px 16px rgba(0,0,0,.08);
            --shadow-lg: 0 8px 40px rgba(0,0,0,.12);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── NAV ── */
        .nav {
            background: #fff;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }
        .nav-logo {
            font-family: 'DM Serif Display', serif;
            font-size: 1.4rem;
            color: var(--accent);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--text-2);
            font-size: .88rem;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: var(--radius);
            transition: all .2s;
        }
        .nav-links a:hover,
        .nav-links a.active {
            color: var(--accent);
            background: rgba(232,98,42,.07);
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-btn {
            position: relative;
            background: var(--bg-3);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 8px 14px;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 600;
            color: var(--text-2);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
        }
        .cart-btn:hover { border-color: var(--accent); color: var(--accent); }
        .cart-badge {
            background: var(--accent);
            color: #fff;
            border-radius: 20px;
            font-size: .68rem;
            font-weight: 700;
            padding: 1px 7px;
            min-width: 20px;
            text-align: center;
        }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; border-radius: var(--radius); font-family: 'DM Sans', sans-serif; font-size: .88rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .2s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-d); }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-2); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn-sm { padding: 6px 14px; font-size: .82rem; }
        .btn-danger { background: #e84242; color: #fff; }
        .btn-danger:hover { background: #c73333; }

        /* ── MAIN CONTENT ── */
        main { flex: 1; max-width: 1200px; width: 100%; margin: 0 auto; padding: 32px 24px; }

        /* ── CARDS ── */
        .card { background: var(--bg-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow); }
        .card-title { font-family: 'DM Serif Display', serif; font-size: 1.15rem; font-weight: 400; margin-bottom: 16px; }

        /* ── BADGES ── */
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; letter-spacing: .03em; }
        .badge-green  { background: rgba(62,207,142,.12);  color: #1a7a4a; }
        .badge-blue   { background: rgba(78,156,247,.12);  color: #1a5ca8; }
        .badge-yellow { background: rgba(245,200,66,.15);  color: #8a6e00; }
        .badge-orange { background: rgba(232,98,42,.12);   color: #b84e1a; }
        .badge-red    { background: rgba(232,66,66,.12);   color: #a82020; }
        .badge-gray   { background: rgba(0,0,0,.07);       color: #555; }

        /* ── FLASH ── */
        .flash { display: flex; align-items: center; justify-content: space-between; padding: 13px 18px; border-radius: var(--radius); margin-bottom: 20px; font-size: .88rem; font-weight: 500; }
        .flash-success { background: rgba(62,207,142,.1); color: #1a7a4a; border: 1px solid rgba(62,207,142,.3); }
        .flash-danger  { background: rgba(232,66,66,.1);  color: #a82020; border: 1px solid rgba(232,66,66,.3); }
        .flash-warning { background: rgba(245,200,66,.12);color: #8a6e00; border: 1px solid rgba(245,200,66,.3); }
        .flash-info    { background: rgba(78,156,247,.1); color: #1a5ca8; border: 1px solid rgba(78,156,247,.3); }

        /* ── FORMS ── */
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .form-label { font-size: .78rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-3); }
        .form-input { background: var(--bg-3); border: 1.5px solid var(--border); color: var(--text); border-radius: var(--radius); padding: 10px 14px; font-family: 'DM Sans', sans-serif; font-size: .9rem; outline: none; transition: border-color .2s; width: 100%; }
        .form-input:focus { border-color: var(--accent); background: #fff; }

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-family: 'DM Serif Display', serif; font-size: 2rem; font-weight: 400; letter-spacing: -.02em; }
        .page-header p { color: var(--text-3); font-size: .9rem; margin-top: 6px; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        thead th { background: var(--bg-3); color: var(--text-3); font-size: .72rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--border); }
        tbody td { padding: 13px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: var(--bg-3); }

        /* ── MODAL ── */
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .modal-backdrop.open { display: flex; }
        .modal { background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; animation: modalIn .25s ease; box-shadow: var(--shadow-lg); }
        @keyframes modalIn { from { opacity:0; transform:scale(.96) translateY(12px) } to { opacity:1; transform:none } }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
        .modal-title { font-family: 'DM Serif Display', serif; font-size: 1.1rem; }
        .modal-close { background: none; border: none; color: var(--text-3); cursor: pointer; font-size: 1.2rem; padding: 4px; border-radius: 6px; }
        .modal-body { padding: 16px 24px 24px; }

        /* ── FOOTER ── */
        footer { background: #1a1512; color: #a8998a; text-align: center; padding: 28px 24px; font-size: .82rem; margin-top: auto; }
        footer strong { color: #e8622a; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            main { padding: 20px 16px; }
        }
    </style>
</head>
<body>

<nav class="nav">
    <div class="nav-inner">
        <a href="<?= $base ?>index.php" class="nav-logo">🔥 Minmi</a>

        <ul class="nav-links">
            <li><a href="<?= $base ?>index.php" class="<?= $current_page === 'index' ? 'active' : '' ?>">Home</a></li>
            <li><a href="<?= $base ?>menu.php" class="<?= $current_page === 'menu' ? 'active' : '' ?>">Menu</a></li>
            <?php if ($is_logged_in): ?>
            <li><a href="<?= $base ?>orders.php" class="<?= $current_page === 'orders' ? 'active' : '' ?>">My Orders</a></li>
            <li><a href="<?= $base ?>reservations.php" class="<?= $current_page === 'reservations' ? 'active' : '' ?>">Reservations</a></li>
            <li><a href="<?= $base ?>feedback.php" class="<?= $current_page === 'feedback' ? 'active' : '' ?>">Feedback</a></li>
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
            <a href="<?= $base ?>register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main>
