<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Real low stock count from DB
$low_stock_count = 0;
if (isset($pdo)) {
    try {
        $low_stock_count = $pdo->query("SELECT COUNT(*) FROM inventory WHERE stock <= min_stock")->fetchColumn();
    } catch (Exception $e) { $low_stock_count = 0; }
}

$user_name  = $_SESSION['user_name']  ?? 'User';
$user_role  = $_SESSION['user_role']  ?? 'cashier';
$is_subpage = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
$root       = $is_subpage ? '../' : '';
$initials   = strtoupper(substr($user_name, 0, 1) . (strpos($user_name,' ')!==false ? substr($user_name, strpos($user_name,' ')+1, 1) : ''));
?>
<header class="topbar">
    <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
        <span></span><span></span><span></span>
    </button>

    <div class="topbar-title"><?= $page_title ?? 'Dashboard' ?></div>

    <div class="topbar-right">
        <!-- Low stock alert — admin only -->
        <?php if ($user_role === 'admin' && $low_stock_count > 0): ?>
        <a href="<?= $root ?>pages/inventory.php"
           class="topbar-badge" title="<?= $low_stock_count ?> low stock items">
            ⚠️ <span><?= $low_stock_count ?></span>
        </a>
        <?php endif; ?>

        <!-- Date -->
        <div class="topbar-date"><?= date('D, d M Y') ?></div>

        <!-- User info + logout -->
        <div class="topbar-user">
            <div class="topbar-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="topbar-user-info">
                <span class="topbar-user-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="topbar-user-role"><?= $user_role === 'admin' ? '👑 Admin' : '🧾 Cashier' ?></span>
            </div>
            <a href="<?= $root ?>logout.php" class="topbar-logout" title="Logout">🚪</a>
        </div>
    </div>
</header>

<style>
.topbar-badge{display:flex;align-items:center;gap:4px;background:rgba(245,200,66,.12);
    color:#f5c842;border:1px solid rgba(245,200,66,.25);border-radius:20px;
    padding:4px 10px;font-size:.75rem;font-weight:600;cursor:pointer;text-decoration:none;
    transition:background .2s}
.topbar-badge:hover{background:rgba(245,200,66,.2)}
.topbar-user{display:flex;align-items:center;gap:8px;padding:4px 8px;
    border-radius:var(--radius);border:1px solid var(--border);background:var(--bg-2);
    cursor:default}
.topbar-avatar{width:30px;height:30px;border-radius:50%;background:var(--accent);
    color:#fff;font-size:.72rem;font-weight:700;display:flex;align-items:center;
    justify-content:center;flex-shrink:0}
.topbar-user-info{display:flex;flex-direction:column;line-height:1.2}
.topbar-user-name{font-size:.78rem;font-weight:600}
.topbar-user-role{font-size:.65rem;color:var(--text-3)}
.topbar-logout{background:none;border:none;cursor:pointer;font-size:1rem;
    color:var(--text-3);padding:2px 4px;border-radius:4px;transition:color .2s;
    text-decoration:none;margin-left:2px}
.topbar-logout:hover{color:var(--red)}
@media(max-width:600px){.topbar-user-info{display:none}.topbar-date{display:none}}
</style>
