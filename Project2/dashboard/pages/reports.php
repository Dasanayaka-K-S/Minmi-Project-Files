<?php
// ============================================================
//  REPORTS — Minmi Restaurent Admin
//  Place in: dashboard/pages/reports.php
// ============================================================

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

$page_scripts = <<<JS
buildDoughnutChart('orderStatusChart',{$status_labels},{$status_data},['#3ecf8e','#f5c842','#4e9cf7','#e84242']);
buildDoughnutChart('paymentChart',['Card','Cash','Other'],[{$card_orders},{$cash_orders},{$other_orders}],['#4e9cf7','#3ecf8e','#a855f7']);
buildBarChart('topCustChart',{$top_cust_names},{$top_cust_spent},'#f5c842');
JS;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ═══════════════════════════════════════
     PAGE HEADER
     ═══════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Reports</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Business performance summary and analytics.</p>
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

<!-- ═══════════════════════════════════════
  SECTION 1 — TOTAL REVENUE BREAKDOWN
     ═══════════════════════════════════════ -->
<div class="card report-section" id="sec-revenue">
    <div class="card-header">
        <div class="card-title">💰 Total Revenue Breakdown</div>
        <button class="btn btn-ghost btn-sm print-btn" onclick="printSection('sec-revenue','Total Revenue Breakdown')">🖨️ Print PDF</button>
    </div>
    <!-- How it's calculated -->
    <div class="calc-box">
        <div class="calc-title">How Total Revenue is Calculated</div>
        <div class="calc-steps">
            <div class="calc-step">
                <span class="calc-num">1</span>
                <span>Only <strong>Delivered</strong> orders are counted — Pending, Cancelled and Processing are excluded.</span>
            </div>
            <div class="calc-step">
                <span class="calc-num">2</span>
                <span>All delivered order totals are summed: <code><?= count($delivered_orders) ?> orders × avg $<?= number_format($avg_order,2) ?></code></span>
            </div>
            <div class="calc-step">
                <span class="calc-num">3</span>
                <span>Cancellation rate = <code>(<?= count($cancelled) ?> cancelled ÷ <?= count($orders) ?> total) × 100 = <?= $cancel_rate ?>%</code></span>
            </div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:16px">
        <?php foreach([
            ['💰','Total Revenue',     '$'.number_format($total_rev,0),    '#e8622a'],
            ['📊','Avg Order Value',   '$'.number_format($avg_order,2),    '#3ecf8e'],
            ['🏆','Highest Order',     '$'.number_format($top_order,2),    '#4e9cf7'],
            ['🚫','Cancellation Rate', $cancel_rate.'%',                   '#e84242'],
        ] as [$ic,$lb,$val,$col]): ?>
        <div class="metric-box">
            <div class="metric-icon"><?= $ic ?></div>
            <div class="metric-label"><?= $lb ?></div>
            <div class="metric-value" style="color:<?= $col ?>"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>


<!-- ═══════════════════════════════════════
  SECTION 2 — ORDER STATUS BREAKDOWN
     ═══════════════════════════════════════ -->
<div class="card report-section" id="sec-orders">
    <div class="card-header">
        <div class="card-title">📦 Order Status Breakdown</div>
        <button class="btn btn-ghost btn-sm print-btn" onclick="printSection('sec-orders','Order Status Breakdown')">🖨️ Print PDF</button>
    </div>
    <div class="calc-box">
        <div class="calc-title">How Order Statuses Work</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Every order in the system has one of 4 statuses: <strong>Delivered, Pending, Processing, Cancelled</strong>.</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Percentages = (status count ÷ <?= count($orders) ?> total orders) × 100</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>Revenue is only added when status is <strong>Delivered</strong>.</span></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
        <div class="chart-box"><canvas id="orderStatusChart"></canvas></div>
        <div style="display:flex;flex-direction:column;gap:8px;justify-content:center">
            <?php
            $st_colors = ['Delivered'=>'#3ecf8e','Pending'=>'#f5c842','Processing'=>'#4e9cf7','Cancelled'=>'#e84242'];
            $st_icons  = ['Delivered'=>'✅','Pending'=>'⏳','Processing'=>'🔄','Cancelled'=>'❌'];
            foreach ($status_counts as $st => $cnt):
                $pct = count($orders) > 0 ? round(($cnt / count($orders)) * 100, 1) : 0;
            ?>
            <div class="status-breakdown-row">
                <span class="sb-icon"><?= $st_icons[$st] ?? '•' ?></span>
                <span class="sb-label"><?= $st ?></span>
                <div class="sb-bar-wrap">
                    <div class="sb-bar" style="width:<?= $pct ?>%;background:<?= $st_colors[$st] ?? '#ccc' ?>"></div>
                </div>
                <span class="sb-count"><?= $cnt ?></span>
                <span class="sb-pct"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════
  SECTION 3 — TOP SELLING ITEMS
     ═══════════════════════════════════════ -->
<div class="card report-section" id="sec-sellers">
    <div class="card-header">
        <div class="card-title">🍽️ Top Selling Items</div>
        <button class="btn btn-ghost btn-sm print-btn" onclick="printSection('sec-sellers','Top Selling Items')">🖨️ Print PDF</button>
    </div>
    <div class="calc-box">
        <div class="calc-title">How Top Items are Calculated</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Each order's <strong>items</strong> field is parsed (supports plain text and JSON format).</span></div>
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
        <span class="rank-badge"><?= $rank++ ?></span>
        <span style="min-width:160px;font-size:.85rem;font-weight:500"><?= htmlspecialchars(mb_strimwidth($name,0,24,'…')) ?></span>
        <div style="flex:1;height:8px;background:var(--bg-3);border-radius:4px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:var(--accent);border-radius:4px;transition:width .6s"></div>
        </div>
        <span style="font-size:.8rem;color:var(--text-2);min-width:50px;text-align:right"><?= $count ?> orders</span>
    </div>
    <?php endforeach; endif; ?>
</div>


<!-- ═══════════════════════════════════════
  SECTION 4 — PAYMENT METHODS
     ═══════════════════════════════════════ -->
<div class="card report-section" id="sec-payment">
    <div class="card-header">
        <div class="card-title">💳 Payment Methods</div>
        <button class="btn btn-ghost btn-sm print-btn" onclick="printSection('sec-payment','Payment Methods')">🖨️ Print PDF</button>
    </div>
    <div class="calc-box">
        <div class="calc-title">How Payment Split is Calculated</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>Each order's <strong>payment</strong> column is checked (case-insensitive match).</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Card = <?= $card_orders ?>, Cash = <?= $cash_orders ?>, Other = <?= $other_orders ?> out of <?= count($orders) ?> total orders.</span></div>
            <div class="calc-step"><span class="calc-num">3</span><span>Percentage = (type count ÷ total orders) × 100</span></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
        <div class="chart-box"><canvas id="paymentChart"></canvas></div>
        <div style="display:flex;flex-direction:column;gap:12px;justify-content:center">
            <?php foreach([
                ['💳','Card',  $card_orders,  '#4e9cf7'],
                ['💵','Cash',  $cash_orders,  '#3ecf8e'],
                ['📱','Other', $other_orders, '#a855f7'],
            ] as [$ic,$lb,$cnt,$col]):
                $pct = count($orders) > 0 ? round(($cnt / count($orders)) * 100, 1) : 0;
            ?>
            <div class="status-breakdown-row">
                <span class="sb-icon"><?= $ic ?></span>
                <span class="sb-label"><?= $lb ?></span>
                <div class="sb-bar-wrap">
                    <div class="sb-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
                </div>
                <span class="sb-count"><?= $cnt ?></span>
                <span class="sb-pct"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════
  SECTION 5 — FINANCIAL SUMMARY
     ═══════════════════════════════════════ -->
<div class="card report-section" id="sec-financial">
    <div class="card-header">
        <div class="card-title">📋 Financial Summary</div>
        <button class="btn btn-ghost btn-sm print-btn" onclick="printSection('sec-financial','Financial Summary')">🖨️ Print PDF</button>
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
        ['✅ Delivered Orders',          count($delivered_orders),                   'var(--green)'],
        ['❌ Cancelled Orders',          count($cancelled),                           'var(--red)'],
        ['⏳ Pending Orders',            count($pending),                             'var(--yellow)'],
        ['🔄 Processing Orders',         count($processing),                          'var(--blue)'],
        ['💰 Total Revenue (Delivered)', '$'.number_format($total_rev,2),             'var(--accent-l)'],
        ['📊 Average Order Value',       '$'.number_format($avg_order,2),             ''],
        ['🏆 Highest Single Order',      '$'.number_format($top_order,2),             ''],
        ['🚫 Cancellation Rate',         $cancel_rate.'%',                            'var(--yellow)'],
        ['💳 Card Payments',             $card_orders.' orders',                      'var(--blue)'],
        ['💵 Cash Payments',             $cash_orders.' orders',                      'var(--green)'],
        ['👥 Total Customers',           count($customers),                           ''],
        ['⭐ VIP Customers',             $vip_count,                                  'var(--accent-l)'],
        ['🆕 New Customers',             $new_cust,                                   'var(--blue)'],
        ['💸 Total Customer Spend',      '$'.number_format($total_cust_revenue,2),    'var(--accent-l)'],
        ['🍽️ Active Menu Items',         $active_menu_count,                          ''],
        ['👨‍🍳 Active Staff',              $active_staff,                               ''],
        ['⚠️ Low Stock Items',           $low_stock_count,                            'var(--red)'],
    ];
    foreach ($metrics as [$label,$value,$color]):
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;
         padding:10px 0;border-bottom:1px solid var(--border);font-size:.84rem">
        <span style="color:var(--text-2)"><?= $label ?></span>
        <strong style="<?= $color ? 'color:'.$color : '' ?>"><?= $value ?></strong>
    </div>
    <?php endforeach; ?>
</div>


<!-- ═══════════════════════════════════════
  SECTION 6 — TOP CUSTOMERS
     ═══════════════════════════════════════ -->
<div class="card report-section" id="sec-customers">
    <div class="card-header">
        <div class="card-title">👥 Top 5 Customers by Spend</div>
        <button class="btn btn-ghost btn-sm print-btn" onclick="printSection('sec-customers','Top Customers by Spend')">🖨️ Print PDF</button>
    </div>
    <div class="calc-box">
        <div class="calc-title">How Top Customers are Ranked</div>
        <div class="calc-steps">
            <div class="calc-step"><span class="calc-num">1</span><span>All customers are sorted by <strong>total_spent</strong> column (descending).</span></div>
            <div class="calc-step"><span class="calc-num">2</span><span>Top 5 are selected and displayed with their spend amount.</span></div>
        </div>
    </div>
    <?php if (empty($top_customers)): ?>
    <div style="text-align:center;padding:24px;color:var(--text-3);font-size:.84rem">No customer data yet.</div>
    <?php else: ?>
    <div class="chart-box" style="margin-top:12px"><canvas id="topCustChart"></canvas></div>
    <div style="margin-top:16px">
        <?php $rank=1; foreach($top_customers as $c): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;
                    border-bottom:1px solid var(--border);font-size:.84rem">
            <span class="rank-badge"><?= $rank++ ?></span>
            <span style="flex:1;font-weight:600"><?= htmlspecialchars($c['name']) ?></span>
            <span class="badge badge-gray"><?= htmlspecialchars($c['status'] ?? '') ?></span>
            <strong style="color:var(--accent-l)">$<?= number_format($c['total_spent'],2) ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>


<!-- ═══════════════════════════════════════
  SECTION 7 — LOW STOCK ALERT
     ═══════════════════════════════════════ -->
<?php if ($low_stock_count > 0):
    $low_items = array_filter($inventory, fn($i) => $i['stock'] <= $i['min_stock']);
?>
<div class="card report-section" id="sec-stock">
    <div class="card-header">
        <div class="card-title">⚠️ Low Stock Alert</div>
        <button class="btn btn-ghost btn-sm print-btn" onclick="printSection('sec-stock','Low Stock Alert')">🖨️ Print PDF</button>
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
                <td><strong style="color:var(--red)"><?= $item['stock'] ?> <?= htmlspecialchars($item['unit'] ?? '') ?></strong></td>
                <td style="color:var(--text-3)"><?= $item['min_stock'] ?></td>
                <td><span class="badge badge-red">Low Stock</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; // end empty check ?>

<!-- ═══════════════════════════════════════
     PRINT IFRAME (hidden)
     ═══════════════════════════════════════ -->
<iframe id="printFrame" style="display:none"></iframe>

<!-- ═══════════════════════════════════════
     STYLES
     ═══════════════════════════════════════ -->
<style>
/* Calculation box — hidden on dashboard, only visible in printed PDF */
.calc-box{display:none}
.calc-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
    color:var(--accent);margin-bottom:10px}
.calc-steps{display:flex;flex-direction:column;gap:8px}
.calc-step{display:flex;align-items:flex-start;gap:10px;font-size:.82rem;color:var(--text-2);line-height:1.5}
.calc-num{background:var(--accent);color:#fff;border-radius:50%;width:20px;height:20px;
    display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;flex-shrink:0;margin-top:1px}
.calc-step code{background:var(--bg-2);padding:1px 6px;border-radius:4px;font-size:.78rem;color:var(--accent-l)}

/* Metric boxes */
.metric-box{background:var(--bg-3);border-radius:var(--radius);padding:16px;text-align:center}
.metric-icon{font-size:1.5rem;margin-bottom:6px}
.metric-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:4px}
.metric-value{font-size:1.2rem;font-weight:700}

/* Status breakdown rows */
.status-breakdown-row{display:flex;align-items:center;gap:8px;padding:6px 0}
.sb-icon{font-size:1rem;width:20px;text-align:center}
.sb-label{font-size:.82rem;font-weight:500;min-width:80px}
.sb-bar-wrap{flex:1;height:7px;background:var(--bg-3);border-radius:4px;overflow:hidden}
.sb-bar{height:100%;border-radius:4px;transition:width .5s}
.sb-count{font-size:.8rem;font-weight:700;min-width:24px;text-align:right}
.sb-pct{font-size:.75rem;color:var(--text-3);min-width:40px;text-align:right}

/* Rank badge */
.rank-badge{background:var(--accent);color:#fff;border-radius:50%;width:24px;height:24px;
    display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0}

/* Print button */
.print-btn{font-size:.75rem !important;padding:4px 10px !important}
</style>

<!-- ═══════════════════════════════════════
     JAVASCRIPT — Section Print
     ═══════════════════════════════════════ -->
<script>
function printSection(sectionId, title) {
    const section = document.getElementById(sectionId);
    if (!section) return;

    // Capture canvas charts as images
    const canvases = section.querySelectorAll('canvas');
    const chartImages = {};
    canvases.forEach(c => {
        chartImages[c.id] = c.toDataURL('image/png');
    });

    // Build the HTML content
    let sectionHTML = section.innerHTML;

    // Replace canvases with their image snapshots
    canvases.forEach(c => {
        const placeholder = `<canvas id="${c.id}"`;
        const replacement = `<img src="${chartImages[c.id]}" style="max-width:100%;height:220px;object-fit:contain"`;
        sectionHTML = sectionHTML.replace(new RegExp(`<canvas id="${c.id}"[^>]*></canvas>`,'g'), replacement + '>');
    });

    const printHTML = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${title} — Minmi Restaurent</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0 }
        body { font-family:'Segoe UI',Arial,sans-serif; font-size:13px;
               color:#111; background:#fff; padding:32px }

        /* PDF Header */
        .pdf-top { display:flex; justify-content:space-between; align-items:flex-start;
                   border-bottom:2px solid #e8622a; padding-bottom:14px; margin-bottom:20px }
        .pdf-brand { font-size:1.3rem; font-weight:700; color:#e8622a }
        .pdf-sub   { font-size:.8rem; color:#888; margin-top:4px }
        .pdf-meta  { text-align:right; font-size:.75rem; color:#888 }
        .pdf-section-title { font-size:1rem; font-weight:700; color:#e8622a;
                             margin-bottom:16px }

        /* Calc box */
        .calc-box  { background:#fff8f5; border-left:3px solid #e8622a;
                     border-radius:0 6px 6px 0; padding:12px 14px; margin:12px 0 16px }
        .calc-title{ font-size:.68rem; font-weight:700; text-transform:uppercase;
                     letter-spacing:.07em; color:#e8622a; margin-bottom:8px }
        .calc-steps{ display:flex; flex-direction:column; gap:7px }
        .calc-step { display:flex; align-items:flex-start; gap:10px;
                     font-size:.8rem; color:#444; line-height:1.5 }
        .calc-num  { background:#e8622a; color:#fff; border-radius:50%;
                     width:18px; height:18px; display:inline-flex;
                     align-items:center; justify-content:center;
                     font-size:.65rem; font-weight:700; flex-shrink:0; margin-top:1px }
        code { background:#f0f0f0; padding:1px 5px; border-radius:3px;
               font-size:.76rem; color:#c0392b }

        /* Metric boxes */
        .metric-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:14px }
        .metric-box  { border:1px solid #eee; border-radius:8px;
                       padding:14px 10px; text-align:center }
        .metric-icon { font-size:1.3rem; margin-bottom:5px }
        .metric-label{ font-size:.65rem; text-transform:uppercase;
                       letter-spacing:.06em; color:#888; margin-bottom:3px }
        .metric-value{ font-size:1.1rem; font-weight:700 }

        /* Status rows */
        .sb-row  { display:flex; align-items:center; gap:8px; padding:6px 0 }
        .sb-label{ font-size:.8rem; font-weight:500; min-width:80px }
        .sb-bar-wrap { flex:1; height:7px; background:#eee; border-radius:4px; overflow:hidden }
        .sb-bar  { height:100%; border-radius:4px }
        .sb-count{ font-size:.78rem; font-weight:700; min-width:24px; text-align:right }
        .sb-pct  { font-size:.72rem; color:#888; min-width:40px; text-align:right }

        /* Rank badge */
        .rank-badge { background:#e8622a; color:#fff; border-radius:50%;
                      width:22px; height:22px; display:inline-flex;
                      align-items:center; justify-content:center;
                      font-size:.68rem; font-weight:700 }

        /* Table */
        table { width:100%; border-collapse:collapse; margin-top:12px }
        th { background:#f5f5f5; padding:8px 10px; font-size:.73rem;
             text-align:left; color:#333; border-bottom:2px solid #ddd }
        td { padding:8px 10px; font-size:.78rem; border-bottom:1px solid #eee; color:#333 }

        /* Badge */
        .badge { display:inline-block; padding:2px 8px; border-radius:20px;
                 font-size:.65rem; font-weight:600; border:1px solid #ccc;
                 background:#f5f5f5; color:#333 }
        .badge-red    { background:#fef2f2; color:#dc2626; border-color:#fca5a5 }
        .badge-green  { background:#f0fdf4; color:#16a34a; border-color:#86efac }
        .badge-blue   { background:#eff6ff; color:#2563eb; border-color:#93c5fd }
        .badge-yellow { background:#fefce8; color:#ca8a04; border-color:#fde047 }

        /* Grid layouts */
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px }
        .grid-flex { display:flex; flex-direction:column; gap:8px; justify-content:center }

        /* Print button hidden */
        .print-btn, button { display:none !important }

        /* Footer */
        .pdf-footer { margin-top:32px; padding-top:12px; border-top:1px solid #ddd;
                      display:flex; justify-content:space-between;
                      font-size:.72rem; color:#aaa }
    </style>
</head>
<body>
    <div class="pdf-top">
        <div>
            <div class="pdf-brand">🔥 Minmi Restaurent</div>
            <div class="pdf-sub">${title}</div>
        </div>
        <div class="pdf-meta">
            Generated: <?= $generated_at ?><br>
            All time data
        </div>
    </div>
    <div class="pdf-section-title">${title}</div>
    ${sectionHTML}
    <div class="pdf-footer">
        <span>🔥 Minmi Restaurent — Confidential</span>
        <span>Generated: <?= $generated_at ?></span>
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
