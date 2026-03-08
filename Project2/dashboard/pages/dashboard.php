<?php
// ============================================================
//  DASHBOARD CONTENT — Minmi Restaurent Admin
//  Place in: dashboard/pages/dashboard.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
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

// ── PEAK HOURS ANALYSIS (real data from created_at) ──────
$hour_counts = array_fill(0, 24, 0);
foreach ($orders as $o) {
    if (!empty($o['created_at'])) {
        $hour = (int)date('G', strtotime($o['created_at']));
        $hour_counts[$hour]++;
    }
}
// Busiest hour stats (for the insight badge)
$peak_hour_idx = array_search(max($hour_counts), $hour_counts);
$peak_hour_lbl = date('g A', mktime($peak_hour_idx, 0, 0));
$peak_hour_cnt = max($hour_counts);

$peak_labels = [];
$peak_vals   = [];
for ($h = 10; $h <= 22; $h++) {
    $peak_labels[] = date('g A', mktime($h, 0, 0));
    $peak_vals[]   = $hour_counts[$h];
}

// ── DAILY TARGET ─────────────────────────────────────────
$today_date    = date('Y-m-d');
$today_revenue = 0;
foreach ($orders as $o) {
    if ($o['status'] === 'Delivered' && $o['date'] === $today_date)
        $today_revenue += $o['total'];
}
try {
    $tgt_row      = $pdo->query("SELECT value FROM settings WHERE `key`='daily_revenue_target'")->fetch();
    $daily_target = $tgt_row ? (float)$tgt_row['value'] : 15000;
} catch (Exception $e) {
    $daily_target = 15000;
}
$target_pct = $daily_target > 0 ? min(100, round(($today_revenue / $daily_target) * 100, 1)) : 0;

// Pre-compute bar colour class to avoid nested ternary in HTML
if ($target_pct >= 100)     $bar_color = 'green';
elseif ($target_pct >= 60)  $bar_color = 'yellow';
else                         $bar_color = 'orange';

// Pre-compute percentage label colour
$pct_color = $target_pct >= 100 ? 'var(--green,#3ecf8e)' : 'var(--accent)';

$customers       = $pdo->query("SELECT * FROM customers")->fetchAll();
$total_customers = count($customers);
$new_customers   = count(array_filter($customers, fn($c) => $c['status'] === 'New'));

$menu_items     = $pdo->query("SELECT * FROM menu_items")->fetchAll();
$category_sales = [];
foreach ($menu_items as $m) {
    $cat = $m['category'] ?? 'Other';
    $category_sales[$cat] = ($category_sales[$cat] ?? 0) + 1;
}

$staff               = $pdo->query("SELECT * FROM staff")->fetchAll();
$staff_on_duty       = count(array_filter($staff, fn($s) => $s['status'] === 'On Duty'));
$staff_on_leave      = count(array_filter($staff, fn($s) => $s['status'] === 'Leave'));
$staff_total_payroll = array_sum(array_column($staff, 'salary'));

$inventory = $pdo->query("SELECT * FROM inventory")->fetchAll();
$low_stock = array_values(array_filter($inventory, fn($i) => $i['stock'] <= $i['min_stock']));

$today               = date('Y-m-d');
$reservations        = $pdo->query("SELECT * FROM reservations")->fetchAll();
$todays_res          = count(array_filter($reservations, fn($r) => $r['date'] === $today));
$pending_res         = count(array_filter($reservations, fn($r) => $r['status'] === 'Pending'));
$todays_reservations = array_filter($reservations, fn($r) => $r['date'] === $today);

// ════════════════════════════════════════
//  AI INSIGHTS
// ════════════════════════════════════════

// 1. Revenue forecast
$rev_values    = array_values($monthly_revenue);
$n             = count($rev_values);
$forecast_next = 0;
if ($n >= 2) {
    $weights   = [1, 1, 2, 2, 3, 3];
    $w_sum     = 0;
    $w_rev_sum = 0;
    foreach ($rev_values as $i => $v) {
        $w = $weights[$i] ?? 1;
        $w_sum     += $w;
        $w_rev_sum += $v * $w;
    }
    $weighted_avg  = $w_sum > 0 ? $w_rev_sum / $w_sum : 0;
    $last          = $rev_values[$n - 1] ?? 0;
    $prev          = $rev_values[$n - 2] ?? 0;
    $growth        = $prev > 0 ? ($last - $prev) / $prev : 0;
    $forecast_next = round($weighted_avg * (1 + $growth));
}
$forecast_trend = $forecast_next > ($rev_values[$n - 1] ?? 0) ? 'up' : 'down';

// 2. Best seller
$item_counts = [];
foreach ($orders as $o) {
    $raw = $o['items'] ?? '';
    if (!$raw) continue;
    $items = is_array($raw) ? $raw : json_decode($raw, true);
    if (!$items) continue;
    foreach ($items as $item) {
        $name = is_array($item) ? ($item['name'] ?? '') : (string)$item;
        if ($name) $item_counts[$name] = ($item_counts[$name] ?? 0) + 1;
    }
}
arsort($item_counts);
$top_item     = key($item_counts) ?? 'N/A';
$top_item_cnt = current($item_counts) ?: 0;

// 3. Smart reorder suggestions
$reorder_items = array_values(array_filter($inventory, fn($i) => $i['stock'] <= ($i['min_stock'] * 1.5)));
usort($reorder_items, fn($a, $b) => $a['stock'] <=> $b['stock']);
$reorder_items = array_slice($reorder_items, 0, 3);

// 4. Cancellation alert
$cancel_rate  = $total_orders > 0 ? round(($status_counts['Cancelled'] / $total_orders) * 100, 1) : 0;
$cancel_alert = $cancel_rate > 20;

// 5. Customer insight
$vip_count    = count(array_filter($customers, fn($c) => $c['status'] === 'VIP'));
$cust_insight = $vip_count > 0
    ? "You have {$vip_count} VIP customers. Consider a loyalty reward campaign."
    : "No VIP customers yet. Start a loyalty program to retain regulars.";

// ════════════════════════════════════════
//  CHART DATA
// ════════════════════════════════════════
$rev_labels    = json_encode(array_keys($monthly_revenue));
$rev_vals      = json_encode(array_values($monthly_revenue));
$cat_labels    = json_encode(array_keys($category_sales));
$cat_vals      = json_encode(array_values($category_sales));
$st_labels     = json_encode(array_keys($status_counts));
$st_vals       = json_encode(array_values($status_counts));
$peak_labels_j = json_encode($peak_labels);
$peak_vals_j   = json_encode($peak_vals);

$today_label = date('l, d F Y');
$page_title  = 'Dashboard';

$page_scripts = <<<JS
buildLineChart('revenueChart',
    {$rev_labels},
    [{
        label: 'Revenue (Rs.)',
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
buildBarChart('peakChart',
    {$peak_labels_j}, {$peak_vals_j},
    '#a855f7'
);
initTargetBar({$target_pct});
JS;

function badge($status) {
    $map = [
        'Delivered' => 'badge-green',
        'Pending'   => 'badge-yellow',
        'Confirmed' => 'badge-blue',
        'Preparing' => 'badge-orange',
        'Cancelled' => 'badge-red',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'badge-gray') . '">' . $status . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/dashboard.css">

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Good day 👋</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">
            Here's what's happening at <strong style="color:var(--accent-l)">Minmi Restaurent</strong> — <?= $today_label ?>
        </p>
    </div>
    <span class="badge badge-green" style="font-size:.72rem;padding:5px 12px">🟢 System Online</span>
</div>

<!-- ══════════════════════════════════════
     DAILY TARGET PROGRESS BAR
     ══════════════════════════════════════ -->
<div class="card target-bar-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <span style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3)">📎 Today's Revenue Target</span>
            <span style="font-size:.82rem;color:var(--text-2);margin-left:10px">
                Rs. <?= number_format($today_revenue, 0) ?> / Rs. <?= number_format($daily_target, 0) ?>
            </span>
        </div>
        <span style="font-size:1rem;font-weight:700;color:<?= $pct_color ?>"><?= $target_pct ?>%</span>
    </div>
    <div class="target-bar-track">
        <div class="target-bar-fill" id="targetBarFill" style="width:0%"></div>
    </div>
    <div style="font-size:.75rem;color:var(--text-3)">
        <?php if ($target_pct >= 100): ?>
            🎉 <strong style="color:#3ecf8e">Target reached!</strong> Great performance today.
        <?php elseif ($target_pct >= 60): ?>
            🔥 Almost there! Rs. <?= number_format($daily_target - $today_revenue, 0) ?> more to hit today's target.
        <?php else: ?>
            📈 Rs. <?= number_format($daily_target - $today_revenue, 0) ?> remaining to reach today's target.
        <?php endif; ?>
    </div>
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

<!-- ══════════════════════════════════════
     AI INSIGHTS PANEL
     ══════════════════════════════════════ -->
<div class="card ai-panel">
    <div class="card-header">
        <div class="card-title">🤖 AI Business Insights</div>
        <span class="badge badge-purple">Powered by AI</span>
    </div>
    <div class="ai-grid">

        <div class="ai-insight-card <?= $forecast_trend === 'up' ? 'ai-green' : 'ai-red' ?>">
            <div class="ai-insight-icon"><?= $forecast_trend === 'up' ? '📈' : '📉' ?></div>
            <div class="ai-insight-body">
                <div class="ai-insight-title">Revenue Forecast</div>
                <div class="ai-insight-value">Rs. <?= number_format($forecast_next, 0) ?></div>
                <div class="ai-insight-sub">
                    Predicted next month revenue based on 6-month trend.
                    <?= $forecast_trend === 'up' ? '↑ Growth expected.' : '↓ Revenue may decline.' ?>
                </div>
            </div>
        </div>

        <div class="ai-insight-card ai-orange">
            <div class="ai-insight-icon">🏆</div>
            <div class="ai-insight-body">
                <div class="ai-insight-title">Top Selling Item</div>
                <div class="ai-insight-value"><?= htmlspecialchars($top_item) ?></div>
                <div class="ai-insight-sub">
                    Ordered <?= $top_item_cnt ?> times. Stock this item consistently to maximize revenue.
                </div>
            </div>
        </div>

        <div class="ai-insight-card <?= $cancel_alert ? 'ai-red' : 'ai-green' ?>">
            <div class="ai-insight-icon"><?= $cancel_alert ? '🚨' : '✅' ?></div>
            <div class="ai-insight-body">
                <div class="ai-insight-title">Cancellation Rate</div>
                <div class="ai-insight-value"><?= $cancel_rate ?>%</div>
                <div class="ai-insight-sub">
                    <?= $cancel_alert
                        ? 'High cancellation rate detected! Review order fulfillment process.'
                        : 'Cancellation rate is healthy. Keep up the good service!' ?>
                </div>
            </div>
        </div>

        <div class="ai-insight-card ai-blue">
            <div class="ai-insight-icon">👥</div>
            <div class="ai-insight-body">
                <div class="ai-insight-title">Customer Insight</div>
                <div class="ai-insight-value"><?= $vip_count ?> VIP</div>
                <div class="ai-insight-sub"><?= htmlspecialchars($cust_insight) ?></div>
            </div>
        </div>

    </div>

    <!-- Smart Reorder Suggestions -->
    <?php if (!empty($reorder_items)): ?>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3);margin-bottom:10px">
            🛒 Smart Reorder Suggestions
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
            <?php foreach ($reorder_items as $ri): ?>
            <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;display:flex;align-items:center;gap:10px">
                <span style="font-size:1.1rem">📦</span>
                <div>
                    <div style="font-size:.83rem;font-weight:600"><?= htmlspecialchars($ri['name']) ?></div>
                    <div style="font-size:.74rem;color:var(--red,#e84242)">
                        Stock: <strong><?= $ri['stock'] ?> <?= htmlspecialchars($ri['unit'] ?? '') ?></strong>
                        &nbsp;·&nbsp; Min: <?= $ri['min_stock'] ?>
                    </div>
                </div>
                <a href="inventory.php" class="btn btn-ghost btn-sm" style="margin-left:4px">Reorder →</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
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
            <div class="card-title">🕐 Peak Hours Analysis</div>
            <span class="badge badge-purple">🔥 Busiest: <?= $peak_hour_lbl ?> (<?= $peak_hour_cnt ?> orders)</span>
        </div>
        <div class="chart-box"><canvas id="peakChart"></canvas></div>
        <div style="font-size:.74rem;color:var(--text-3);margin-top:8px;padding:0 4px">
            📊 Based on <strong><?= $total_orders ?></strong> real orders from <code>created_at</code> timestamps.
            Staff up around <strong style="color:#a855f7"><?= $peak_hour_lbl ?></strong> for best service.
        </div>
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
            <div class="card-title">Menu Items by Category</div>
            <span class="badge badge-blue"><?= count($category_sales) ?> categories</span>
        </div>
        <div class="chart-box"><canvas id="categoryChart"></canvas></div>
    </div>
</div>

<!-- ROW 3 — Recent Orders + Quick Summary -->
<div class="grid-2">
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

    <div class="card">
        <div class="card-header">
            <div class="card-title">Quick Summary</div>
            <span class="badge badge-gray">Live</span>
        </div>
        <?php
        $summary = [
            ['👨‍🍳', 'Staff On Duty',      $staff_on_duty   . ' members',         'var(--green)'],
            ['🏖️',  'Staff On Leave',      $staff_on_leave  . ' members',         'var(--yellow)'],
            ['📦',  'Total Inventory',     count($inventory). ' items tracked',   'var(--blue)'],
            ['⚠️',  'Low Stock Items',     count($low_stock). ' need restock',    'var(--red)'],
            ['🍽️',  'Menu Items',          count($menu_items). ' items',          'var(--accent)'],
            ['📅',  'Reservations Today',  $todays_res . ' bookings',             '#a855f7'],
            ['💵',  'Monthly Payroll',     'Rs. ' . number_format($staff_total_payroll) . ' / month', '#a855f7'],
        ];
        foreach ($summary as [$icon, $label, $value, $color]):
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--border);font-size:.84rem">
            <span style="display:flex;align-items:center;gap:9px;color:var(--text-2)">
                <span><?= $icon ?></span><?= $label ?>
            </span>
            <strong style="color:<?= $color ?>"><?= $value ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ROW 4 — Low Stock + Today's Reservations -->
<div class="grid-2">
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

    <div class="card">
        <div class="card-header">
            <div class="card-title">📅 Today's Reservations</div>
            <a href="reservations.php" class="btn btn-ghost btn-sm">View all →</a>
        </div>
        <?php if (empty($todays_reservations)): ?>
        <div style="text-align:center;padding:32px;color:var(--text-3)">
            <div style="font-size:2rem;margin-bottom:8px">📅</div>
            <div style="font-size:.85rem">No reservations today.</div>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:8px;padding:4px 0">
            <?php
            $res_badge = [
                'Pending'   => 'badge-yellow',
                'Confirmed' => 'badge-blue',
                'Seated'    => 'badge-orange',
                'Completed' => 'badge-green',
                'Cancelled' => 'badge-red',
            ];
            foreach (array_slice($todays_reservations, 0, 5) as $r): ?>
            <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;display:flex;justify-content:space-between;align-items:center">
                <div>
                    <strong style="font-size:.85rem"><?= htmlspecialchars($r['customer_name']) ?></strong>
                    <div style="font-size:.74rem;color:var(--text-3);margin-top:2px">🕐 <?= date('g:i A', strtotime($r['time'])) ?></div>
                </div>
                <span class="badge <?= $res_badge[$r['status']] ?? 'badge-gray' ?>"><?= $r['status'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>