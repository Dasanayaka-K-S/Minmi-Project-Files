<?php
// ============================================================
//  REPORTS — Minmi Restaurent Admin
//  Place in: dashboard/pages/reports.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// ══════════════════════════════════════════════════════════════
//  FETCH ALL DATA FROM DB
// ══════════════════════════════════════════════════════════════
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

$page_scripts = <<<JS
buildDoughnutChart('orderStatusChart',{$status_labels},{$status_data},['#3ecf8e','#f5c842','#4e9cf7','#e84242']);
buildDoughnutChart('paymentChart',['Card','Cash','Other'],[{$card_orders},{$cash_orders},{$other_orders}],['#4e9cf7','#3ecf8e','#a855f7']);
buildBarChart('topCustChart',{$top_cust_names},{$top_cust_spent},'#f5c842');
JS;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ══════════════════════════════════════
     PAGE HEADER
     ══════════════════════════════════════ -->
<div class="page-header no-print">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Reports</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Business performance summary and analytics.</p>
    </div>
    <button class="btn btn-primary" onclick="exportPDF()">🖨️ Export PDF</button>
</div>

<!-- PDF Header (only shows when printing) -->
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
     SECTION 2 — ORDER STATUS BREAKDOWN
     ══════════════════════════════════════ -->
<div class="pdf-section-title print-only">📦 Order & Payment Breakdown</div>
<div class="report-section card" id="sec-orders">
    <div class="card-header">
        <div class="card-title">📦 Order Status Breakdown</div>
        <button class="btn btn-ghost btn-sm no-print" onclick="printSection('sec-orders','Order Status Breakdown')">🖨️ Print</button>
    </div>

    <!-- How it's calculated -->
    <div class="calc-box">
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
    <div class="calc-box">
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
    <div class="calc-box">
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
        $max_sales = max($best_sellers);
        $rank = 1;
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
    <div class="calc-box">
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
     SECTION 6 — FINANCIAL SUMMARY TABLE
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
    <div class="calc-box">
        <div class="calc-title">Data Sources</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Order data from <code>orders</code> table — counts and totals.</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Customer data from <code>customers</code> table — status counts and total_spent sum.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>Menu, staff and inventory counts from their respective tables.</span></div>
        </div>
    </div>
    <?php
    $metrics = [
        ['📦 Total Orders',             count($orders),                              ''],
        ['✅ Delivered Orders',          count($delivered_orders),                   'var(--green,#3ecf8e)'],
        ['❌ Cancelled Orders',          count($cancelled),                           'var(--red,#e84242)'],
        ['⏳ Pending Orders',            count($pending),                             'var(--yellow,#f5c842)'],
        ['🔄 Processing Orders',         count($processing),                          'var(--blue,#4e9cf7)'],
        ['💰 Total Revenue (Delivered)', 'Rs. ' . number_format($total_rev, 2),       'var(--accent-l,#e8622a)'],
        ['📊 Average Order Value',       'Rs. ' . number_format($avg_order, 2),       ''],
        ['🏆 Highest Single Order',      'Rs. ' . number_format($top_order, 2),       ''],
        ['🚫 Cancellation Rate',         $cancel_rate . '%',                          'var(--yellow,#f5c842)'],
        ['💳 Card Payments',             $card_orders . ' orders',                    'var(--blue,#4e9cf7)'],
        ['💵 Cash Payments',             $cash_orders . ' orders',                    'var(--green,#3ecf8e)'],
        ['👥 Total Customers',           count($customers),                           ''],
        ['⭐ VIP Customers',             $vip_count,                                  'var(--accent-l,#e8622a)'],
        ['🆕 New Customers',             $new_cust,                                   'var(--blue,#4e9cf7)'],
        ['💸 Total Customer Spend',      'Rs. ' . number_format($total_cust_revenue, 2), 'var(--accent-l,#e8622a)'],
        ['🍽️ Active Menu Items',         $active_menu_count,                          ''],
        ['👨‍🍳 Active Staff',              $active_staff,                               ''],
        ['⚠️ Low Stock Items',           $low_stock_count,                            'var(--red,#e84242)'],
    ];
    foreach ($metrics as [$label, $value, $color]):
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;
         padding:10px 0;border-bottom:1px solid var(--border);font-size:.84rem">
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
    <div class="calc-box">
        <div class="calc-title">How Low Stock is Detected</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Each inventory item has a <strong>min_stock</strong> threshold set by the admin.</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>If <code>stock ≤ min_stock</code>, the item is flagged as Low Stock.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span><?= $low_stock_count ?> item<?= $low_stock_count>1?'s':'' ?> currently below threshold.</span></div>
        </div>
    </div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead>
                <tr><th>Item</th><th>Category</th><th>Current Stock</th><th>Min Stock</th><th>Status</th></tr>
            </thead>
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

<!-- PDF Footer -->
<div class="print-only" style="margin-top:40px;padding-top:16px;border-top:1px solid #ddd;
     display:flex;justify-content:space-between;font-size:.75rem;color:#999">
    <span>🔥 Minmi Restaurent — Confidential</span>
    <span>Generated: <?= $generated_at ?></span>
</div>

<?php endif; // end empty check ?>

<!-- Hidden print iframe -->
<iframe id="printFrame" style="display:none"></iframe>

<!-- ══════════════════════════════════════
     STYLES
     ══════════════════════════════════════ -->
<style>
/* Calculation box */
.calc-box{background:var(--bg-3);border-left:3px solid var(--accent);border-radius:0 var(--radius) var(--radius) 0;padding:14px 16px;margin:12px 0}
.calc-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--accent);margin-bottom:10px}
.calc-steps{display:flex;flex-direction:column;gap:8px}
.calc-step{display:flex;align-items:flex-start;gap:10px;font-size:.82rem;color:var(--text-2);line-height:1.5}
.calc-num{background:var(--accent);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;flex-shrink:0;margin-top:1px}
.calc-step code{background:var(--bg-2);padding:1px 6px;border-radius:4px;font-size:.78rem;color:var(--accent-l)}
.report-section{margin-bottom:16px}

/* Print-only elements hidden on screen */
.print-only{display:none}
.pdf-section-title{display:none}

/* ── PRINT / PDF STYLES ── */
@media print {
    .sidebar,.topbar,.sidebar-overlay,.no-print,.btn,button{display:none !important}
    .print-only{display:block !important}
    .pdf-section-title{display:block !important;font-size:1rem;font-weight:700;color:#e8622a;margin:24px 0 10px;padding-bottom:6px;border-bottom:1px solid #e8622a}
    body,html{background:#fff !important;color:#111 !important}
    .main-content,.page-wrapper,main{margin:0 !important;padding:16px !important}
    .card{border:1px solid #ddd !important;border-radius:8px !important;background:#fff !important;margin-bottom:16px !important;page-break-inside:avoid;box-shadow:none !important}
    .stats-grid{display:grid !important;grid-template-columns:repeat(4,1fr) !important;gap:10px !important;margin-bottom:16px !important}
    .stat-card{border:1px solid #ddd !important;border-radius:8px !important;padding:12px !important;background:#fff !important;box-shadow:none !important;page-break-inside:avoid}
    .stat-value{color:#111 !important;font-size:1.3rem !important}
    .print-chart{height:180px !important}
    canvas{max-height:180px !important}
    table{width:100% !important;border-collapse:collapse !important}
    th{background:#f5f5f5 !important;color:#333 !important;padding:8px !important;font-size:.75rem !important}
    td{padding:8px !important;font-size:.78rem !important;border-bottom:1px solid #eee !important;color:#333 !important}
    .badge{border:1px solid #ccc !important;color:#333 !important;background:#f5f5f5 !important;padding:2px 8px !important;border-radius:20px !important;font-size:.65rem !important}
    .calc-box{background:#fff8f5 !important;border-left:3px solid #e8622a !important}
    .calc-num{background:#e8622a !important}
}
</style>

<!-- ══════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════ -->
<script>
function exportPDF() {
    const btn = document.querySelector('button[onclick="exportPDF()"]');
    if (btn) { btn.textContent = '⏳ Preparing…'; btn.disabled = true; }
    setTimeout(() => {
        window.print();
        if (btn) { btn.textContent = '🖨️ Export PDF'; btn.disabled = false; }
    }, 400);
}

function printSection(sectionId, title) {
    const section = document.getElementById(sectionId);
    if (!section) return;

    // Capture canvas charts as images
    const canvases = section.querySelectorAll('canvas');
    const chartImages = {};
    canvases.forEach(c => { chartImages[c.id] = c.toDataURL('image/png'); });

    let sectionHTML = section.innerHTML;
    canvases.forEach(c => {
        sectionHTML = sectionHTML.replace(
            new RegExp(`<canvas id="${c.id}"[^>]*></canvas>`, 'g'),
            `<img src="${chartImages[c.id]}" style="max-width:100%;height:220px;object-fit:contain">`
        );
    });

    const generated = <?= json_encode($generated_at) ?>;
    const printHTML = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${title} — Minmi Restaurent</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0 }
        body { font-family:'Segoe UI',Arial,sans-serif; font-size:13px; color:#111; background:#fff; padding:32px }
        .pdf-top { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #e8622a; padding-bottom:14px; margin-bottom:20px }
        .pdf-brand { font-size:1.3rem; font-weight:700; color:#e8622a }
        .pdf-sub { font-size:.8rem; color:#888; margin-top:4px }
        .pdf-meta { text-align:right; font-size:.75rem; color:#888 }
        .pdf-section-title { font-size:1rem; font-weight:700; color:#e8622a; margin-bottom:16px }
        .calc-box { background:#fff8f5; border-left:3px solid #e8622a; border-radius:0 6px 6px 0; padding:12px 14px; margin:12px 0 16px }
        .calc-title { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#e8622a; margin-bottom:8px }
        .calc-steps { display:flex; flex-direction:column; gap:7px }
        .calc-step { display:flex; align-items:flex-start; gap:10px; font-size:.8rem; color:#444; line-height:1.5 }
        .calc-num { background:#e8622a; color:#fff; border-radius:50%; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:700; flex-shrink:0; margin-top:1px }
        code { background:#f0f0f0; padding:1px 5px; border-radius:3px; font-size:.76rem; color:#c0392b }
        table { width:100%; border-collapse:collapse; margin-top:12px }
        th { background:#f5f5f5; padding:8px 10px; font-size:.73rem; text-align:left; color:#333; border-bottom:2px solid #ddd }
        td { padding:8px 10px; font-size:.78rem; border-bottom:1px solid #eee; color:#333 }
        .badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:.65rem; font-weight:600; border:1px solid #ccc; background:#f5f5f5; color:#333 }
        .badge-red { background:#fef2f2; color:#dc2626; border-color:#fca5a5 }
        .badge-green { background:#f0fdf4; color:#16a34a; border-color:#86efac }
        .no-print, button { display:none !important }
        .pdf-footer { margin-top:32px; padding-top:12px; border-top:1px solid #ddd; display:flex; justify-content:space-between; font-size:.72rem; color:#aaa }
    </style>
</head>
<body>
    <div class="pdf-top">
        <div>
            <div class="pdf-brand">🔥 Minmi Restaurent</div>
            <div class="pdf-sub">${title}</div>
        </div>
        <div class="pdf-meta">Generated: ${generated}<br>All time data</div>
    </div>
    <div class="pdf-section-title">${title}</div>
    ${sectionHTML}
    <div class="pdf-footer">
        <span>🔥 Minmi Restaurent — Confidential</span>
        <span>Generated: ${generated}</span>
    </div>
</body>
</html>`;

    const iframe = document.getElementById('printFrame');
    iframe.style.display = 'none';
    iframe.srcdoc = printHTML;
    iframe.onload = function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>