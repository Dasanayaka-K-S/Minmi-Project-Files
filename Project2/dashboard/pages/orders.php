<?php
// ============================================================
//  ORDERS — Minmi Restaurent Admin
//  Place in: dashboard/pages/orders.php
//  Actions: View, Change Status, Delete
// ============================================================

require_once __DIR__ . '/../includes/db.php';

// ════════════════════════════════════════
//  HANDLE POST ACTIONS
// ════════════════════════════════════════
$action   = $_POST['action']  ?? '';
$item_id  = $_POST['item_id'] ?? '';
$msg      = '';
$msg_type = 'success';

// ── STATUS CHANGE ──────────────────────────────────────────
if ($action === 'status' && $item_id) {
    $new_status = $_POST['new_status'] ?? '';
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")
        ->execute([$new_status, $item_id]);
    $msg = '✅ Order "' . htmlspecialchars($item_id) . '" marked as ' . htmlspecialchars($new_status) . '.';
}

// ── DELETE ─────────────────────────────────────────────────
if ($action === 'delete' && $item_id) {
    $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$item_id]);
    $msg      = '🗑️ Order "' . htmlspecialchars($item_id) . '" deleted.';
    $msg_type = 'danger';
}

// ════════════════════════════════════════
//  FETCH DATA
// ════════════════════════════════════════
$orders = $pdo->query("SELECT * FROM orders ORDER BY date DESC, id DESC")->fetchAll();

// ── Stats ──────────────────────────────────────────────────
$cnt_pending   = count(array_filter($orders, fn($o) => $o['status'] === 'Pending'));
$cnt_confirmed = count(array_filter($orders, fn($o) => $o['status'] === 'Confirmed'));
$cnt_preparing = count(array_filter($orders, fn($o) => $o['status'] === 'Preparing'));
$cnt_delivered = count(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));
$cnt_cancelled = count(array_filter($orders, fn($o) => $o['status'] === 'Cancelled'));
$total_revenue = array_sum(array_column(
    array_filter($orders, fn($o) => $o['status'] === 'Delivered'), 'total'
));

// ── Daily orders chart (last 7 days) ──────────────────────
$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('M d',   strtotime("-{$i} days"));
    $daily[$label] = 0;
    foreach ($orders as $o) {
        if (substr($o['date'], 0, 10) === $date) $daily[$label]++;
    }
}
$daily_labels = json_encode(array_keys($daily));
$daily_vals   = json_encode(array_values($daily));

$orders_json = json_encode($orders);
$page_title  = 'Orders';

$page_scripts = "buildBarChart('dailyOrdersChart', {$daily_labels}, {$daily_vals}, '#4e9cf7');";

// ── Badge helper ───────────────────────────────────────────
function orderBadge(string $status): string {
    $map = [
        'Pending'   => 'badge-yellow',
        'Confirmed' => 'badge-blue',
        'Preparing' => 'badge-orange',
        'Delivered' => 'badge-green',
        'Cancelled' => 'badge-red',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'badge-gray') . '">' . htmlspecialchars($status) . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ═══════════════════════════════════════
     FLASH MESSAGE
     ═══════════════════════════════════════ -->
<?php if ($msg): ?>
<div class="flash-msg flash-<?= $msg_type ?>" id="flashMsg">
    <?= $msg ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:12px">✕</button>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     PAGE HEADER
     ═══════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Orders</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Track and manage incoming customer orders.</p>
    </div>
</div>

<!-- ═══════════════════════════════════════
     KPI STATS
     ═══════════════════════════════════════ -->
<div class="stats-grid">
    <div class="stat-card yellow">
        <div class="stat-icon">⏳</div>
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $cnt_pending ?></div>
        <div class="stat-sub">Awaiting confirmation</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">✔️</div>
        <div class="stat-label">Confirmed</div>
        <div class="stat-value"><?= $cnt_confirmed ?></div>
        <div class="stat-sub">Ready to prepare</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">🍳</div>
        <div class="stat-label">Preparing</div>
        <div class="stat-value"><?= $cnt_preparing ?></div>
        <div class="stat-sub">In the kitchen</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Delivered</div>
        <div class="stat-value"><?= $cnt_delivered ?></div>
        <div class="stat-sub">$<?= number_format($total_revenue, 0) ?> revenue</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">❌</div>
        <div class="stat-label">Cancelled</div>
        <div class="stat-value"><?= $cnt_cancelled ?></div>
        <div class="stat-sub">Total cancelled</div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     DAILY CHART
     ═══════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Daily Orders — Last 7 Days</div>
        <span class="badge badge-blue">Live data</span>
    </div>
    <div class="chart-box"><canvas id="dailyOrdersChart"></canvas></div>
</div>

<!-- ═══════════════════════════════════════
     ORDERS TABLE
     ═══════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <div class="card-title">All Orders</div>
        <span class="badge badge-gray" id="orderCount"><?= count($orders) ?> orders</span>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="text" id="searchOrders" class="search-input"
               placeholder="🔍  Search ID or customer…"
               oninput="filterOrders()" style="flex:1;min-width:200px">
        <select id="filterStatus" class="search-input" style="min-width:150px" onchange="filterOrders()">
            <option value="">All Statuses</option>
            <option>Pending</option>
            <option>Confirmed</option>
            <option>Preparing</option>
            <option>Delivered</option>
            <option>Cancelled</option>
        </select>
        <select id="filterPayment" class="search-input" style="min-width:130px" onchange="filterOrders()">
            <option value="">All Payments</option>
            <option>Card</option>
            <option>Cash</option>
        </select>
    </div>

    <?php if (empty($orders)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-3)">
        <div style="font-size:2.5rem;margin-bottom:10px">📦</div>
        <p>No orders yet. Orders placed on your website will appear here automatically.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table id="ordersTable">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o):
                // Handle items — plain text or JSON
                $items_display = $o['items'] ?? '';
                $decoded = json_decode($items_display, true);
                if (is_array($decoded)) {
                    $items_display = implode(', ', array_map(
                        fn($i) => is_array($i) ? ($i['name'] ?? '') : (string)$i,
                        $decoded
                    ));
                }
            ?>
            <tr data-id="<?= strtolower(htmlspecialchars($o['id'])) ?>"
                data-customer="<?= strtolower(htmlspecialchars($o['customer'])) ?>"
                data-status="<?= htmlspecialchars($o['status']) ?>"
                data-payment="<?= htmlspecialchars($o['payment']) ?>">
                <td><code style="color:var(--accent-l);font-size:.78rem"><?= htmlspecialchars($o['id']) ?></code></td>
                <td><strong><?= htmlspecialchars($o['customer']) ?></strong></td>
                <td style="color:var(--text-2);font-size:.8rem;max-width:180px;
                            overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($items_display) ?>">
                    <?= htmlspecialchars(mb_strimwidth($items_display, 0, 30, '…')) ?>
                </td>
                <td><strong>$<?= number_format($o['total'], 2) ?></strong></td>
                <td>
                    <?php if (strtolower($o['payment']) === 'card'): ?>
                        <span class="badge badge-blue">💳 Card</span>
                    <?php else: ?>
                        <span class="badge badge-gray">💵 Cash</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-3);font-size:.8rem"><?= htmlspecialchars($o['date']) ?></td>
                <td><?= orderBadge($o['status']) ?></td>
                <td>
                    <div style="display:flex;gap:4px;justify-content:center">
                        <!-- View -->
                        <button class="btn btn-ghost btn-sm btn-icon btn-view"
                                data-id="<?= htmlspecialchars($o['id']) ?>"
                                title="View Details">👁️</button>
                        <!-- Status -->
                        <button class="btn btn-ghost btn-sm btn-icon btn-status"
                                data-id="<?= htmlspecialchars($o['id']) ?>"
                                data-status="<?= htmlspecialchars($o['status']) ?>"
                                title="Change Status">🔄</button>
                        <!-- Delete -->
                        <button class="btn btn-danger btn-sm btn-icon btn-delete"
                                data-id="<?= htmlspecialchars($o['id']) ?>"
                                title="Delete">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<!-- ═══════════════════════════════════════
     VIEW ORDER MODAL
     ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👁️ Order Details</div>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body" id="viewContent"></div>
    </div>
</div>


<!-- ═══════════════════════════════════════
     STATUS CHANGE MODAL
     ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="statusModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title">🔄 Change Order Status</div>
            <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-2);font-size:.85rem;margin-bottom:16px">
                Order: <strong id="status_order_id"></strong>
            </p>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action"  value="status">
                <input type="hidden" name="item_id" id="status_id">
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px">
                    <?php foreach([
                        ['Pending',   '⏳', 'badge-yellow'],
                        ['Confirmed', '✔️', 'badge-blue'],
                        ['Preparing', '🍳', 'badge-orange'],
                        ['Delivered', '✅', 'badge-green'],
                        ['Cancelled', '❌', 'badge-red'],
                    ] as [$st, $ic, $bc]): ?>
                    <label class="status-option">
                        <input type="radio" name="new_status" value="<?= $st ?>">
                        <span class="status-icon"><?= $ic ?></span>
                        <span class="badge <?= $bc ?>"><?= $st ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">✔ Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════
     DELETE MODAL
     ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-body" style="text-align:center;padding:32px 28px 24px">
            <div style="font-size:3rem;margin-bottom:12px">🗑️</div>
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">Delete Order?</h3>
            <p id="deleteMsg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="item_id" id="delete_id">
                <div style="display:flex;gap:10px;justify-content:center">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>


<!-- ═══════════════════════════════════════
     STYLES
     ═══════════════════════════════════════ -->
<style>
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;
    border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:var(--green-dim);color:var(--green);border:1px solid var(--green)}
.flash-danger{background:var(--red-dim);color:var(--red);border:1px solid var(--red)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;
    display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);
    width:100%;max-width:560px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;
    padding:4px;border-radius:6px}
.modal-close:hover{color:var(--text)}
.modal-body{padding:18px 24px 24px}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;
    padding-top:16px;border-top:1px solid var(--border)}
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);
    border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;
    font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;
    justify-content:center;border-radius:8px}
.status-option{display:flex;align-items:center;gap:10px;padding:10px 14px;
    border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;
    transition:border-color .2s,background .2s}
.status-option:has(input:checked){border-color:var(--accent);background:rgba(232,98,42,.06)}
.status-option input{accent-color:var(--accent)}
.status-icon{font-size:1.1rem}
</style>


<!-- ═══════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════ -->
<script>
const ORDERS = <?= $orders_json ?>;

// ── Modals ────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-backdrop.open').forEach(bd => bd.classList.remove('open'));
});

// ── Button handler ────────────────────────────────────────
document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const id = btn.dataset.id;
    const o  = id ? ORDERS.find(x => x.id === id) : null;

    // VIEW
    if (btn.classList.contains('btn-view') && o) {
        let itemsDisplay = o.items || '';
        try {
            const parsed = JSON.parse(o.items);
            if (Array.isArray(parsed)) {
                itemsDisplay = parsed.map(i => typeof i === 'object' ? (i.name || '') : i).join(', ');
            }
        } catch(e) {}

        const statusColors = {
            Pending:'var(--yellow)', Confirmed:'var(--blue)',
            Preparing:'var(--accent)', Delivered:'var(--green)', Cancelled:'var(--red)'
        };
        document.getElementById('viewContent').innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <div>
                    <div style="font-family:'DM Serif Display',serif;font-size:1.3rem">${o.id}</div>
                    <div style="color:var(--text-3);font-size:.8rem;margin-top:2px">${o.date}</div>
                </div>
                <span style="padding:5px 14px;border-radius:20px;font-size:.78rem;font-weight:700;
                             background:rgba(255,255,255,.06);color:${statusColors[o.status]||'var(--text-2)'}">
                    ${o.status}
                </span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
                ${vRow('👤','Customer', o.customer)}
                ${vRow('💳','Payment',  o.payment)}
                ${vRow('💰','Total',    '$' + Number(o.total).toFixed(2))}
                ${vRow('📅','Date',     o.date)}
            </div>
            <div style="background:var(--bg-3);border-radius:var(--radius);padding:14px;margin-bottom:16px">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;
                            color:var(--text-3);margin-bottom:8px">🍽️ Items Ordered</div>
                <div style="font-size:.85rem;line-height:1.8">${itemsDisplay || '—'}</div>
            </div>
            <button class="btn btn-primary" style="width:100%"
                    onclick="closeModal('viewModal');
                             document.querySelector('.btn-status[data-id=\\'${o.id}\\']').click()">
                🔄 Change Status
            </button>`;
        openModal('viewModal');
    }

    // STATUS
    if (btn.classList.contains('btn-status') && o) {
        document.getElementById('status_id').value = o.id;
        document.getElementById('status_order_id').textContent = o.id + ' — ' + o.customer;
        const radio = document.querySelector(`input[name="new_status"][value="${o.status}"]`);
        if (radio) radio.checked = true;
        openModal('statusModal');
    }

    // DELETE
    if (btn.classList.contains('btn-delete')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteMsg').textContent =
            'This will permanently delete order "' + id + '". This cannot be undone.';
        openModal('deleteModal');
    }
});

function vRow(icon, label, value) {
    return `<div style="background:var(--bg-3);border-radius:var(--radius);padding:12px">
        <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;
                    color:var(--text-3);margin-bottom:4px">${icon} ${label}</div>
        <div style="font-size:.85rem;font-weight:600">${value}</div>
    </div>`;
}

// ── Filter ────────────────────────────────────────────────
function filterOrders() {
    const q   = document.getElementById('searchOrders').value.toLowerCase();
    const st  = document.getElementById('filterStatus').value;
    const pay = document.getElementById('filterPayment').value;
    let n = 0;
    document.querySelectorAll('#ordersTable tbody tr').forEach(row => {
        const show =
            (!q   || row.dataset.id.includes(q) || row.dataset.customer.includes(q)) &&
            (!st  || row.dataset.status  === st) &&
            (!pay || row.dataset.payment === pay);
        row.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.getElementById('orderCount').textContent = n + ' orders';
}

// Auto dismiss flash
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 4000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
