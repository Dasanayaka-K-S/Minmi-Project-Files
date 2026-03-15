<?php
// ============================================================
//  PROFILE — Minmi Restaurent Customer Website
//  Place in: customer/profile.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$customer_id = $_SESSION['customer_id'];
$msg = ''; $msg_type = 'success';

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

// ── Update profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$name || !$email) {
        $msg = '⚠️ Name and email are required.'; $msg_type = 'warning';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '⚠️ Invalid email address.'; $msg_type = 'warning';
    } else {
        // Check email not taken by another customer
        $check = $pdo->prepare("SELECT id FROM customers WHERE email=? AND id != ?");
        $check->execute([$email, $customer_id]);
        if ($check->fetch()) {
            $msg = '⚠️ This email is already in use by another account.'; $msg_type = 'warning';
        } else {
            $pdo->prepare("UPDATE customers SET name=?, email=?, phone=? WHERE id=?")
                ->execute([$name, $email, $phone, $customer_id]);
            $_SESSION['customer_name']  = $name;
            $_SESSION['customer_email'] = $email;
            $customer['name']  = $name;
            $customer['email'] = $email;
            $customer['phone'] = $phone;
            $msg = '✅ Profile updated successfully!';
        }
    }
}

// ── Change password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $current  = $_POST['current']  ?? '';
    $new_pass = $_POST['new_pass'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (!password_verify($current, $customer['password'])) {
        $msg = '❌ Current password is incorrect.'; $msg_type = 'danger';
    } elseif (strlen($new_pass) < 6) {
        $msg = '⚠️ New password must be at least 6 characters.'; $msg_type = 'warning';
    } elseif ($new_pass !== $confirm) {
        $msg = '⚠️ New passwords do not match.'; $msg_type = 'warning';
    } else {
        $pdo->prepare("UPDATE customers SET password=? WHERE id=?")->execute([password_hash($new_pass, PASSWORD_DEFAULT), $customer_id]);
        $msg = '✅ Password changed successfully!';
    }
}

// Stats
$order_count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_email=? AND status != 'Cancelled'");
$order_count->execute([$customer['email']]);
$order_count = $order_count->fetchColumn();

$total_spent = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_email=? AND status='Delivered'");
$total_spent->execute([$customer['email']]);
$total_spent = $total_spent->fetchColumn();

$res_count = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE email=? AND status NOT IN ('Cancelled')");
$res_count->execute([$customer['email']]);
$res_count = $res_count->fetchColumn();

$page_title = 'My Profile';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash flash-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>My Profile</h1>
    <p>Manage your account details and password.</p>
</div>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px">
    <?php foreach([
        ['📦', 'Total Orders',      $order_count . ' orders'],
        ['💰', 'Total Spent',       'Rs. ' . number_format($total_spent, 0)],
        ['📅', 'Reservations',      $res_count . ' bookings'],
    ] as [$icon, $label, $val]): ?>
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px;text-align:center">
        <div style="font-size:1.6rem;margin-bottom:6px"><?= $icon ?></div>
        <div style="font-size:.75rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px"><?= $label ?></div>
        <div style="font-weight:700;font-size:.95rem;color:var(--accent)"><?= $val ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

    <!-- EDIT PROFILE -->
    <div class="card" style="margin-bottom:0">
        <div class="card-title">👤 Personal Details</div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-input"
                       value="<?= htmlspecialchars($customer['name']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-input"
                       value="<?= htmlspecialchars($customer['email']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-input"
                       value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                       placeholder="+94 77 000 0000">
            </div>
            <div class="form-group">
                <label class="form-label">Member Since</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($customer['joined'] ?? 'N/A') ?>" readonly style="opacity:.6;cursor:not-allowed">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">💾 Save Changes</button>
        </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="card" style="margin-bottom:0">
        <div class="card-title">🔒 Change Password</div>
        <form method="POST">
            <input type="hidden" name="action" value="password">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current" class="form-input" placeholder="Your current password" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_pass" class="form-input" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm" class="form-input" placeholder="Repeat new password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">🔒 Update Password</button>
        </form>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
            <a href="logout.php" class="btn btn-ghost" style="width:100%;justify-content:center;color:#e84242;border-color:#e84242">
                🚪 Logout
            </a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
