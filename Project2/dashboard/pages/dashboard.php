<?php
// ============================================================
//  DASHBOARD CONTENT — Minmi Restaurent Admin
//  Place in: dashboard/pages/dashboard.php
// ============================================================

require_once __DIR__ . '/../includes/db.php';

// ════════════════════════════════════════
//  FETCH ALL DATA FROM DB
// ════════════════════════════════════════

$orders         = $pdo->query("SELECT * FROM orders ORDER BY date DESC, id DESC")->fetchAll();
$total_orders   = count($orders);
$pending_orders = count(array_filter($orders, fn($o) => $o['status'] === 'Pending'));
$total_revenue  = array_sum(array_column(
    array_filter($orders, fn($o) => $o['status'] === 'Delivered'), 'total'
));

$status_counts = [
    'Delivered' => count(array_filter($orders, fn($o) => $o['status'] === 'Delivered')),
    'Pending'   => count(array_filter($orders, fn($o) => $o['status'] === 'Pending')),
    'Confirmed' => count(array_filter($orders, fn($o) => $o['status'] === 'Confirmed')),
    'Cancelled' => count(array_filter($orders, fn($o) => $o['status'] === 'Cancelled')),
];

// Monthly revenue — last 6 months
$monthly_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('M Y', strtotime("-{$i} months"));
    $key   = date('Y-m',  strtotime("-{$i} months"));
    $monthly_revenue[$label] = 0;
    foreach ($orders as $o) {
        if ($o['status'] === 'Delivered' && substr($o['date'], 0, 7) === $key)
            $monthly_revenue[$label] += $o['total'];
    }
}

$customers       = $pdo->query("SELECT * FROM customers")->fetchAll();
$total_customers = count($customers);
$new_customers   = count(array_filter($customers, fn($c) => $c['status'] === 'New'));

$menu_items      = $pdo->query("SELECT * FROM menu_items")->fetchAll();
$category_sales  = [];
foreach ($menu_items as $m) {
    $cat = $m['category'] ?? 'Other';
    $category_sales[$cat] = ($category_sales[$cat] ?? 0) + 1;
}

$staff               = $pdo->query("SELECT * FROM staff")->fetchAll();
$staff_on_duty       = count(array_filter($staff, fn($s) => $s['status'] === 'On Duty'));
$staff_on_leave      = count(array_filter($staff, fn($s) => $s['status'] === 'Leave'));
$staff_total_payroll = array_sum(array_column($staff, 'salary'));

$inventory  = $pdo->query("SELECT * FROM inventory")->fetchAll();
$low_stock  = array_values(array_filter($inventory, fn($i) => $i['stock'] <= $i['min_stock']));

$today           = date('Y-m-d');
$reservations    = $pdo->query("SELECT * FROM reservations")->fetchAll();
$todays_res      = count(array_filter($reservations, fn($r) => $r['date'] === $today));
$pending_res     = count(array_filter($reservations, fn($r) => $r['status'] === 'Pending'));
$todays_reservations = array_filter($reservations, fn($r) => $r['date'] === $today);

// ════════════════════════════════════════
//  CHART DATA
// ════════════════════════════════════════
$rev_labels = json_encode(array_keys($monthly_revenue));
$rev_vals   = json_encode(array_values($monthly_revenue));
$cat_labels = json_encode(array_keys($category_sales));
$cat_vals   = json_encode(array_values($category_sales));
$st_labels  = json_encode(array_keys($status_counts));
$st_vals    = json_encode(array_values($status_counts));

$today_label = date('l, d F Y');
$page_title  = 'Dashboard';

$page_scripts = <<<JS
buildLineChart('revenueChart',
    {$rev_labels},
    [{
        label: 'Revenue',
        data: {$rev_vals},
        borderColor: '#e8622a',
        backgroundColor: 'rgba(232,98,42,0.1)',
        fill: true, tension: 0.4, pointRadius: 4,
        pointBackgroundColor: '#e8622a'
    }]
);
buildDoughnutChart('categoryChart',
    {$cat_labels}, {$cat_vals},
    ['#e8622a','#f5c842','#3ecf8e','#4e9cf7','#a855f7','#f472b6','#6b7280']
);
buildBarChart('statusChart',
    {$st_labels}, {$st_vals},
    ['#3ecf8e','#f5c842','#4e9cf7','#e84242']
);
JS;

function badge($status) {
    $map = [
        'Delivered' => 'badge-green',  'Pending'  => 'badge-yellow',
        'Confirmed' => 'badge-blue',   'Preparing'=> 'badge-orange',
        'Cancelled' => 'badge-red',
    ];
    return '<span class="badge '.($map[$status] ?? 'badge-gray').'">'.$status.'</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Good day 👋</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">
            Here's what's happening at <strong style="color:var(--accent-l)">Minmi Restaurent</strong> — <?= $today_label ?>
        </p>
    </div>
    <span class="badge badge-green" style="font-size:.72rem;padding:5px 12px">🟢 System Online</span>
</div>

<!-- KPI STATS -->
<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-icon">💰</div>
        <div class="stat-label">Revenue (Delivered)</div>
        <div class="stat-value">Rs. <?= number_format($total_revenue, 0) ?></div>
        <div class="stat-sub">All time</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">🧾</div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?= $total_orders ?></div>
        <div class="stat-sub"><?= $pending_orders ?> pending</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">⏳</div>
        <div class="stat-label">Pending Orders</div>
        <div class="stat-value"><?= $pending_orders ?></div>
        <div class="stat-sub">Need attention</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">👥</div>
        <div class="stat-label">Customers</div>
        <div class="stat-value"><?= $total_customers ?></div>
        <div class="stat-sub"><?= $new_customers ?> new</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value"><?= count($low_stock) ?></div>
        <div class="stat-sub">Requires restocking</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon">📅</div>
        <div class="stat-label">Today's Reservations</div>
        <div class="stat-value"><?= $todays_res ?></div>
        <div class="stat-sub"><?= $pending_res ?> pending</div>
    </div>
</div>

<!-- CHARTS ROW 1 -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Monthly Revenue</div>
            <span class="badge badge-orange">Last 6 months</span>
        </div>
        <div class="chart-box"><canvas id="revenueChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">Menu Items by Category</div>
            <span class="badge badge-blue"><?= count($category_sales) ?> categories</span>
        </div>
        <div class="chart-box"><canvas id="categoryChart"></canvas></div>
    </div>
</div>

<!-- CHARTS ROW 2 -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Order Status Breakdown</div>
            <span class="badge badge-gray"><?= $total_orders ?> total</span>
        </div>
        <div class="chart-box"><canvas id="statusChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">Recent Orders</div>
            <a href="orders.php" class="btn btn-ghost btn-sm">View all →</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Order ID</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-3);padding:24px">No orders yet</td></tr>
                <?php else: foreach (array_slice($orders, 0, 6) as $o): ?>
                    <tr>
                        <td><code style="color:var(--accent-l);font-size:.78rem"><?= htmlspecialchars($o['id']) ?></code></td>
                        <td><?= htmlspecialchars($o['customer']) ?></td>
                        <td><strong>Rs. <?= number_format($o['total'], 2) ?></strong></td>
                        <td><?= badge($o['status']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ROW 3 — Quick Summary + Low Stock -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Quick Summary</div>
            <span class="badge badge-gray">Live</span>
        </div>
        <?php
        $summary = [
            ['👨‍🍳','Staff On Duty',    $staff_on_duty  .' members',         'var(--green)'],
            ['🏖️', 'Staff On Leave',   $staff_on_leave .' members',         'var(--yellow)'],
            ['📦', 'Total Inventory',  count($inventory).' items tracked',  'var(--blue)'],
            ['⚠️', 'Low Stock Items',  count($low_stock).' need restock',   'var(--red)'],
            ['🍽️', 'Menu Items',       count($menu_items).' items',         'var(--accent)'],
            ['📅', 'Reservations Today',$todays_res.' bookings',            '#a855f7'],
            ['💵', 'Monthly Payroll',  'Rs. '.number_format($staff_total_payroll).' / month','#a855f7'],
        ];
        foreach ($summary as [$icon,$label,$value,$color]):
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:11px 0;border-bottom:1px solid var(--border);font-size:.84rem">
            <span style="display:flex;align-items:center;gap:9px;color:var(--text-2)">
                <span><?= $icon ?></span><?= $label ?>
            </span>
            <strong style="color:<?= $color ?>"><?= $value ?></strong>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Low Stock Alert</div>
            <a href="inventory.php" class="btn btn-ghost btn-sm">Manage →</a>
        </div>
        <?php if (empty($low_stock)): ?>
        <div style="text-align:center;padding:32px;color:var(--text-3)">
            <div style="font-size:2rem;margin-bottom:8px">✅</div>
            <div style="font-size:.85rem">All inventory levels are healthy!</div>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Stock</th><th>Min</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($low_stock, 0, 6) as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><strong style="color:var(--red)"><?= $item['stock'] ?> <?= htmlspecialchars($item['unit'] ?? '') ?></strong></td>
                    <td style="color:var(--text-3)"><?= $item['min_stock'] ?></td>
                    <td><span class="badge badge-red">Low Stock</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ROW 4 — Today's Reservations -->
<?php if (!empty($todays_reservations)): ?>
<div class="card">
    <div class="card-header">
        <div class="card-title">📅 Today's Reservations</div>
        <a href="reservations.php" class="btn btn-ghost btn-sm">View all →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
        <?php
        $res_badge = ['Pending'=>'badge-yellow','Confirmed'=>'badge-blue','Seated'=>'badge-orange','Completed'=>'badge-green','Cancelled'=>'badge-red'];
        foreach ($todays_reservations as $r): ?>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <strong style="font-size:.88rem"><?= htmlspecialchars($r['customer_name']) ?></strong>
                <span class="badge <?= $res_badge[$r['status']] ?? 'badge-gray' ?>"><?= $r['status'] ?></span>
            </div>
            <div style="font-size:.76rem;color:var(--text-3)">
                🕐 <?= date('g:i A', strtotime($r['time'])) ?>
                <?php if ($r['phone']): ?>&nbsp;·&nbsp; 📞 <?= htmlspecialchars($r['phone']) ?><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.stat-card.purple{border-top:3px solid #a855f7}
.stat-card.purple .stat-icon,.stat-card.purple .stat-value{color:#a855f7}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>