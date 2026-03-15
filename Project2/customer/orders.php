<?php
// ============================================================
//  MY ORDERS — Minmi Restaurent Customer Website
//  Place in: customer/orders.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$customer_id = $_SESSION['customer_id'];
$msg = ''; $msg_type = 'success';

// ── Cancel order (only if Pending) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $order_id = intval($_POST['order_id']);
    $check = $pdo->prepare("SELECT * FROM orders WHERE id=? AND customer_email=?");
    $check->execute([$order_id, $_SESSION['customer_email']]);
    $order = $check->fetch();

    if ($order && $order['status'] === 'Pending') {
        $pdo->prepare("UPDATE orders SET status='Cancelled' WHERE id=?")->execute([$order_id]);

        // Update customer total_spent
        $pdo->prepare("UPDATE customers SET orders_count = GREATEST(orders_count-1,0), total_spent = GREATEST(total_spent - ?, 0) WHERE id=?")
            ->execute([$order['total'], $customer_id]);

        $msg = '✅ Order #' . $order_id . ' has been cancelled.';
        $msg_type = 'success';
    } else {
        $msg = '❌ This order cannot be cancelled (only Pending orders can be cancelled).';
        $msg_type = 'danger';
    }
}

$placed_id = $_GET['placed'] ?? '';

// Fetch customer's orders
$orders = $pdo->prepare("SELECT * FROM orders WHERE customer_email=? ORDER BY id DESC");
$orders->execute([$_SESSION['customer_email']]);
$orders = $orders->fetchAll();

function statusBadge(string $s): string {
    $map = ['Pending'=>'badge-yellow','Confirmed'=>'badge-blue','Processing'=>'badge-blue','Delivered'=>'badge-green','Cancelled'=>'badge-red'];
    return '<span class="badge ' . ($map[$s] ?? 'badge-gray') . '">' . htmlspecialchars($s) . '</span>';
}

$page_title = 'My Orders';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($placed_id): ?>
<div class="flash flash-success" id="placedFlash">
    🎉 Order #<?= htmlspecialchars($placed_id) ?> placed successfully! We'll start preparing it soon.
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;margin-left:8px">✕</button>
</div>
<?php endif; ?>

<?php if ($msg): ?>
<div class="flash flash-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <h1>My Orders</h1>
        <p>Track your order history and current status.</p>
    </div>
    <a href="menu.php" class="btn btn-primary">＋ New Order</a>
</div>

<?php if (empty($orders)): ?>
<div style="text-align:center;padding:60px;background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border)">
    <div style="font-size:3.5rem;margin-bottom:14px">📦</div>
    <h3 style="font-family:'DM Serif Display',serif;font-size:1.3rem;font-weight:400;margin-bottom:8px">No orders yet</h3>
    <p style="color:var(--text-3);margin-bottom:20px">Browse our menu and place your first order!</p>
    <a href="menu.php" class="btn btn-primary">🍽️ Browse Menu</a>
</div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:16px">
<?php foreach ($orders as $o):
    $items_display = $o['items'] ?? '';
    $decoded = json_decode($items_display, true);
    if (is_array($decoded)) {
        $items_display = implode(', ', array_map(fn($i) => (is_array($i) ? ($i['name'] ?? '') . (isset($i['qty']) ? ' x'.$i['qty'] : '') : (string)$i), $decoded));
    }
    $can_cancel = $o['status'] === 'Pending';

    $status_icons = ['Pending'=>'⏳','Confirmed'=>'✅','Processing'=>'🍳','Delivered'=>'🎉','Cancelled'=>'❌'];
    $status_icon  = $status_icons[$o['status']] ?? '🔄';

    // Progress steps
    $steps = ['Pending','Confirmed','Processing','Delivered'];
    $step_idx = array_search($o['status'], $steps);
?>
<div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.05)">
    <!-- Order Header -->
    <div style="padding:18px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-bottom:1px solid var(--border);background:var(--bg-3)">
        <div style="display:flex;align-items:center;gap:12px">
            <div style="font-size:1.5rem"><?= $status_icon ?></div>
            <div>
                <div style="font-weight:700;font-size:.95rem">Order #<?= htmlspecialchars($o['id']) ?></div>
                <div style="color:var(--text-3);font-size:.78rem"><?= htmlspecialchars($o['date']) ?></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            <?= statusBadge($o['status']) ?>
            <strong style="color:var(--accent)">Rs. <?= number_format($o['total'], 2) ?></strong>
        </div>
    </div>

    <div style="padding:18px 20px">
        <!-- Items -->
        <div style="margin-bottom:14px">
            <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:6px">🍽️ Items Ordered</div>
            <div style="font-size:.88rem;color:var(--text-2)"><?= htmlspecialchars($items_display ?: '—') ?></div>
        </div>

        <!-- Details row -->
        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:14px">
            <div style="font-size:.82rem">
                <span style="color:var(--text-3)">Payment: </span>
                <strong><?= htmlspecialchars($o['payment']) ?></strong>
            </div>
            <div style="font-size:.82rem">
                <span style="color:var(--text-3)">Total: </span>
                <strong style="color:var(--accent)">Rs. <?= number_format($o['total'], 2) ?></strong>
            </div>
        </div>

        <!-- Progress bar (only for non-cancelled) -->
        <?php if ($o['status'] !== 'Cancelled' && $step_idx !== false): ?>
        <div style="margin-bottom:14px">
            <div style="display:flex;align-items:center;gap:0">
                <?php foreach ($steps as $i => $step):
                    $done    = $i <= $step_idx;
                    $current = $i === $step_idx;
                ?>
                <div style="display:flex;flex-direction:column;align-items:center;flex:1">
                    <div style="width:28px;height:28px;border-radius:50%;border:2px solid <?= $done ? '#e8622a' : '#ddd' ?>;
                                background:<?= $done ? '#e8622a' : '#fff' ?>;
                                display:flex;align-items:center;justify-content:center;
                                font-size:.7rem;color:<?= $done ? '#fff' : '#ccc' ?>;font-weight:700;
                                <?= $current ? 'box-shadow:0 0 0 3px rgba(232,98,42,.2)' : '' ?>">
                        <?= $done ? '✓' : ($i+1) ?>
                    </div>
                    <div style="font-size:.65rem;color:<?= $done ? 'var(--accent)' : 'var(--text-3)' ?>;margin-top:4px;font-weight:<?= $current ? '700' : '400' ?>;text-align:center">
                        <?= $step ?>
                    </div>
                </div>
                <?php if ($i < count($steps)-1): ?>
                <div style="flex:1;height:2px;background:<?= $i < $step_idx ? '#e8622a' : '#eee' ?>;margin-bottom:20px"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cancel button -->
        <?php if ($can_cancel): ?>
        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel order #<?= $o['id'] ?>?')">
            <input type="hidden" name="action"   value="cancel">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">❌ Cancel Order</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<script>
const f = document.getElementById('placedFlash');
if (f) setTimeout(() => { f.style.transition='opacity .5s'; f.style.opacity='0'; }, 5000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
