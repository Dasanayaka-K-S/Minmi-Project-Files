<?php
// ============================================================
//  REPORTS — Minmi Restaurent Admin
//  Place in: dashboard/pages/reports.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$orders           = $pdo->query("SELECT * FROM orders")->fetchAll();
$delivered_orders = array_values(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));
$cancelled        = array_values(array_filter($orders, fn($o) => $o['status'] === 'Cancelled'));
$pending          = array_values(array_filter($orders, fn($o) => $o['status'] === 'Pending'));
$processing       = array_values(array_filter($orders, fn($o) => $o['status'] === 'Processing'));

$total_rev   = count($delivered_orders) ? array_sum(array_column($delivered_orders, 'total')) : 0;
$avg_order   = count($delivered_orders) ? $total_rev / count($delivered_orders) : 0;
$top_order   = count($delivered_orders) ? max(array_column($delivered_orders, 'total')) : 0;
$cancel_rate = count($orders)           ? round((count($cancelled) / count($orders)) * 100, 1) : 0;

$card_orders  = count(array_filter($orders, fn($o) => strtolower($o['payment']) === 'card'));
$cash_orders  = count(array_filter($orders, fn($o) => strtolower($o['payment']) === 'cash'));
$other_orders = count($orders) - $card_orders - $cash_orders;

$customers          = $pdo->query("SELECT * FROM customers")->fetchAll();
$vip_count          = count(array_filter($customers, fn($c) => $c['status'] === 'VIP'));
$new_cust           = count(array_filter($customers, fn($c) => $c['status'] === 'New'));
$total_cust_revenue = array_sum(array_column($customers, 'total_spent'));

$menu_items        = $pdo->query("SELECT * FROM menu_items")->fetchAll();
$active_menu_count = count(array_filter($menu_items, fn($m) => $m['status'] === 'Available'));

$inventory       = $pdo->query("SELECT * FROM inventory")->fetchAll();
$low_stock_count = count(array_filter($inventory, fn($i) => $i['stock'] <= $i['min_stock']));

$staff        = $pdo->query("SELECT * FROM staff")->fetchAll();
$active_staff = count(array_filter($staff, fn($s) => $s['status'] === 'Active'));

// Best sellers
$item_counts = [];
foreach ($orders as $o) {
    $raw = $o['items'] ?? '';
    if (!$raw) continue;
    $items = is_array($raw) ? $raw : json_decode($raw, true);
    if (!$items) continue;
    foreach ($items as $item) {
        $name = is_array($item) ? ($item['name'] ?? $item[0] ?? '') : (string)$item;
        if ($name) $item_counts[$name] = ($item_counts[$name] ?? 0) + 1;
    }
}
arsort($item_counts);
$best_sellers = array_slice($item_counts, 0, 5, true);

$status_counts = [
    'Delivered'  => count($delivered_orders),
    'Pending'    => count($pending),
    'Processing' => count($processing),
    'Cancelled'  => count($cancelled),
];

usort($customers, fn($a,$b) => $b['total_spent'] <=> $a['total_spent']);
$top_customers  = array_slice($customers, 0, 5);
$status_labels  = json_encode(array_keys($status_counts));
$status_data    = json_encode(array_values($status_counts));
$top_cust_names = json_encode(array_column($top_customers, 'name'));
$top_cust_spent = json_encode(array_column($top_customers, 'total_spent'));
$generated_at   = date('F j, Y  g:i A');
$page_title     = 'Reports';

// ════════════════════════════════════════
//  AI ANALYSIS — computed from DB data
// ════════════════════════════════════════

// Monthly revenue for trend
$monthly_rev = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $monthly_rev[$key] = 0;
    foreach ($delivered_orders as $o) {
        if (substr($o['date'], 0, 7) === $key)
            $monthly_rev[$key] += $o['total'];
    }
}
$rev_values = array_values($monthly_rev);
$n = count($rev_values);

// Forecast
$forecast = 0;
if ($n >= 2) {
    $weights   = [1,1,2,2,3,3];
    $ws = $wv = 0;
    foreach ($rev_values as $i => $v) { $w=$weights[$i]??1; $ws+=$w; $wv+=$v*$w; }
    $wavg   = $ws > 0 ? $wv/$ws : 0;
    $last   = $rev_values[$n-1] ?? 0;
    $prev   = $rev_values[$n-2] ?? 0;
    $growth = $prev > 0 ? ($last-$prev)/$prev : 0;
    $forecast = round($wavg * (1 + $growth));
}
$forecast_dir = $forecast > ($rev_values[$n-1] ?? 0) ? 'up' : 'down';

// Best seller
arsort($item_counts);
$top_item     = key($item_counts) ?? 'N/A';
$top_item_cnt = current($item_counts) ?: 0;

// Second best
$item_keys = array_keys($item_counts);
$second_item = isset($item_keys[1]) ? $item_keys[1] : null;

// Anomaly: sudden drop in last month
$last_month_rev  = $rev_values[$n-1] ?? 0;
$prev_month_rev  = $rev_values[$n-2] ?? 0;
$revenue_drop    = $prev_month_rev > 0 && $last_month_rev < ($prev_month_rev * 0.7);
$revenue_change_pct = $prev_month_rev > 0
    ? round((($last_month_rev - $prev_month_rev) / $prev_month_rev) * 100, 1)
    : 0;

// Payment preference
$dominant_payment = $card_orders >= $cash_orders ? 'Card' : 'Cash';
$dominant_pct     = count($orders) > 0
    ? round((max($card_orders, $cash_orders) / count($orders)) * 100, 1) : 0;

// Customer health
$repeat_customers = count(array_filter($customers, fn($c) => ($c['orders_count'] ?? 0) > 1));
$repeat_rate      = count($customers) > 0
    ? round(($repeat_customers / count($customers)) * 100, 1) : 0;

// AI recommendations
$ai_recommendations = [];
if ($cancel_rate > 20)
    $ai_recommendations[] = ['🚨', 'High cancellation rate ('.$cancel_rate.'%). Review kitchen speed and stock availability.', 'red'];
if ($low_stock_count > 0)
    $ai_recommendations[] = ['📦', $low_stock_count.' inventory items below minimum. Reorder before stock runs out.', 'orange'];
if ($repeat_rate < 30)
    $ai_recommendations[] = ['👥', 'Only '.$repeat_rate.'% repeat customer rate. Launch a loyalty rewards program.', 'blue'];
if ($forecast_dir === 'up')
    $ai_recommendations[] = ['📈', 'Revenue trending upward. Consider expanding menu or increasing staff during peak hours.', 'green'];
if ($top_item !== 'N/A')
    $ai_recommendations[] = ['🍽️', '"'.$top_item.'" is your top seller ('.$top_item_cnt.' orders). Always keep it in stock.', 'orange'];
if ($dominant_pct > 60)
    $ai_recommendations[] = ['💳', $dominant_pct.'% of customers pay by '.$dominant_payment.'. Ensure this method is always available.', 'blue'];

$page_scripts = <<<JS
buildDoughnutChart('orderStatusChart',{$status_labels},{$status_data},['#3ecf8e','#f5c842','#4e9cf7','#e84242']);
buildDoughnutChart('paymentChart',['Card','Cash','Other'],[{$card_orders},{$cash_orders},{$other_orders}],['#4e9cf7','#3ecf8e','#a855f7']);
buildBarChart('topCustChart',{$top_cust_names},{$top_cust_spent},'#f5c842');
JS;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- PAGE HEADER -->
<div class="page-header no-print">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Reports</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Business performance summary and analytics.</p>
    </div>
    <button class="btn btn-primary" onclick="exportPDF()">🖨️ Export PDF</button>
</div>

<!-- PDF Header (print only) -->
<div class="pdf-header print-only">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;
                border-bottom:2px solid #e8622a;padding-bottom:16px;margin-bottom:24px">
        <div>
            <div style="font-size:1.6rem;font-weight:700;color:#e8622a">🔥 Minmi Restaurent</div>
            <div style="font-size:.85rem;color:#888;margin-top:4px">Business Performance Report</div>
        </div>
        <div style="text-align:right;font-size:.8rem;color:#888">
            <div>Generated: <?= $generated_at ?></div>
            <div style="margin-top:4px">All time data</div>
        </div>
    </div>
</div>

<?php if (empty($orders)): ?>
<div class="card" style="text-align:center;padding:48px 24px">
    <div style="font-size:3rem;margin-bottom:12px">📊</div>
    <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">No data yet</h3>
    <p style="color:var(--text-3);font-size:.85rem">Add some orders and reports will appear here automatically.</p>
    <a href="orders.php" class="btn btn-primary" style="margin-top:16px;display:inline-flex">＋ Go to Orders</a>
</div>
<?php else: ?>

<!-- ══════════════════════════════════════
     SECTION 1 — KPI STATS
     ══════════════════════════════════════ -->
<div class="pdf-section-title print-only">📊 Key Performance Indicators</div>
<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-icon">💰</div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value">Rs. <?= number_format($total_rev, 0) ?></div>
        <div class="stat-sub">Delivered orders only</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">📊</div>
        <div class="stat-label">Avg. Order Value</div>
        <div class="stat-value">Rs. <?= number_format($avg_order, 2) ?></div>
        <div class="stat-sub">Per delivered order</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🏆</div>
        <div class="stat-label">Highest Order</div>
        <div class="stat-value">Rs. <?= number_format($top_order, 2) ?></div>
        <div class="stat-sub">Single order record</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">🚫</div>
        <div class="stat-label">Cancellation Rate</div>
        <div class="stat-value"><?= $cancel_rate ?>%</div>
        <div class="stat-sub"><?= count($cancelled) ?> cancelled orders</div>
    </div>
</div>

<!-- ══════════════════════════════════════
     SECTION AI — AI ANALYSIS
     ══════════════════════════════════════ -->
<div class="pdf-section-title print-only">🤖 AI Business Analysis</div>
<div class="report-section card" id="sec-ai">
    <div class="card-header">
        <div class="card-title">🤖 AI Business Analysis</div>
        <div style="display:flex;gap:8px">
            <span class="badge badge-purple">Powered by AI</span>
            <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-ai','AI Business Analysis')">🖨️ Print</button>
        </div>
    </div>

    <!-- Calc box: print only -->
    <div class="calc-box print-only">
        <div class="calc-title">How AI Insights are Generated</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Revenue forecast uses a weighted 6-month trend with growth rate calculation.</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Recommendations are generated by analyzing cancellation rate, stock levels, customer retention, and payment patterns.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>All insights are derived in real-time from the live database.</span></div>
        </div>
    </div>

    <!-- AI Metric Cards -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">

        <!-- Revenue Forecast -->
        <div class="ai-metric <?= $forecast_dir === 'up' ? 'ai-metric-green' : 'ai-metric-red' ?>">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px">
                <?= $forecast_dir === 'up' ? '📈' : '📉' ?> Revenue Forecast
            </div>
            <div style="font-size:1.3rem;font-weight:700;margin-bottom:4px">Rs. <?= number_format($forecast, 0) ?></div>
            <div style="font-size:.76rem;opacity:.8">
                Predicted next month · 
                <?php if ($revenue_change_pct != 0): ?>
                    Last month <?= $revenue_change_pct > 0 ? '+' : '' ?><?= $revenue_change_pct ?>% vs prior month
                <?php else: ?>
                    Based on 6-month weighted average
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Seller -->
        <div class="ai-metric ai-metric-orange">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px">🏆 Top Selling Item</div>
            <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($top_item) ?></div>
            <div style="font-size:.76rem;opacity:.8">
                <?= $top_item_cnt ?> orders
                <?php if ($second_item): ?> · Runner up: <?= htmlspecialchars($second_item) ?><?php endif; ?>
            </div>
        </div>

        <!-- Customer Retention -->
        <div class="ai-metric <?= $repeat_rate >= 30 ? 'ai-metric-green' : 'ai-metric-blue' ?>">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px">👥 Customer Retention</div>
            <div style="font-size:1.3rem;font-weight:700;margin-bottom:4px"><?= $repeat_rate ?>%</div>
            <div style="font-size:.76rem;opacity:.8">
                <?= $repeat_customers ?> of <?= count($customers) ?> customers returned · <?= $vip_count ?> VIP
            </div>
        </div>

    </div>

    <!-- Revenue Anomaly Alert -->
    <?php if ($revenue_drop): ?>
    <div style="background:rgba(232,66,66,.08);border:1px solid rgba(232,66,66,.3);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
        <span style="font-size:1.4rem">⚠️</span>
        <div>
            <div style="font-size:.82rem;font-weight:700;color:var(--red,#e84242)">Revenue Anomaly Detected</div>
            <div style="font-size:.78rem;color:var(--text-2);margin-top:2px">
                Last month revenue dropped <?= abs($revenue_change_pct) ?>% compared to the previous month.
                Consider reviewing order fulfillment, promotions, or seasonal factors.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- AI Recommendations -->
    <?php if (!empty($ai_recommendations)): ?>
    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-3);margin-bottom:10px">
        💡 AI Recommendations
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
        <?php
        $rec_colors = [
            'red'    => ['bg'=>'rgba(232,66,66,.07)',   'border'=>'rgba(232,66,66,.25)',   'dot'=>'#e84242'],
            'orange' => ['bg'=>'rgba(232,98,42,.07)',   'border'=>'rgba(232,98,42,.25)',   'dot'=>'#e8622a'],
            'blue'   => ['bg'=>'rgba(78,156,247,.07)',  'border'=>'rgba(78,156,247,.25)',  'dot'=>'#4e9cf7'],
            'green'  => ['bg'=>'rgba(62,207,142,.07)',  'border'=>'rgba(62,207,142,.25)',  'dot'=>'#3ecf8e'],
        ];
        foreach ($ai_recommendations as [$icon, $text, $color]):
            $c = $rec_colors[$color] ?? $rec_colors['blue'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 14px;
                    background:<?= $c['bg'] ?>;border:1px solid <?= $c['border'] ?>;border-radius:var(--radius)">
            <span style="font-size:1rem;flex-shrink:0;margin-top:1px"><?= $icon ?></span>
            <span style="font-size:.82rem;color:var(--text-2);line-height:1.5"><?= htmlspecialchars($text) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════
     SECTION 2 — ORDER STATUS BREAKDOWN
     ══════════════════════════════════════ -->
<div class="pdf-section-title print-only">📦 Order & Payment Breakdown</div>
<div class="report-section card" id="sec-orders">
    <div class="card-header">
        <div class="card-title">📦 Order Status Breakdown</div>
        <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-orders','Order Status Breakdown')">🖨️ Print</button>
    </div>
    <div class="calc-box print-only">
        <div class="calc-title">How Order Statuses Work</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Every order has one of 4 statuses: <strong>Delivered, Pending, Processing, Cancelled</strong>.</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Percentages = (status count ÷ <?= count($orders) ?> total orders) × 100</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>Revenue is only counted from <strong>Delivered</strong> orders.</span></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
        <div class="chart-box print-chart"><canvas id="orderStatusChart"></canvas></div>
        <div style="display:flex;flex-direction:column;gap:8px;justify-content:center">
            <?php
            $st_colors = ['Delivered'=>'#3ecf8e','Pending'=>'#f5c842','Processing'=>'#4e9cf7','Cancelled'=>'#e84242'];
            $st_icons  = ['Delivered'=>'✅','Pending'=>'⏳','Processing'=>'🔄','Cancelled'=>'❌'];
            foreach ($status_counts as $st => $cnt):
                $pct = count($orders) > 0 ? round(($cnt / count($orders)) * 100, 1) : 0;
            ?>
            <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
                <span style="font-size:1rem;width:20px;text-align:center"><?= $st_icons[$st] ?? '•' ?></span>
                <span style="font-size:.82rem;font-weight:500;min-width:80px"><?= $st ?></span>
                <div style="flex:1;height:7px;background:var(--bg-3);border-radius:4px;overflow:hidden">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $st_colors[$st] ?? '#ccc' ?>;border-radius:4px"></div>
                </div>
                <span style="font-size:.8rem;font-weight:700;min-width:24px;text-align:right"><?= $cnt ?></span>
                <span style="font-size:.75rem;color:var(--text-3);min-width:40px;text-align:right"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     SECTION 3 — PAYMENT METHODS
     ══════════════════════════════════════ -->
<div class="report-section card" id="sec-payment">
    <div class="card-header">
        <div class="card-title">💳 Payment Methods</div>
        <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-payment','Payment Methods')">🖨️ Print</button>
    </div>
    <div class="calc-box print-only">
        <div class="calc-title">How Payment Split is Calculated</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Each order's <strong>payment</strong> column is checked (case-insensitive).</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Card = <?= $card_orders ?>, Cash = <?= $cash_orders ?>, Other = <?= $other_orders ?> out of <?= count($orders) ?> total orders.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>Percentage = (type count ÷ total orders) × 100</span></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
        <div class="chart-box print-chart"><canvas id="paymentChart"></canvas></div>
        <div style="display:flex;flex-direction:column;gap:12px;justify-content:center">
            <?php foreach([
                ['💳','Card',  $card_orders,  '#4e9cf7'],
                ['💵','Cash',  $cash_orders,  '#3ecf8e'],
                ['📱','Other', $other_orders, '#a855f7'],
            ] as [$ic,$lb,$cnt,$col]):
                $pct = count($orders) > 0 ? round(($cnt / count($orders)) * 100, 1) : 0;
            ?>
            <div style="display:flex;align-items:center;gap:8px;padding:6px 0">
                <span style="font-size:1rem;width:20px;text-align:center"><?= $ic ?></span>
                <span style="font-size:.82rem;font-weight:500;min-width:80px"><?= $lb ?></span>
                <div style="flex:1;height:7px;background:var(--bg-3);border-radius:4px;overflow:hidden">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:4px"></div>
                </div>
                <span style="font-size:.8rem;font-weight:700;min-width:24px;text-align:right"><?= $cnt ?></span>
                <span style="font-size:.75rem;color:var(--text-3);min-width:40px;text-align:right"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     SECTION 4 — TOP SELLING ITEMS
     ══════════════════════════════════════ -->
<div class="report-section card" id="sec-sellers">
    <div class="card-header">
        <div class="card-title">🍽️ Top Selling Items</div>
        <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-sellers','Top Selling Items')">🖨️ Print</button>
    </div>
    <div class="calc-box print-only">
        <div class="calc-title">How Top Items are Calculated</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Each order's <strong>items</strong> field is parsed (supports plain text and JSON).</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Each item name is counted across <strong>all orders</strong> regardless of status.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>Items are sorted by frequency — top 5 are shown.</span></div>
        </div>
    </div>
    <?php if (empty($best_sellers)): ?>
    <div style="text-align:center;padding:24px;color:var(--text-3);font-size:.84rem">No item data found in orders yet.</div>
    <?php else:
        $max_sales = max($best_sellers); $rank = 1;
        foreach ($best_sellers as $name => $count):
            $pct = round(($count / $max_sales) * 100);
    ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
        <span style="background:var(--accent);color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0"><?= $rank++ ?></span>
        <span style="min-width:160px;font-size:.85rem;font-weight:500"><?= htmlspecialchars(mb_strimwidth($name,0,24,'…')) ?></span>
        <div style="flex:1;height:8px;background:var(--bg-3);border-radius:4px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:var(--accent);border-radius:4px;transition:width .6s"></div>
        </div>
        <span style="font-size:.8rem;color:var(--text-2);min-width:60px;text-align:right"><?= $count ?> orders</span>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- ══════════════════════════════════════
     SECTION 5 — TOP CUSTOMERS
     ══════════════════════════════════════ -->
<div class="report-section card" id="sec-customers">
    <div class="card-header">
        <div class="card-title">👥 Top 5 Customers by Spend</div>
        <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-customers','Top Customers by Spend')">🖨️ Print</button>
    </div>
    <div class="calc-box print-only">
        <div class="calc-title">How Top Customers are Ranked</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>All customers sorted by <strong>total_spent</strong> column (descending).</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Top 5 selected and displayed with bar chart.</span></div>
        </div>
    </div>
    <?php if (empty($top_customers)): ?>
    <div style="text-align:center;padding:24px;color:var(--text-3);font-size:.84rem">No customer data yet.</div>
    <?php else: ?>
    <div class="chart-box print-chart" style="margin-top:12px"><canvas id="topCustChart"></canvas></div>
    <div style="margin-top:16px">
        <?php $rank=1; foreach($top_customers as $c): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:.84rem">
            <span style="background:var(--accent);color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0"><?= $rank++ ?></span>
            <span style="flex:1;font-weight:600"><?= htmlspecialchars($c['name']) ?></span>
            <span class="badge badge-gray"><?= htmlspecialchars($c['status'] ?? '') ?></span>
            <strong style="color:var(--accent-l)">Rs. <?= number_format($c['total_spent'],2) ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════
     SECTION 6 — FINANCIAL SUMMARY
     ══════════════════════════════════════ -->
<div class="pdf-section-title print-only">💰 Financial Summary</div>
<div class="report-section card" id="sec-financial">
    <div class="card-header">
        <div class="card-title">📋 Financial Summary</div>
        <div style="display:flex;gap:8px">
            <span class="badge badge-gray">All time</span>
            <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-financial','Financial Summary')">🖨️ Print</button>
        </div>
    </div>
    <div class="calc-box print-only">
        <div class="calc-title">Data Sources</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Order data from <code>orders</code> table — counts and totals.</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Customer data from <code>customers</code> table — status counts and total_spent sum.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>Menu, staff and inventory counts from their respective tables.</span></div>
        </div>
    </div>
    <?php
    $metrics = [
        ['📦 Total Orders',             count($orders),                                  ''],
        ['✅ Delivered Orders',          count($delivered_orders),                        'var(--green,#3ecf8e)'],
        ['❌ Cancelled Orders',          count($cancelled),                               'var(--red,#e84242)'],
        ['⏳ Pending Orders',            count($pending),                                 'var(--yellow,#f5c842)'],
        ['🔄 Processing Orders',         count($processing),                              'var(--blue,#4e9cf7)'],
        ['💰 Total Revenue (Delivered)', 'Rs. ' . number_format($total_rev, 2),           'var(--accent-l,#e8622a)'],
        ['📊 Average Order Value',       'Rs. ' . number_format($avg_order, 2),           ''],
        ['🏆 Highest Single Order',      'Rs. ' . number_format($top_order, 2),           ''],
        ['🚫 Cancellation Rate',         $cancel_rate . '%',                              'var(--yellow,#f5c842)'],
        ['💳 Card Payments',             $card_orders . ' orders',                        'var(--blue,#4e9cf7)'],
        ['💵 Cash Payments',             $cash_orders . ' orders',                        'var(--green,#3ecf8e)'],
        ['👥 Total Customers',           count($customers),                               ''],
        ['⭐ VIP Customers',             $vip_count,                                      'var(--accent-l,#e8622a)'],
        ['🆕 New Customers',             $new_cust,                                       'var(--blue,#4e9cf7)'],
        ['💸 Total Customer Spend',      'Rs. ' . number_format($total_cust_revenue, 2),  'var(--accent-l,#e8622a)'],
        ['🔁 Repeat Customer Rate',      $repeat_rate . '%',                              'var(--green,#3ecf8e)'],
        ['🍽️ Active Menu Items',         $active_menu_count,                              ''],
        ['👨‍🍳 Active Staff',              $active_staff,                                   ''],
        ['⚠️ Low Stock Items',           $low_stock_count,                                'var(--red,#e84242)'],
    ];
    foreach ($metrics as [$label, $value, $color]):
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:.84rem">
        <span style="color:var(--text-2)"><?= $label ?></span>
        <strong style="<?= $color ? 'color:'.$color : '' ?>"><?= $value ?></strong>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════
     SECTION 7 — LOW STOCK ALERT
     ══════════════════════════════════════ -->
<?php if ($low_stock_count > 0):
    $low_items = array_filter($inventory, fn($i) => $i['stock'] <= $i['min_stock']);
?>
<div class="pdf-section-title print-only">⚠️ Inventory Alerts</div>
<div class="report-section card" id="sec-stock">
    <div class="card-header">
        <div class="card-title">⚠️ Low Stock Alert</div>
        <div style="display:flex;gap:8px">
            <span class="badge badge-red"><?= $low_stock_count ?> items</span>
            <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-stock','Low Stock Alert')">🖨️ Print</button>
        </div>
    </div>
    <div class="calc-box print-only">
        <div class="calc-title">How Low Stock is Detected</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Each inventory item has a <strong>min_stock</strong> threshold set by the admin.</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>If <code>stock ≤ min_stock</code>, the item is flagged as Low Stock.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span><?= $low_stock_count ?> item<?= $low_stock_count>1?'s':'' ?> currently below threshold.</span></div>
        </div>
    </div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>Item</th><th>Category</th><th>Current Stock</th><th>Min Stock</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($low_items as $item): ?>
            <tr>
                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                <td style="color:var(--text-2)"><?= htmlspecialchars($item['category'] ?? '—') ?></td>
                <td><strong style="color:var(--red,#e84242)"><?= $item['stock'] ?> <?= htmlspecialchars($item['unit'] ?? '') ?></strong></td>
                <td style="color:var(--text-3)"><?= $item['min_stock'] ?></td>
                <td><span class="badge badge-red">Low Stock</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════
     SECTION 8 — POWER BI DASHBOARD
     ══════════════════════════════════════ -->
<div class="report-section card no-print" id="sec-powerbi">
    <div class="card-header">
        <div class="card-title">📊 Power BI Dashboard</div>
        <span class="badge badge-blue">Business Intelligence</span>
    </div>
    <?php
    // Check if Power BI URL is saved in settings
    $pbi_url = '';
    try {
        $pbi_row = $pdo->query("SELECT value FROM settings WHERE `key`='powerbi_url'")->fetch();
        $pbi_url = $pbi_row['value'] ?? '';
    } catch (Exception $e) { $pbi_url = ''; }
    ?>
    <?php if ($pbi_url): ?>
    <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:var(--radius);margin-top:12px">
        <iframe src="<?= htmlspecialchars($pbi_url) ?>"
                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;border-radius:var(--radius)"
                allowfullscreen></iframe>
    </div>
    <p style="font-size:.75rem;color:var(--text-3);margin-top:8px;text-align:center">
        Live Power BI report embedded above. <a href="<?= htmlspecialchars($pbi_url) ?>" target="_blank" style="color:var(--accent)">Open in full screen →</a>
    </p>
    <?php else: ?>
    <div style="background:var(--bg-3);border:2px dashed var(--border);border-radius:var(--radius);padding:32px 24px;text-align:center;margin-top:12px">
        <div style="font-size:2.5rem;margin-bottom:12px">📊</div>
        <div style="font-family:'DM Serif Display',serif;font-size:1.1rem;margin-bottom:8px">Connect Your Power BI Report</div>
        <p style="color:var(--text-3);font-size:.83rem;line-height:1.6;max-width:480px;margin:0 auto 20px">
            Embed your Power BI dashboard here for advanced business intelligence visualization.
            Paste your Power BI publish URL in Settings to activate this section.
        </p>
        <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;text-align:left;max-width:520px;margin:0 auto 20px;font-size:.78rem">
            <div style="font-weight:700;color:var(--text-2);margin-bottom:8px">How to embed Power BI:</div>
            <div style="color:var(--text-3);line-height:1.8">
                1. Open Power BI → your report → <strong>File → Embed report → Publish to web</strong><br>
                2. Copy the <strong>embed URL</strong> (starts with https://app.powerbi.com/...)<br>
                3. Go to <a href="settings.php" style="color:var(--accent)">Settings</a> → add key <code style="background:var(--bg-3);padding:1px 5px;border-radius:3px">powerbi_url</code> with that URL<br>
                4. Come back here and the report will appear automatically.
            </div>
        </div>
        <a href="settings.php" class="btn btn-primary">⚙️ Go to Settings</a>
    </div>
    <?php endif; ?>
</div>

<!-- PDF Footer -->
<div class="print-only" style="margin-top:40px;padding-top:16px;border-top:1px solid #ddd;
     display:flex;justify-content:space-between;font-size:.75rem;color:#999">
    <span>🔥 Minmi Restaurent — Confidential</span>
    <span>Generated: <?= $generated_at ?></span>
</div>

<?php endif; ?>

<iframe id="printFrame" style="display:none"></iframe>

<link rel="stylesheet" href="../assets/css/reports.css">

<script>
    // Pass PHP-generated timestamp to external JS file
    window.REPORTS_GENERATED_AT = <?= json_encode($generated_at) ?>;
</script>
<script src="../assets/js/reports.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>