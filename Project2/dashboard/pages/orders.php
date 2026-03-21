<?php
// ============================================================
//  ORDERS — Minmi Restaurent Admin
//  Place in: dashboard/pages/orders.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// ── Auto-add customer_email column if it doesn't exist ──
try {
    $pdo->query("SELECT customer_email FROM orders LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN customer_email VARCHAR(255) DEFAULT '' AFTER customer");
}

// ════════════════════════════════════════
//  HANDLE POST ACTIONS
//  ── PRG Pattern: always redirect after POST ──
//  This prevents duplicate emails when page auto-refreshes
// ════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $item_id = $_POST['item_id'] ?? '';
    $msg     = '';
    $msg_type = 'success';

    // ── ADD NEW ORDER ──
    if ($action === 'add') {
        $customer       = trim($_POST['customer']       ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $items          = trim($_POST['items']          ?? '');
        $total          = floatval($_POST['total']      ?? 0);
        $payment        = $_POST['payment']  ?? 'Cash';
        $status         = $_POST['status']   ?? 'Pending';
        $date           = date('Y-m-d');

        if ($customer && $items && $total > 0) {
            $items_arr  = array_map('trim', explode(',', $items));
            $items_json = json_encode(array_map(fn($i) => ['name' => $i], $items_arr));

            $pdo->prepare("INSERT INTO orders (customer, customer_email, items, total, status, date, payment)
                           VALUES (?,?,?,?,?,?,?)")
                ->execute([$customer, $customer_email, $items_json, $total, $status, $date, $payment]);

            $new_order_id = $pdo->lastInsertId();
            $msg = '✅ Order for "' . htmlspecialchars($customer) . '" added successfully.';

            if ($customer_email) {
                $items_display   = implode(', ', $items_arr);
                $formatted_total = 'Rs. ' . number_format($total, 2);

                $email_body = "Thank you for your order at Minmi Restaurent! We have received your order and it is currently <strong>{$status}</strong>.

<div style='background:#fff8f5;border-left:4px solid #e8622a;border-radius:0 8px 8px 0;padding:18px 20px;margin:20px 0'>
    <div style='font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#e8622a;margin-bottom:12px'>🧾 Order Details</div>
    <table width='100%' cellpadding='6' cellspacing='0' style='font-size:.9rem;color:#333'>
        <tr><td style='color:#888;width:40%'>Order ID</td><td><strong>#{$new_order_id}</strong></td></tr>
        <tr><td style='color:#888'>Items</td><td><strong>" . htmlspecialchars($items_display) . "</strong></td></tr>
        <tr><td style='color:#888'>Total</td><td><strong style='color:#e8622a;font-size:1rem'>{$formatted_total}</strong></td></tr>
        <tr><td style='color:#888'>Payment</td><td>" . htmlspecialchars($payment) . "</td></tr>
        <tr><td style='color:#888'>Date</td><td>" . date('l, d F Y') . "</td></tr>
        <tr><td style='color:#888'>Status</td><td><span style='color:#f5c842;font-weight:700'>{$status}</span></td></tr>
    </table>
</div>

Our team will start preparing your order shortly. We'll keep you updated on its progress.

If you have any questions, contact us at <a href='mailto:minmirestaurant@gmail.com' style='color:#e8622a'>minmirestaurant@gmail.com</a>.

Thank you for choosing Minmi Restaurent! 🔥";

                $mail_result = sendMail($customer_email, $customer,
                    '🧾 Order Confirmation #' . $new_order_id . ' — Minmi Restaurent', $email_body);

                if ($mail_result['success']) {
                    $msg .= ' 📧 Confirmation email sent to ' . htmlspecialchars($customer_email) . '.';
                } else {
                    $msg      .= ' ⚠️ Saved but email failed: ' . htmlspecialchars($mail_result['error'] ?? 'Unknown error');
                    $msg_type  = 'warning';
                }
            }
        } else {
            $msg      = '⚠️ Please fill in all required fields.';
            $msg_type = 'danger';
        }
    }

    // ── CHANGE STATUS ──
    if ($action === 'status' && $item_id) {
        $new_status = $_POST['new_status'] ?? '';
        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$new_status, $item_id]);
        $msg = '✅ Order #' . htmlspecialchars($item_id) . ' marked as ' . htmlspecialchars($new_status) . '.';

        $ord_row = $pdo->prepare("SELECT * FROM orders WHERE id=?");
        $ord_row->execute([$item_id]);
        $updated_order = $ord_row->fetch();

        if ($updated_order && !empty($updated_order['customer_email'])) {
            $items_display = $updated_order['items'] ?? '';
            $decoded = json_decode($items_display, true);
            if (is_array($decoded)) {
                $items_display = implode(', ', array_map(
                    fn($i) => is_array($i) ? ($i['name'] ?? '') : (string)$i, $decoded
                ));
            }

            $formatted_total = 'Rs. ' . number_format($updated_order['total'], 2);
            $status_colors   = ['Pending'=>'#f5c842','Confirmed'=>'#4e9cf7','Processing'=>'#4e9cf7','Delivered'=>'#3ecf8e','Cancelled'=>'#e84242'];
            $status_color    = $status_colors[$new_status] ?? '#888';

            $status_messages = [
                'Confirmed'  => 'Great news! Your order has been <strong style="color:#4e9cf7">confirmed</strong> by our team. We\'re getting ready to prepare it!',
                'Processing' => 'Your order is now <strong style="color:#4e9cf7">being prepared</strong> in our kitchen. It won\'t be long now! 🍳',
                'Delivered'  => 'Your order has been <strong style="color:#3ecf8e">delivered</strong>. We hope you enjoy your meal! Bon appétit! 🌟',
                'Cancelled'  => 'Unfortunately, your order has been <strong style="color:#e84242">cancelled</strong>. If this was unexpected, please contact us to reorder.',
                'Pending'    => 'Your order status has been updated to <strong>Pending</strong>. Our team will confirm it shortly.',
            ];
            $status_msg = $status_messages[$new_status] ?? "Your order status has been updated to <strong>{$new_status}</strong>.";

            $update_body = "{$status_msg}

<div style='background:#fff8f5;border-left:4px solid #e8622a;border-radius:0 8px 8px 0;padding:18px 20px;margin:20px 0'>
    <div style='font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#e8622a;margin-bottom:12px'>🧾 Your Order</div>
    <table width='100%' cellpadding='6' cellspacing='0' style='font-size:.9rem;color:#333'>
        <tr><td style='color:#888;width:40%'>Order ID</td><td><strong>#{$item_id}</strong></td></tr>
        <tr><td style='color:#888'>Items</td><td>" . htmlspecialchars($items_display) . "</td></tr>
        <tr><td style='color:#888'>Total</td><td><strong style='color:#e8622a'>{$formatted_total}</strong></td></tr>
        <tr><td style='color:#888'>Payment</td><td>" . htmlspecialchars($updated_order['payment']) . "</td></tr>
        <tr><td style='color:#888'>Status</td><td><span style='color:{$status_color};font-weight:700'>{$new_status}</span></td></tr>
    </table>
</div>

For any questions, contact us at <a href='mailto:minmirestaurant@gmail.com' style='color:#e8622a'>minmirestaurant@gmail.com</a>.";

            $subject_map = [
                'Confirmed'  => '✅ Order Confirmed — Minmi Restaurent',
                'Processing' => '🍳 Your Order is Being Prepared — Minmi Restaurent',
                'Delivered'  => '🌟 Order Delivered — Thank You! — Minmi Restaurent',
                'Cancelled'  => '❌ Order Cancelled — Minmi Restaurent',
                'Pending'    => '⏳ Order Update — Minmi Restaurent',
            ];
            $subject = ($subject_map[$new_status] ?? '🔄 Order Update — Minmi Restaurent') . ' #' . $item_id;

            $mail_result = sendMail($updated_order['customer_email'], $updated_order['customer'], $subject, $update_body);

            if ($mail_result['success']) {
                $msg .= ' 📧 Status email sent to ' . htmlspecialchars($updated_order['customer_email']) . '.';
            } else {
                $msg .= ' ⚠️ Status updated but email failed.';
            }
        }
    }

    // ── DELETE ──
    if ($action === 'delete' && $item_id) {
        $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$item_id]);
        $msg      = '🗑️ Order #' . htmlspecialchars($item_id) . ' deleted.';
        $msg_type = 'danger';
    }

    // ── PRG: Store message in session then redirect to GET ──
    // This is the key fix — after any POST action, we redirect to GET
    // So auto-refresh will never re-trigger the POST and resend emails
    $_SESSION['admin_flash']      = $msg;
    $_SESSION['admin_flash_type'] = $msg_type;
    header('Location: orders.php');
    exit;
}

// ── Read flash message from session (set by POST redirect above) ──
$msg      = $_SESSION['admin_flash']      ?? '';
$msg_type = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);

// ════════════════════════════════════════
//  FETCH DATA
// ════════════════════════════════════════
$orders = $pdo->query("SELECT * FROM orders ORDER BY date DESC, id DESC")->fetchAll();

$cnt_pending    = count(array_filter($orders, fn($o) => $o['status'] === 'Pending'));
$cnt_confirmed  = count(array_filter($orders, fn($o) => $o['status'] === 'Confirmed'));
$cnt_processing = count(array_filter($orders, fn($o) => $o['status'] === 'Processing'));
$cnt_delivered  = count(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));
$cnt_cancelled  = count(array_filter($orders, fn($o) => $o['status'] === 'Cancelled'));
$total_revenue  = array_sum(array_column(
    array_filter($orders, fn($o) => $o['status'] === 'Delivered'), 'total'
));

$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $d     = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('M d',   strtotime("-{$i} days"));
    $daily[$label] = 0;
    foreach ($orders as $o) {
        if (substr($o['date'], 0, 10) === $d) $daily[$label]++;
    }
}
$daily_labels = json_encode(array_keys($daily));
$daily_vals   = json_encode(array_values($daily));
$orders_json  = json_encode($orders);
$page_title   = 'Orders';
$page_scripts = "buildBarChart('dailyOrdersChart', {$daily_labels}, {$daily_vals}, '#4e9cf7');";

function orderBadge(string $status): string {
    $map = ['Pending'=>'badge-yellow','Confirmed'=>'badge-blue','Processing'=>'badge-blue','Preparing'=>'badge-orange','Delivered'=>'badge-green','Cancelled'=>'badge-red'];
    return '<span class="badge ' . ($map[$status] ?? 'badge-gray') . '">' . htmlspecialchars($status) . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-msg flash-<?= $msg_type ?>" id="flashMsg">
    <?= $msg ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:12px">✕</button>
</div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Orders</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Track and manage incoming customer orders.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">＋ Add Order</button>
</div>

<!-- KPI STATS -->
<div class="stats-grid">
    <div class="stat-card yellow">
        <div class="stat-icon">⏳</div><div class="stat-label">Pending</div>
        <div class="stat-value"><?= $cnt_pending ?></div><div class="stat-sub">Awaiting confirmation</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">✔️</div><div class="stat-label">Confirmed</div>
        <div class="stat-value"><?= $cnt_confirmed ?></div><div class="stat-sub">Ready to prepare</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">🍳</div><div class="stat-label">Processing</div>
        <div class="stat-value"><?= $cnt_processing ?></div><div class="stat-sub">In the kitchen</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div><div class="stat-label">Delivered</div>
        <div class="stat-value"><?= $cnt_delivered ?></div>
        <div class="stat-sub">Rs. <?= number_format($total_revenue, 0) ?> revenue</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">❌</div><div class="stat-label">Cancelled</div>
        <div class="stat-value"><?= $cnt_cancelled ?></div><div class="stat-sub">Total cancelled</div>
    </div>
</div>

<!-- DAILY CHART -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Daily Orders — Last 7 Days</div>
        <span class="badge badge-blue">Live data</span>
    </div>
    <div class="chart-box"><canvas id="dailyOrdersChart"></canvas></div>
</div>

<!-- ORDERS TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">All Orders</div>
        <span class="badge badge-gray" id="orderCount"><?= count($orders) ?> orders</span>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="text" id="searchOrders" class="search-input"
               placeholder="🔍  Search ID, customer or email…"
               oninput="filterOrders()" style="flex:1;min-width:200px">
        <select id="filterStatus" class="search-input" style="min-width:150px" onchange="filterOrders()">
            <option value="">All Statuses</option>
            <option>Pending</option><option>Confirmed</option><option>Processing</option>
            <option>Delivered</option><option>Cancelled</option>
        </select>
        <select id="filterPayment" class="search-input" style="min-width:130px" onchange="filterOrders()">
            <option value="">All Payments</option>
            <option>Card</option><option>Cash</option><option>Other</option>
        </select>
    </div>

    <?php if (empty($orders)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-3)">
        <div style="font-size:2.5rem;margin-bottom:10px">📦</div>
        <p>No orders yet. Click "＋ Add Order" to add one.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table id="ordersTable">
            <thead>
                <tr>
                    <th>#</th><th>Customer</th><th>Items</th>
                    <th>Total</th><th>Payment</th><th>Date</th>
                    <th>Status</th><th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o):
                $items_display = $o['items'] ?? '';
                $decoded = json_decode($items_display, true);
                if (is_array($decoded)) {
                    $items_display = implode(', ', array_map(
                        fn($i) => is_array($i) ? ($i['name'] ?? '') : (string)$i, $decoded
                    ));
                }
                $has_email = !empty($o['customer_email']);
            ?>
            <tr data-id="<?= strtolower(htmlspecialchars($o['id'])) ?>"
                data-customer="<?= strtolower(htmlspecialchars($o['customer'])) ?>"
                data-email="<?= strtolower(htmlspecialchars($o['customer_email'] ?? '')) ?>"
                data-status="<?= htmlspecialchars($o['status']) ?>"
                data-payment="<?= htmlspecialchars($o['payment']) ?>">
                <td><code style="color:var(--accent-l);font-size:.78rem">#<?= htmlspecialchars($o['id']) ?></code></td>
                <td>
                    <strong><?= htmlspecialchars($o['customer']) ?></strong>
                    <?php if ($has_email): ?>
                    <div style="color:var(--text-3);font-size:.74rem;margin-top:2px">📧 <?= htmlspecialchars($o['customer_email']) ?></div>
                    <?php else: ?>
                    <div style="color:var(--text-3);font-size:.72rem;margin-top:2px;font-style:italic;opacity:.6">no email</div>
                    <?php endif; ?>
                </td>
                <td style="color:var(--text-2);font-size:.8rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($items_display) ?>">
                    <?= htmlspecialchars(mb_strimwidth($items_display, 0, 30, '…')) ?>
                </td>
                <td><strong>Rs. <?= number_format($o['total'], 2) ?></strong></td>
                <td>
                    <?php
                    $pay = strtolower($o['payment']);
                    if ($pay === 'card')     echo '<span class="badge badge-blue">💳 Card</span>';
                    elseif ($pay === 'cash') echo '<span class="badge badge-gray">💵 Cash</span>';
                    else echo '<span class="badge badge-gray">📱 ' . htmlspecialchars($o['payment']) . '</span>';
                    ?>
                </td>
                <td style="color:var(--text-3);font-size:.8rem"><?= htmlspecialchars($o['date']) ?></td>
                <td><?= orderBadge($o['status']) ?></td>
                <td>
                    <div style="display:flex;gap:4px;justify-content:center">
                        <button class="btn btn-ghost btn-sm btn-icon btn-view"
                                data-id="<?= htmlspecialchars($o['id']) ?>" title="View Details">👁️</button>
                        <button class="btn btn-ghost btn-sm btn-icon btn-status"
                                data-id="<?= htmlspecialchars($o['id']) ?>"
                                data-status="<?= htmlspecialchars($o['status']) ?>" title="Change Status">🔄</button>
                        <button class="btn btn-danger btn-sm btn-icon btn-delete"
                                data-id="<?= htmlspecialchars($o['id']) ?>" title="Delete">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ══ ADD ORDER MODAL ══ -->
<div class="modal-backdrop" id="addModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <div class="modal-title">＋ Add New Order</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Customer Name <span style="color:#e84242">*</span></label>
                        <input type="text" name="customer" class="form-input" placeholder="e.g. John Silva" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Customer Email <span style="color:#3ecf8e;font-size:.68rem;font-weight:400;text-transform:none"> — updates will be sent</span></label>
                        <input type="email" name="customer_email" class="form-input" placeholder="customer@email.com">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Items Ordered <span style="color:#e84242">*</span></label>
                        <input type="text" name="items" class="form-input" placeholder="e.g. Grilled Chicken, Fried Rice" required>
                        <div style="font-size:.75rem;color:var(--text-3);margin-top:4px">Separate multiple items with commas</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total (Rs.) <span style="color:#e84242">*</span></label>
                        <input type="number" name="total" class="form-input" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select name="payment" class="form-input">
                            <option value="Cash">💵 Cash</option>
                            <option value="Card">💳 Card</option>
                            <option value="Other">📱 Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input">
                            <option value="Pending">⏳ Pending</option>
                            <option value="Confirmed">✔️ Confirmed</option>
                            <option value="Processing">🍳 Processing</option>
                            <option value="Delivered">✅ Delivered</option>
                            <option value="Cancelled">❌ Cancelled</option>
                        </select>
                    </div>
                </div>
                <div style="background:rgba(62,207,142,.07);border:1px solid rgba(62,207,142,.25);border-radius:var(--radius);padding:10px 14px;margin-top:4px;font-size:.78rem;color:#3ecf8e">
                    📧 An order confirmation email will automatically be sent to the customer if an email is provided.
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">＋ Add Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ VIEW ORDER MODAL ══ -->
<div class="modal-backdrop" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👁️ Order Details</div>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body" id="viewContent"></div>
    </div>
</div>

<!-- ══ STATUS MODAL ══ -->
<div class="modal-backdrop" id="statusModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title">🔄 Change Order Status</div>
            <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-2);font-size:.85rem;margin-bottom:16px">Order: <strong id="status_order_id"></strong></p>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action"  value="status">
                <input type="hidden" name="item_id" id="status_id">
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">
                    <?php foreach([
                        ['Pending',    '⏳', 'badge-yellow'],
                        ['Confirmed',  '✔️', 'badge-blue'],
                        ['Processing', '🍳', 'badge-blue'],
                        ['Delivered',  '✅', 'badge-green'],
                        ['Cancelled',  '❌', 'badge-red'],
                    ] as [$st, $ic, $bc]): ?>
                    <label class="status-option">
                        <input type="radio" name="new_status" value="<?= $st ?>">
                        <span class="status-icon"><?= $ic ?></span>
                        <span class="badge <?= $bc ?>"><?= $st ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="background:rgba(62,207,142,.07);border:1px solid rgba(62,207,142,.25);border-radius:var(--radius);padding:10px 14px;margin-bottom:16px;font-size:.77rem;color:#3ecf8e">
                    📧 A status update email will automatically be sent to the customer if they provided an email.
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">✔ Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ DELETE MODAL ══ -->
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

<style>
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:rgba(62,207,142,.1);color:#3ecf8e;border:1px solid #3ecf8e}
.flash-danger{background:rgba(232,66,66,.1);color:#e84242;border:1px solid #e84242}
.flash-warning{background:rgba(245,200,66,.1);color:#f5c842;border:1px solid #f5c842}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;padding:4px;border-radius:6px}
.modal-close:hover{color:var(--text)}
.modal-body{padding:18px 24px 24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-3)}
.form-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:9px 13px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;width:100%;box-sizing:border-box}
.form-input:focus{border-color:var(--accent)}
select.form-input{cursor:pointer}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)}
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
.status-option{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:border-color .2s,background .2s}
.status-option:has(input:checked){border-color:var(--accent);background:rgba(232,98,42,.06)}
.status-option input{accent-color:var(--accent)}
.status-icon{font-size:1.1rem}
</style>

<script>
const ORDERS = <?= $orders_json ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-backdrop.open').forEach(bd => bd.classList.remove('open'));
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const id = btn.dataset.id;
    const o  = id ? ORDERS.find(x => String(x.id) === String(id)) : null;

    if (btn.classList.contains('btn-view') && o) {
        let itemsDisplay = o.items || '';
        try {
            const parsed = JSON.parse(o.items);
            if (Array.isArray(parsed))
                itemsDisplay = parsed.map(i => typeof i === 'object' ? (i.name || '') : i).join(', ');
        } catch(e) {}

        const statusColors = {Pending:'#f5c842',Confirmed:'#4e9cf7',Processing:'#4e9cf7',Delivered:'#3ecf8e',Cancelled:'#e84242'};
        const emailRow = o.customer_email
            ? vRow('📧','Email', o.customer_email)
            : vRow('📧','Email','<span style="color:var(--text-3);font-style:italic">not provided</span>');

        document.getElementById('viewContent').innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <div>
                    <div style="font-family:'DM Serif Display',serif;font-size:1.3rem">Order #${o.id}</div>
                    <div style="color:var(--text-3);font-size:.8rem;margin-top:2px">${o.date}</div>
                </div>
                <span style="padding:5px 14px;border-radius:20px;font-size:.78rem;font-weight:700;background:rgba(255,255,255,.06);color:${statusColors[o.status]||'#aaa'}">${o.status}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
                ${vRow('👤','Customer', o.customer)}
                ${vRow('💳','Payment',  o.payment)}
                ${vRow('💰','Total', 'Rs. ' + Number(o.total).toLocaleString('en-LK',{minimumFractionDigits:2}))}
                ${emailRow}
            </div>
            <div style="background:var(--bg-3);border-radius:var(--radius);padding:14px;margin-bottom:16px">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:8px">🍽️ Items Ordered</div>
                <div style="font-size:.85rem;line-height:1.8">${itemsDisplay || '—'}</div>
            </div>
            <button class="btn btn-primary" style="width:100%"
                    onclick="closeModal('viewModal');setTimeout(()=>document.querySelector('.btn-status[data-id=\\'${o.id}\\']').click(),100)">
                🔄 Change Status
            </button>`;
        openModal('viewModal');
    }

    if (btn.classList.contains('btn-status') && o) {
        document.getElementById('status_id').value = o.id;
        document.getElementById('status_order_id').textContent = '#' + o.id + ' — ' + o.customer;
        const radio = document.querySelector(`input[name="new_status"][value="${o.status}"]`);
        if (radio) radio.checked = true;
        openModal('statusModal');
    }

    if (btn.classList.contains('btn-delete') && id) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteMsg').textContent = 'This will permanently delete order #' + id + '. This cannot be undone.';
        openModal('deleteModal');
    }
});

function vRow(icon, label, value) {
    return `<div style="background:var(--bg-3);border-radius:var(--radius);padding:12px">
        <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:4px">${icon} ${label}</div>
        <div style="font-size:.85rem;font-weight:600">${value}</div>
    </div>`;
}

function filterOrders() {
    const q   = document.getElementById('searchOrders').value.toLowerCase();
    const st  = document.getElementById('filterStatus').value;
    const pay = document.getElementById('filterPayment').value;
    let n = 0;
    document.querySelectorAll('#ordersTable tbody tr').forEach(row => {
        const show =
            (!q   || row.dataset.id.includes(q) || row.dataset.customer.includes(q) || row.dataset.email.includes(q)) &&
            (!st  || row.dataset.status  === st) &&
            (!pay || row.dataset.payment === pay);
        row.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.getElementById('orderCount').textContent = n + ' orders';
}

const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => { flash.style.transition = 'opacity .5s'; flash.style.opacity = '0'; }, 5000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>