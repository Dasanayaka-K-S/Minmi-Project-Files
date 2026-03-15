<?php
// ============================================================
//  CART & PLACE ORDER — Minmi Restaurent Customer Website
//  Place in: customer/cart.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// ── Image base URL ──
$_doc_root    = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']), '/');
$_script_file = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$_web_path    = str_replace($_doc_root, '', $_script_file);
$_project_dir = substr($_web_path, 0, strpos($_web_path, '/customer/'));
$img_base_url = $_project_dir . '/dashboard/';

$msg = ''; $msg_type = 'success';

// ── Remove one from cart ──
if (isset($_GET['remove_one'])) {
    $rid = trim($_GET['remove_one']);
    if (isset($_SESSION['cart'][$rid])) {
        $_SESSION['cart'][$rid]['qty']--;
        if ($_SESSION['cart'][$rid]['qty'] <= 0) unset($_SESSION['cart'][$rid]);
    }
    session_write_close();
    $back = (isset($_GET['from']) && $_GET['from'] === 'menu') ? 'menu.php?category=' . urlencode($_GET['category'] ?? '') : 'cart.php';
    header('Location: ' . $back); exit;
}

// ── Remove whole item ──
if (isset($_GET['remove'])) {
    $rid = trim($_GET['remove']);
    unset($_SESSION['cart'][$rid]);
    session_write_close();
    header('Location: cart.php'); exit;
}

// ── Add from cart page ──
if (isset($_GET['add'])) {
    $aid = trim($_GET['add']);
    if (isset($_SESSION['cart'][$aid])) { $_SESSION['cart'][$aid]['qty']++; }
    session_write_close();
    header('Location: cart.php'); exit;
}

// ── Clear cart ──
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header('Location: cart.php'); exit;
}

// ── Place Order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place_order') {
    $order_type = $_POST['order_type'] ?? 'online';
    $payment    = $_POST['payment']    ?? 'Cash';
    $table_no   = trim($_POST['table_no'] ?? '');
    $notes      = trim($_POST['notes']  ?? '');

    if (empty($_SESSION['cart'])) {
        $msg = '⚠️ Your cart is empty.'; $msg_type = 'warning';
    } else {
        $cart        = $_SESSION['cart'];
        $total       = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
        $items_arr   = array_map(fn($i) => ['name' => $i['name'], 'qty' => $i['qty'], 'price' => $i['price']], array_values($cart));
        $items_json  = json_encode($items_arr);
        $customer    = $_SESSION['customer_name'];
        $cust_email  = $_SESSION['customer_email'];
        $customer_id = $_SESSION['customer_id'];

        // Add table number to notes if dine-in
        $full_notes = $notes;
        if ($order_type === 'dine-in' && $table_no) $full_notes = "Table: {$table_no}" . ($notes ? " | {$notes}" : '');

        $pdo->prepare("INSERT INTO orders (customer, customer_email, items, total, status, date, payment, created_at)
                       VALUES (?, ?, ?, ?, 'Pending', CURDATE(), ?, NOW())")
            ->execute([$customer, $cust_email, $items_json, $total, $payment]);

        $order_id = $pdo->lastInsertId();

        // Update customer orders_count and total_spent
        $pdo->prepare("UPDATE customers SET orders_count = orders_count + 1, total_spent = total_spent + ? WHERE id=?")
            ->execute([$total, $customer_id]);

        // Send confirmation email
        $mailer_path = __DIR__ . '/../dashboard/includes/mailer.php';
        if (file_exists($mailer_path)) {
            require_once $mailer_path;
            $items_display = implode(', ', array_map(fn($i) => $i['name'] . ' x' . $i['qty'], $items_arr));
            $type_label = $order_type === 'dine-in' ? "Dine-In (Table: {$table_no})" : 'Online Order';
            $body = "Thank you for your order! It has been received and is currently <strong>Pending</strong>.

<div style='background:#fff8f5;border-left:4px solid #e8622a;border-radius:0 8px 8px 0;padding:18px 20px;margin:20px 0'>
    <div style='font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#e8622a;margin-bottom:12px'>🧾 Order Summary</div>
    <table width='100%' cellpadding='6' cellspacing='0' style='font-size:.9rem;color:#333'>
        <tr><td style='color:#888;width:40%'>Order ID</td><td><strong>#{$order_id}</strong></td></tr>
        <tr><td style='color:#888'>Items</td><td>" . htmlspecialchars($items_display) . "</td></tr>
        <tr><td style='color:#888'>Total</td><td><strong style='color:#e8622a'>Rs. " . number_format($total, 2) . "</strong></td></tr>
        <tr><td style='color:#888'>Type</td><td>{$type_label}</td></tr>
        <tr><td style='color:#888'>Payment</td><td>{$payment}</td></tr>
    </table>
</div>
We'll send you updates as your order progresses. Thank you for choosing Minmi Restaurent! 🔥";
            sendMail($cust_email, $customer, "🧾 Order Confirmation #{$order_id} — Minmi Restaurent", $body);
        }

        $_SESSION['cart'] = [];
        header("Location: orders.php?placed={$order_id}"); exit;
    }
}

$cart  = $_SESSION['cart'] ?? [];
$total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));

$page_title = 'Your Cart';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>🛒 Your Cart</h1>
    <p>Review your items and place your order.</p>
</div>

<?php if ($msg): ?>
<div class="flash flash-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<?php if (empty($cart)): ?>
<div style="text-align:center;padding:60px;background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border)">
    <div style="font-size:3.5rem;margin-bottom:14px">🛒</div>
    <h3 style="font-family:'DM Serif Display',serif;font-size:1.3rem;font-weight:400;margin-bottom:8px">Your cart is empty</h3>
    <p style="color:var(--text-3);margin-bottom:20px">Browse our menu and add some delicious items!</p>
    <a href="menu.php" class="btn btn-primary">🍽️ Browse Menu</a>
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">

    <!-- CART ITEMS -->
    <div class="card" style="margin-bottom:0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
            <div class="card-title" style="margin-bottom:0">Items (<?= count($cart) ?>)</div>
            <a href="cart.php?clear=1" style="color:#e84242;font-size:.82rem;text-decoration:none;font-weight:600">🗑️ Clear Cart</a>
        </div>

        <?php foreach ($cart as $id => $item): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border)">
            <?php if ($item['image']): ?>
            <img src="<?= $img_base_url . htmlspecialchars($item['image']) ?>"
                 style="width:60px;height:60px;border-radius:10px;object-fit:cover;flex-shrink:0">
            <?php else: ?>
            <div style="width:60px;height:60px;border-radius:10px;background:var(--bg-3);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0">🍽️</div>
            <?php endif; ?>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.92rem"><?= htmlspecialchars($item['name']) ?></div>
                <div style="color:var(--accent);font-size:.88rem;font-weight:700;margin-top:2px">Rs. <?= number_format($item['price'], 0) ?> each</div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <a href="cart.php?remove_one=<?= urlencode($id) ?>" class="btn btn-ghost btn-sm" style="width:30px;height:30px;padding:0;justify-content:center">−</a>
                <span style="font-weight:700;min-width:20px;text-align:center"><?= $item['qty'] ?></span>
                <a href="cart.php?add=<?= urlencode($id) ?>" class="btn btn-ghost btn-sm" style="width:30px;height:30px;padding:0;justify-content:center">＋</a>
            </div>
            <div style="font-weight:700;min-width:80px;text-align:right">Rs. <?= number_format($item['price'] * $item['qty'], 0) ?></div>
            <a href="cart.php?remove=<?= urlencode($id) ?>" style="color:#e84242;font-size:1rem;text-decoration:none;padding:4px">✕</a>
        </div>
        <?php endforeach; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:16px">
            <span style="font-size:.88rem;color:var(--text-3)">Subtotal</span>
            <strong style="font-size:1.1rem;color:var(--accent)">Rs. <?= number_format($total, 2) ?></strong>
        </div>
    </div>

    <!-- ORDER DETAILS -->
    <div class="card" style="margin-bottom:0;position:sticky;top:88px">
        <div class="card-title">Place Order</div>
        <form method="POST">
            <input type="hidden" name="action" value="place_order">

            <div class="form-group">
                <label class="form-label">Order Type</label>
                <select name="order_type" class="form-input" id="orderType" onchange="toggleTable(this.value)">
                    <option value="online">📦 Online / Takeaway</option>
                    <option value="dine-in">🪑 Dine-In</option>
                </select>
            </div>

            <div class="form-group" id="tableGroup" style="display:none">
                <label class="form-label">Table Number</label>
                <input type="text" name="table_no" class="form-input" placeholder="e.g. Table 5">
            </div>

            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select name="payment" class="form-input">
                    <option value="Cash">💵 Cash</option>
                    <option value="Card">💳 Card</option>
                    <option value="Other">📱 Other</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Special Notes</label>
                <textarea name="notes" class="form-input" rows="2" placeholder="Allergies, special requests…"></textarea>
            </div>

            <div style="background:var(--bg-3);border-radius:var(--radius);padding:14px;margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;font-size:.88rem;margin-bottom:6px">
                    <span style="color:var(--text-3)">Items</span>
                    <span><?= count($cart) ?> items</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem">
                    <span>Total</span>
                    <span style="color:var(--accent)">Rs. <?= number_format($total, 2) ?></span>
                </div>
            </div>

            <div style="font-size:.75rem;color:var(--text-3);margin-bottom:14px">
                📧 A confirmation email will be sent to <?= htmlspecialchars($_SESSION['customer_email']) ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
                ✅ Place Order — Rs. <?= number_format($total, 2) ?>
            </button>
        </form>
    </div>

</div>

<script>
function toggleTable(val) {
    document.getElementById('tableGroup').style.display = val === 'dine-in' ? 'flex' : 'none';
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>