<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$is_subpage   = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
$prefix       = $is_subpage ? '' : 'pages/';
$root         = $is_subpage ? '../' : '';
$role         = $_SESSION['user_role'] ?? 'cashier';
$assets_path  = $is_subpage ? '../assets' : 'assets';

$all_links = [
    ['page'=>'dashboard',    'href'=>'dashboard.php',             'icon'=>'🏠', 'label'=>'Dashboard',       'roles'=>['admin']],
    ['page'=>'orders',       'href'=>$prefix.'orders.php',        'icon'=>'🧾', 'label'=>'Orders',           'roles'=>['admin','cashier']],
    ['page'=>'reservations', 'href'=>$prefix.'reservations.php',  'icon'=>'📅', 'label'=>'Reservations',     'roles'=>['admin','cashier']],
    ['page'=>'customers',    'href'=>$prefix.'customers.php',     'icon'=>'👥', 'label'=>'Customers',        'roles'=>['admin','cashier']],
    ['page'=>'feedback',     'href'=>$prefix.'feedback.php',      'icon'=>'⭐', 'label'=>'Feedback',         'roles'=>['admin','cashier']],
    ['page'=>'menu',         'href'=>$prefix.'menu.php',          'icon'=>'🍽️', 'label'=>'Menu Management',  'roles'=>['admin']],
    ['page'=>'inventory',    'href'=>$prefix.'inventory.php',     'icon'=>'📦', 'label'=>'Inventory',        'roles'=>['admin']],
    ['page'=>'staff',        'href'=>$prefix.'staff.php',         'icon'=>'👨‍🍳','label'=>'Staff',             'roles'=>['admin']],
    ['page'=>'reports',      'href'=>$prefix.'reports.php',       'icon'=>'📊', 'label'=>'Reports',          'roles'=>['admin']],
    ['page'=>'settings',     'href'=>$prefix.'settings.php',      'icon'=>'⚙️', 'label'=>'Settings',         'roles'=>['admin']],
];

$nav_links = array_filter($all_links, fn($l) => in_array($role, $l['roles']));

$user_name     = $_SESSION['user_name']  ?? 'User';
$user_initials = strtoupper(substr($user_name,0,1).(strpos($user_name,' ')!==false?substr($user_name,strpos($user_name,' ')+1,1):''));
$role_label    = $role === 'admin' ? '👑 Admin' : '🧾 Cashier';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="<?= $assets_path ?>/img/logo.png"
             alt="Minmi Restaurant"
             style="height:38px;width:38px;object-fit:contain;border-radius:8px;flex-shrink:0"
             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
        <span style="display:none;font-size:1.4rem">🔥</span>
        <span class="brand-name">Minmi<em>Restaurent</em></span>
    </div>

    <?php if ($role === 'cashier'): ?>
    <div style="margin:0 12px 12px;background:rgba(232,98,42,.1);border:1px solid rgba(232,98,42,.25);border-radius:8px;padding:8px 12px;font-size:.72rem;color:#e8622a;text-align:center">
        🧾 Cashier Mode
    </div>
    <?php endif; ?>

    <nav class="sidebar-nav">
        <?php foreach ($nav_links as $link): ?>
        <a href="<?= $link['href'] ?>" class="nav-link <?= $current_page===$link['page']?'active':'' ?>">
            <span class="nav-icon"><?= $link['icon'] ?></span>
            <span class="nav-label"><?= $link['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-avatar"><?= htmlspecialchars($user_initials) ?></div>
        <div class="admin-info">
            <strong><?= htmlspecialchars($user_name) ?></strong>
            <span><?= $role_label ?></span>
        </div>
        <a href="<?= $root ?>logout.php" title="Logout"
           style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--text-3);font-size:1.1rem;text-decoration:none;padding:4px;border-radius:6px;transition:color .2s"
           onmouseover="this.style.color='var(--red)'"
           onmouseout="this.style.color='var(--text-3)'">🚪</a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>