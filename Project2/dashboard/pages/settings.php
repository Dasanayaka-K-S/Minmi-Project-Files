<?php
// ============================================================
//  SETTINGS — Minmi Restaurent Admin
//  Place in: dashboard/pages/settings.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

function loadSettings(PDO $pdo): array {
    return $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}

function saveSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$key, $value]);
}

$msg      = '';
$msg_type = 'success';
$section  = $_POST['section'] ?? '';

if ($section === 'profile') {
    foreach (['rest_name','rest_tagline','rest_cuisine','rest_capacity','rest_address',
              'rest_city','rest_state','rest_zip','rest_country','rest_phone',
              'rest_email','rest_website','rest_instagram','rest_facebook','rest_twitter','rest_desc'] as $f) {
        saveSetting($pdo, $f, trim($_POST[$f] ?? ''));
    }
    $msg = '✅ Restaurant profile saved!';
}

if ($section === 'hours') {
    foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $d) {
        saveSetting($pdo, "hours_{$d}", json_encode([
            'open'    => $_POST["open_{$d}"]  ?? '09:00',
            'close'   => $_POST["close_{$d}"] ?? '22:00',
            'is_open' => isset($_POST["day_open_{$d}"]),
        ]));
    }
    saveSetting($pdo, 'special_closures', $_POST['special_closures'] ?? '[]');
    $msg = '✅ Operating hours saved!';
}

if ($section === 'billing') {
    foreach (['tax_rate','service_charge','currency','currency_symbol',
              'receipt_header','receipt_footer','invoice_prefix'] as $f) {
        saveSetting($pdo, $f, trim($_POST[$f] ?? ''));
    }
    foreach (['receipt_show_tax','receipt_show_service','receipt_print_kitchen',
              'receipt_email_customer','payment_card','payment_cash','payment_mobile',
              'payment_bank','payment_voucher','payment_crypto'] as $cb) {
        saveSetting($pdo, $cb, isset($_POST[$cb]) ? '1' : '0');
    }
    $msg = '✅ Billing & tax settings saved!';
}

if ($section === 'notif') {
    saveSetting($pdo, 'notif_email', trim($_POST['notif_email'] ?? ''));
    foreach (['notif_new_order','notif_order_cancelled','notif_low_inventory','notif_new_customer',
              'notif_daily_summary','notif_weekly_report','notif_staff_clock','notif_payment_failed'] as $cb) {
        saveSetting($pdo, $cb, isset($_POST[$cb]) ? '1' : '0');
    }
    saveSetting($pdo, 'alert_low_stock',         trim($_POST['alert_low_stock'] ?? '5'));
    saveSetting($pdo, 'alert_pending_order_mins', trim($_POST['alert_pending_order_mins'] ?? '15'));
    saveSetting($pdo, 'alert_daily_revenue',      trim($_POST['alert_daily_revenue'] ?? '2500'));
    $msg = '✅ Notification settings saved!';
}

if ($section === 'appearance') {
    foreach (['accent_colour','date_format','time_format','language','timezone','rows_per_page','default_view'] as $f) {
        saveSetting($pdo, $f, trim($_POST[$f] ?? ''));
    }
    foreach (['show_greeting','show_low_stock_badge','compact_tables','animate_transitions'] as $cb) {
        saveSetting($pdo, $cb, isset($_POST[$cb]) ? '1' : '0');
    }
    $msg = '✅ Appearance settings saved!';
}

if ($section === 'security') {
    $admin_name  = trim($_POST['admin_name']  ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    saveSetting($pdo, 'admin_name',  $admin_name);
    saveSetting($pdo, 'admin_email', $admin_email);
    saveSetting($pdo, 'auto_logout_mins',   $_POST['auto_logout_mins']   ?? '30');
    saveSetting($pdo, 'max_login_attempts', $_POST['max_login_attempts'] ?? '5');
    foreach (['require_pw_on_return','enable_2fa','log_admin_actions'] as $cb) {
        saveSetting($pdo, $cb, isset($_POST[$cb]) ? '1' : '0');
    }
    // Update admin email in users table too
    $admin_row = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
    if ($admin_row) {
        $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?")
            ->execute([$admin_name, $admin_email, $admin_row['id']]);
    }
    // Admin password change
    $new_pw  = $_POST['new_pw']     ?? '';
    $conf_pw = $_POST['confirm_pw'] ?? '';
    if ($new_pw) {
        if (!$admin_row) {
            $msg = '❌ Admin account not found.'; $msg_type = 'danger';
        } elseif (strlen($new_pw) < 8) {
            $msg = '❌ New password must be at least 8 characters.'; $msg_type = 'danger';
        } elseif ($new_pw !== $conf_pw) {
            $msg = '❌ New passwords do not match.'; $msg_type = 'danger';
        } else {
            $pdo->prepare("UPDATE users SET password=?, password_hint=? WHERE id=?")
                ->execute([password_hash($new_pw, PASSWORD_DEFAULT), $new_pw, $admin_row['id']]);
            $msg = '✅ Admin account & password updated!';
        }
    } else {
        $msg = '✅ Admin account updated!';
    }
}

// ── ADD CASHIER ────────────────────────────────────────────
if ($section === 'cashier_add') {
    $name   = trim($_POST['cashier_name']   ?? '');
    $email  = trim($_POST['cashier_email']  ?? '');
    $pw     = $_POST['cashier_pw']           ?? '';
    $pw2    = $_POST['cashier_pw2']          ?? '';
    $status = $_POST['cashier_status']       ?? 'active';
    if (empty($name) || empty($email)) {
        $msg = '❌ Name and email are required.'; $msg_type = 'danger';
    } elseif (empty($pw)) {
        $msg = '❌ Password is required.'; $msg_type = 'danger';
    } elseif (strlen($pw) < 8) {
        $msg = '❌ Password must be at least 8 characters.'; $msg_type = 'danger';
    } elseif ($pw !== $pw2) {
        $msg = '❌ Passwords do not match.'; $msg_type = 'danger';
    } else {
        // Check email not already used
        $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $msg = '❌ That email is already in use.'; $msg_type = 'danger';
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password, password_hint, role, status) VALUES (?,?,?,?,'cashier',?)")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $pw, $status]);
            $msg = '✅ Cashier "' . htmlspecialchars($name) . '" created!';
        }
    }
}

// ── EDIT CASHIER ───────────────────────────────────────────
if ($section === 'cashier_edit') {
    $cid    = (int)($_POST['cashier_id'] ?? 0);
    $name   = trim($_POST['cashier_name']  ?? '');
    $email  = trim($_POST['cashier_email'] ?? '');
    $pw     = $_POST['cashier_pw']          ?? '';
    $pw2    = $_POST['cashier_pw2']         ?? '';
    $status = $_POST['cashier_status']      ?? 'active';
    if (empty($name) || empty($email)) {
        $msg = '❌ Name and email are required.'; $msg_type = 'danger';
    } elseif ($pw && strlen($pw) < 8) {
        $msg = '❌ Password must be at least 8 characters.'; $msg_type = 'danger';
    } elseif ($pw && $pw !== $pw2) {
        $msg = '❌ Passwords do not match.'; $msg_type = 'danger';
    } else {
        if ($pw) {
            $pdo->prepare("UPDATE users SET name=?, email=?, password=?, password_hint=?, status=? WHERE id=? AND role='cashier'")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $pw, $status, $cid]);
        } else {
            $pdo->prepare("UPDATE users SET name=?, email=?, status=? WHERE id=? AND role='cashier'")
                ->execute([$name, $email, $status, $cid]);
        }
        $msg = '✅ Cashier updated!';
    }
}

// ── DELETE CASHIER ─────────────────────────────────────────
if ($section === 'cashier_delete') {
    $cid = (int)($_POST['cashier_id'] ?? 0);
    if ($cid) {
        $pdo->prepare("DELETE FROM users WHERE id=? AND role='cashier'")->execute([$cid]);
        $msg = '🗑️ Cashier deleted.'; $msg_type = 'danger';
    }
}

// ── Load all cashiers from DB ──────────────────────────────
$cashiers = $pdo->query("SELECT * FROM users WHERE role='cashier' ORDER BY created_at DESC")->fetchAll();

$S = loadSettings($pdo);
function getSetting(array $S, string $key, string $default = ''): string {
    return htmlspecialchars($S[$key] ?? $default);
}

$days_list = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$day_defaults = ['monday'=>['09:00','22:00',true],'tuesday'=>['09:00','22:00',true],
    'wednesday'=>['09:00','22:00',true],'thursday'=>['09:00','23:00',true],
    'friday'=>['10:00','23:30',true],'saturday'=>['10:00','23:30',true],'sunday'=>['11:00','21:00',false]];
$hours = [];
foreach ($days_list as $d) {
    $raw = $S["hours_{$d}"] ?? '';
    $parsed = $raw ? json_decode($raw, true) : null;
    $hours[$d] = $parsed ?: ['open'=>$day_defaults[$d][0],'close'=>$day_defaults[$d][1],'is_open'=>$day_defaults[$d][2]];
}
$closures   = json_decode($S['special_closures'] ?? '[]', true) ?: [];
$page_title = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-msg flash-<?= $msg_type ?>" id="flashMsg">
    <?= $msg ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:12px">✕</button>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Settings</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Manage your restaurant profile, preferences &amp; system configuration.</p>
    </div>
</div>

<div class="settings-tabs">
    <button class="tab-btn active" onclick="switchTab('profile',this)">🏠 Profile</button>
    <button class="tab-btn" onclick="switchTab('hours',this)">🕐 Hours</button>
    <button class="tab-btn" onclick="switchTab('billing',this)">💳 Billing</button>
    <button class="tab-btn" onclick="switchTab('notif',this)">🔔 Notifications</button>
    <button class="tab-btn" onclick="switchTab('appearance',this)">🎨 Appearance</button>
    <button class="tab-btn" onclick="switchTab('security',this)">🔒 Security</button>
</div>

<!-- ══ PROFILE ══ -->
<div class="tab-panel active" id="tab-profile">
<form method="POST" action="settings.php">
<input type="hidden" name="section" value="profile">
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🔥</div><div><div class="section-title">Brand Identity</div><div class="section-sub">Restaurant name and public details</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group span2"><label class="form-label">Restaurant Name *</label><input class="form-input" type="text" name="rest_name" value="<?= getSetting($S,'rest_name','Minmi Restaurent') ?>" required></div>
            <div class="form-group"><label class="form-label">Tagline</label><input class="form-input" type="text" name="rest_tagline" value="<?= getSetting($S,'rest_tagline','Fire-crafted flavours, every night') ?>"></div>
            <div class="form-group"><label class="form-label">Cuisine Type</label>
                <select class="form-input" name="rest_cuisine">
                    <?php foreach(['Contemporary International','Italian','SriLankan','French','American','Mediterranean','Japanese','Mexican','Indian'] as $c): ?>
                    <option <?= ($S['rest_cuisine']??'')===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Seating Capacity</label><input class="form-input" type="number" name="rest_capacity" value="<?= getSetting($S,'rest_capacity','85') ?>" min="1"></div>
            <div class="form-group span2"><label class="form-label">About / Description</label><textarea class="form-input" name="rest_desc" rows="3"><?= getSetting($S,'rest_desc') ?></textarea></div>
        </div>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">📍</div><div><div class="section-title">Contact &amp; Location</div><div class="section-sub">Shown on receipts and public listings</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group span2"><label class="form-label">Street Address</label><input class="form-input" type="text" name="rest_address" value="<?= getSetting($S,'rest_address') ?>"></div>
            <div class="form-group"><label class="form-label">City</label><input class="form-input" type="text" name="rest_city" value="<?= getSetting($S,'rest_city') ?>"></div>
            <div class="form-group"><label class="form-label">State / Province</label><input class="form-input" type="text" name="rest_state" value="<?= getSetting($S,'rest_state') ?>"></div>
            <div class="form-group"><label class="form-label">ZIP / Postal Code</label><input class="form-input" type="text" name="rest_zip" value="<?= getSetting($S,'rest_zip') ?>"></div>
            <div class="form-group"><label class="form-label">Country</label>
                <select class="form-input" name="rest_country">
                    <?php foreach(['United States','United Kingdom','Canada','Australia','Germany','France','Sri Lanka','India','Japan'] as $c): ?>
                    <option <?= ($S['rest_country']??'')===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Phone</label><input class="form-input" type="tel" name="rest_phone" value="<?= getSetting($S,'rest_phone') ?>"></div>
            <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="rest_email" value="<?= getSetting($S,'rest_email') ?>"></div>
            <div class="form-group"><label class="form-label">Website</label><input class="form-input" type="url" name="rest_website" value="<?= getSetting($S,'rest_website') ?>"></div>
        </div>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">📲</div><div><div class="section-title">Social Media</div><div class="section-sub">Links on your public profile</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">Instagram</label><div class="input-prefix-wrap"><span class="input-prefix">@</span><input class="form-input has-prefix" type="text" name="rest_instagram" value="<?= getSetting($S,'rest_instagram') ?>"></div></div>
            <div class="form-group"><label class="form-label">Facebook</label><div class="input-prefix-wrap"><span class="input-prefix">fb.com/</span><input class="form-input has-prefix" type="text" name="rest_facebook" value="<?= getSetting($S,'rest_facebook') ?>"></div></div>
            <div class="form-group"><label class="form-label">Twitter / X</label><div class="input-prefix-wrap"><span class="input-prefix">@</span><input class="form-input has-prefix" type="text" name="rest_twitter" value="<?= getSetting($S,'rest_twitter') ?>"></div></div>
        </div>
    </div>
</div>
<div class="form-actions-bar"><button type="submit" class="btn btn-primary">💾 Save Profile</button></div>
</form>
</div>

<!-- ══ HOURS ══ -->
<div class="tab-panel" id="tab-hours">
<form method="POST" action="settings.php" id="hoursForm">
<input type="hidden" name="section" value="hours">
<input type="hidden" name="special_closures" id="closuresInput" value="<?= htmlspecialchars($S['special_closures'] ?? '[]') ?>">
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🕐</div><div><div class="section-title">Weekly Operating Hours</div><div class="section-sub">Toggle the switch to mark a day as closed</div></div></div>
    <div class="settings-body">
        <?php foreach ($days_list as $d): $h = $hours[$d]; $is_open = (bool)$h['is_open']; ?>
        <div class="hours-row">
            <div class="hours-day">
                <label class="toggle-wrap">
                    <input type="checkbox" class="toggle-input" name="day_open_<?= $d ?>" <?= $is_open?'checked':'' ?> onchange="toggleDay('<?= $d ?>',this.checked)">
                    <span class="toggle-slider"></span>
                </label>
                <span class="day-name"><?= ucfirst($d) ?></span>
                <span class="badge <?= $is_open?'badge-green':'badge-red' ?>" id="daybadge-<?= $d ?>" style="font-size:.65rem"><?= $is_open?'Open':'Closed' ?></span>
            </div>
            <div class="hours-times <?= !$is_open?'disabled':'' ?>" id="times-<?= $d ?>">
                <div class="time-field"><label class="form-label">Opens</label><input class="form-input time-input" type="time" name="open_<?= $d ?>" value="<?= htmlspecialchars($h['open']) ?>" <?= !$is_open?'disabled':'' ?>></div>
                <span class="time-sep">→</span>
                <div class="time-field"><label class="form-label">Closes</label><input class="form-input time-input" type="time" name="close_<?= $d ?>" value="<?= htmlspecialchars($h['close']) ?>" <?= !$is_open?'disabled':'' ?>></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🎉</div><div><div class="section-title">Special Closures</div><div class="section-sub">Mark public holidays or one-off closures</div></div></div>
    <div class="settings-body">
        <div id="closuresList">
            <?php foreach ($closures as $cl): ?>
            <div class="closure-row">
                <input class="form-input closure-date" type="date" value="<?= htmlspecialchars($cl['date']??'') ?>" style="max-width:180px">
                <input class="form-input closure-reason" type="text" value="<?= htmlspecialchars($cl['reason']??'') ?>" placeholder="Reason" style="flex:1">
                <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeClosure(this)">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-top:12px" onclick="addClosure()">＋ Add Closure Date</button>
    </div>
</div>
<div class="form-actions-bar"><button type="submit" class="btn btn-primary" onclick="serializeClosures()">💾 Save Hours</button></div>
</form>
</div>

<!-- ══ BILLING ══ -->
<div class="tab-panel" id="tab-billing">
<form method="POST" action="settings.php">
<input type="hidden" name="section" value="billing">
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">💰</div><div><div class="section-title">Tax &amp; Service Charges</div><div class="section-sub">Applied automatically to all orders</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">Tax Rate (%)</label><input class="form-input" type="number" name="tax_rate" step="0.01" min="0" max="100" value="<?= getSetting($S,'tax_rate','8.5') ?>"></div>
            <div class="form-group"><label class="form-label">Service Charge (%)</label><input class="form-input" type="number" name="service_charge" step="0.01" min="0" max="100" value="<?= getSetting($S,'service_charge','10') ?>"></div>
            <div class="form-group"><label class="form-label">Currency</label>
                <select class="form-input" name="currency">
                    <?php foreach(['USD','GBP','EUR','CAD','AUD','LKR','INR','JPY'] as $c): ?>
                    <option <?= ($S['currency']??'USD')===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Currency Symbol</label><input class="form-input" type="text" name="currency_symbol" maxlength="5" value="<?= getSetting($S,'currency_symbol','Rs.') ?>"></div>
        </div>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🧾</div><div><div class="section-title">Receipt &amp; Invoice</div><div class="section-sub">Customise printed and digital receipts</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">Receipt Header</label><input class="form-input" type="text" name="receipt_header" value="<?= getSetting($S,'receipt_header') ?>"></div>
            <div class="form-group"><label class="form-label">Receipt Footer</label><input class="form-input" type="text" name="receipt_footer" value="<?= getSetting($S,'receipt_footer') ?>"></div>
            <div class="form-group"><label class="form-label">Invoice Prefix</label><input class="form-input" type="text" name="invoice_prefix" maxlength="10" value="<?= getSetting($S,'invoice_prefix','MNM-') ?>"></div>
        </div>
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:12px">
            <?php foreach(['receipt_show_tax'=>'Show tax breakdown','receipt_show_service'=>'Show service charge line','receipt_print_kitchen'=>'Print kitchen copy automatically','receipt_email_customer'=>'Email receipt to customer'] as $n=>$l): ?>
            <label class="checkbox-row"><input type="checkbox" name="<?= $n ?>" <?= ($S[$n]??'0')==='1'?'checked':'' ?>><?= $l ?></label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">💳</div><div><div class="section-title">Payment Methods</div><div class="section-sub">Toggle accepted payment types</div></div></div>
    <div class="settings-body">
        <div class="payment-grid">
            <?php foreach(['payment_card'=>['💳','Credit / Debit Card'],'payment_cash'=>['💵','Cash'],'payment_mobile'=>['📱','Mobile Pay'],'payment_bank'=>['🏦','Bank Transfer'],'payment_voucher'=>['🎁','Gift Voucher'],'payment_crypto'=>['🪙','Cryptocurrency']] as $n=>[$ic,$lb]): $on=($S[$n]??'0')==='1'; ?>
            <div class="payment-card <?= $on?'enabled':'' ?>">
                <span class="pm-icon"><?= $ic ?></span>
                <span class="pm-label"><?= $lb ?></span>
                <label class="toggle-wrap" style="margin-left:auto"><input type="checkbox" class="toggle-input" name="<?= $n ?>" <?= $on?'checked':'' ?> onchange="this.closest('.payment-card').classList.toggle('enabled',this.checked)"><span class="toggle-slider"></span></label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="form-actions-bar"><button type="submit" class="btn btn-primary">💾 Save Billing</button></div>
</form>
</div>

<!-- ══ NOTIFICATIONS ══ -->
<div class="tab-panel" id="tab-notif">
<form method="POST" action="settings.php">
<input type="hidden" name="section" value="notif">
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">📧</div><div><div class="section-title">Email Notifications</div><div class="section-sub">Events that trigger email alerts</div></div></div>
    <div class="settings-body">
        <div class="form-group" style="max-width:400px;margin-bottom:20px">
            <label class="form-label">Notification Email</label>
            <input class="form-input" type="email" name="notif_email" value="<?= getSetting($S,'notif_email') ?>">
        </div>
        <?php foreach(['notif_new_order'=>'New order received','notif_order_cancelled'=>'Order cancelled','notif_low_inventory'=>'Low inventory alert','notif_new_customer'=>'New customer registration','notif_daily_summary'=>'Daily sales summary (6pm)','notif_weekly_report'=>'Weekly performance report','notif_staff_clock'=>'Staff clock-in / clock-out','notif_payment_failed'=>'Payment failed or declined'] as $n=>$l): ?>
        <label class="notif-row"><span class="notif-label"><?= $l ?></span><label class="toggle-wrap"><input type="checkbox" class="toggle-input" name="<?= $n ?>" <?= ($S[$n]??'0')==='1'?'checked':'' ?>><span class="toggle-slider"></span></label></label>
        <?php endforeach; ?>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🔔</div><div><div class="section-title">Dashboard Alert Thresholds</div><div class="section-sub">Warning triggers</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">Low Stock Alert (units)</label><input class="form-input" type="number" name="alert_low_stock" min="1" value="<?= getSetting($S,'alert_low_stock','5') ?>"><span class="form-hint">Alert when stock falls below this</span></div>
            <div class="form-group"><label class="form-label">Pending Order Alert (mins)</label><input class="form-input" type="number" name="alert_pending_order_mins" min="1" value="<?= getSetting($S,'alert_pending_order_mins','15') ?>"><span class="form-hint">Alert if order stays pending longer</span></div>
            <div class="form-group"><label class="form-label">Daily Revenue Target (Rs.)</label><input class="form-input" type="number" name="alert_daily_revenue" min="0" value="<?= getSetting($S,'alert_daily_revenue','2500') ?>"><span class="form-hint">Alert if not met by 8pm</span></div>
        </div>
    </div>
</div>
<div class="form-actions-bar"><button type="submit" class="btn btn-primary">💾 Save Notifications</button></div>
</form>
</div>

<!-- ══ APPEARANCE ══ -->
<div class="tab-panel" id="tab-appearance">
<form method="POST" action="settings.php">
<input type="hidden" name="section" value="appearance">
<input type="hidden" name="accent_colour" id="accentInput" value="<?= getSetting($S,'accent_colour','#e8622a') ?>">
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🎨</div><div><div class="section-title">Theme &amp; Display</div><div class="section-sub">Colour accent and layout preferences</div></div></div>
    <div class="settings-body">
        <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">Accent Colour</label>
            <div class="colour-swatches">
                <?php $curAcc=$S['accent_colour']??'#e8622a'; foreach(['#e8622a'=>'Ember Orange','#3ecf8e'=>'Mint Green','#4e9cf7'=>'Sky Blue','#a855f7'=>'Violet','#f5c842'=>'Gold','#e84242'=>'Ruby Red'] as $hex=>$name): ?>
                <button type="button" class="colour-swatch <?= $curAcc===$hex?'active':'' ?>" style="background:<?= $hex ?>" title="<?= $name ?>" onclick="selectAccent(this,'<?= $hex ?>')"><?= $curAcc===$hex?'✓':'' ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group"><label class="form-label">Date Format</label><select class="form-input" name="date_format"><?php foreach(['DD/MM/YYYY','MM/DD/YYYY','YYYY-MM-DD'] as $o): ?><option <?= ($S['date_format']??'')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Time Format</label><select class="form-input" name="time_format"><?php foreach(['12-hour (AM/PM)','24-hour'] as $o): ?><option <?= ($S['time_format']??'')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Language</label><select class="form-input" name="language"><?php foreach(['English (US)','English (UK)','French','Spanish','German','Japanese'] as $o): ?><option <?= ($S['language']??'')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Timezone</label><select class="form-input" name="timezone"><?php foreach(['America/New_York (UTC-5)','America/Los_Angeles (UTC-8)','Europe/London (UTC+0)','Europe/Paris (UTC+1)','Asia/Colombo (UTC+5:30)','Asia/Tokyo (UTC+9)'] as $o): ?><option <?= ($S['timezone']??'')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Rows per Page</label><select class="form-input" name="rows_per_page"><?php foreach(['10','25','50','100'] as $o): ?><option <?= ($S['rows_per_page']??'25')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Default View</label><select class="form-input" name="default_view"><?php foreach(['Dashboard Overview','Orders','Reports'] as $o): ?><option <?= ($S['default_view']??'')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:12px">
            <?php foreach(['show_greeting'=>'Show welcome greeting on dashboard','show_low_stock_badge'=>'Show low-stock badge in topbar','compact_tables'=>'Enable compact table mode','animate_transitions'=>'Animate page transitions'] as $n=>$l): ?>
            <label class="checkbox-row"><input type="checkbox" name="<?= $n ?>" <?= ($S[$n]??'0')==='1'?'checked':'' ?>><?= $l ?></label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="form-actions-bar"><button type="submit" class="btn btn-primary">💾 Save Appearance</button></div>
</form>
</div>

<!-- ══ SECURITY ══ -->
<div class="tab-panel" id="tab-security">

<!-- ── ADMIN ACCOUNT ── -->
<form method="POST" action="settings.php">
<input type="hidden" name="section" value="security">
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">👤</div><div><div class="section-title">Admin Account</div><div class="section-sub">Update your login credentials</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">Full Name</label><input class="form-input" type="text" name="admin_name" value="<?= getSetting($S,'admin_name','Admin') ?>"></div>
            <div class="form-group"><label class="form-label">Admin Email</label><input class="form-input" type="email" name="admin_email" value="<?= getSetting($S,'admin_email') ?>"></div>
        </div>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🔑</div><div><div class="section-title">Change Admin Password</div><div class="section-sub">Enter new password and confirm to update</div></div></div>
    <div class="settings-body">
        <div class="form-grid">
            <div class="form-group"><label class="form-label">New Password</label><div class="input-eye-wrap"><input class="form-input" type="password" name="new_pw" id="new_pw" placeholder="Min 8 characters" oninput="checkStrength(this.value,'pwFill','pwLabel')"><button class="eye-btn" type="button" onclick="togglePw('new_pw',this)">👁️</button></div><div class="pw-strength-bar"><div class="pw-strength-fill" id="pwFill"></div></div><span class="form-hint" id="pwLabel">Enter a new password</span></div>
            <div class="form-group"><label class="form-label">Confirm Password</label><div class="input-eye-wrap"><input class="form-input" type="password" name="confirm_pw" id="confirm_pw" placeholder="Repeat new password"><button class="eye-btn" type="button" onclick="togglePw('confirm_pw',this)">👁️</button></div></div>
        </div>
    </div>
</div>
<div class="settings-section">
    <div class="section-hdr"><div class="section-icon">🛡️</div><div><div class="section-title">Security Preferences</div><div class="section-sub">Session and access control</div></div></div>
    <div class="settings-body">
        <div class="form-grid" style="margin-bottom:16px">
            <div class="form-group"><label class="form-label">Auto Logout (minutes)</label><select class="form-input" name="auto_logout_mins"><?php foreach(['15','30','60','120','Never'] as $o): ?><option <?= ($S['auto_logout_mins']??'30')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label class="form-label">Max Login Attempts</label><select class="form-input" name="max_login_attempts"><?php foreach(['3','5','10'] as $o): ?><option <?= ($S['max_login_attempts']??'5')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach(['require_pw_on_return'=>'Require password after auto-logout','enable_2fa'=>'Enable two-factor authentication (2FA)','log_admin_actions'=>'Log all admin actions'] as $n=>$l): ?>
            <label class="checkbox-row"><input type="checkbox" name="<?= $n ?>" <?= ($S[$n]??'0')==='1'?'checked':'' ?>><?= $l ?></label>
            <?php endforeach; ?>
        </div>
        <div class="danger-zone">
            <div class="danger-title">⚠️ Danger Zone</div>
            <div class="danger-body">
                <div class="danger-row"><div><strong>Reset all settings to default</strong><p>Wipe all customisations and restore factory defaults.</p></div><button type="button" class="btn btn-danger btn-sm" onclick="confirmAction('Reset all settings?','This cannot be undone.',()=>showToast('⚠️ Coming soon'))">Reset</button></div>
                <div class="danger-row"><div><strong>Clear all data</strong><p>Permanently delete all orders, customers and inventory.</p></div><button type="button" class="btn btn-danger btn-sm" onclick="confirmAction('Clear all data?','This will permanently delete everything. Cannot be undone.',()=>showToast('⚠️ Coming soon'))">Clear Data</button></div>
            </div>
        </div>
    </div>
</div>
<div class="form-actions-bar"><button type="submit" class="btn btn-primary">💾 Save Security</button></div>
</form>

<!-- ── CASHIER ACCOUNTS ── -->
<div class="settings-section" style="border:1px solid var(--accent);margin-top:8px">
    <div class="section-hdr" style="background:rgba(232,98,42,.06)">
        <div class="section-icon">🧾</div>
        <div>
            <div class="section-title">Cashier Accounts</div>
            <div class="section-sub">Cashiers can access Orders, Reservations and Customers only</div>
        </div>
        <span class="badge badge-gray" style="margin-left:auto"><?= count($cashiers) ?> cashier<?= count($cashiers)!==1?'s':'' ?></span>
    </div>
    <div class="settings-body">

        <!-- Existing cashiers list -->
        <?php if (!empty($cashiers)): ?>
        <div style="margin-bottom:20px">
            <?php foreach ($cashiers as $c): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg-3);
                        border-radius:var(--radius);margin-bottom:8px;border:1px solid var(--border)">
                <div style="width:36px;height:36px;border-radius:50%;background:var(--accent);
                            display:flex;align-items:center;justify-content:center;
                            font-weight:700;color:#fff;font-size:.85rem;flex-shrink:0">
                    <?= strtoupper(substr($c['name'],0,1)) ?>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:.88rem"><?= htmlspecialchars($c['name']) ?></div>
                    <div style="color:var(--text-3);font-size:.75rem"><?= htmlspecialchars($c['email']) ?>
                        <?php if ($c['password_hint']): ?>
                        &nbsp;·&nbsp; 🔑 <span style="font-family:monospace"><?= htmlspecialchars($c['password_hint']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="badge <?= $c['status']==='active'?'badge-green':'badge-red' ?>">
                    <?= $c['status']==='active'?'🟢 Active':'🔴 Inactive' ?>
                </span>
                <button type="button" class="btn btn-ghost btn-sm btn-icon"
                        onclick="openEditCashier(<?= htmlspecialchars(json_encode($c)) ?>)"
                        title="Edit">✏️</button>
                <form method="POST" action="settings.php" style="display:inline"
                      onsubmit="return confirm('Delete cashier <?= htmlspecialchars($c['name']) ?>?')">
                    <input type="hidden" name="section"    value="cashier_delete">
                    <input type="hidden" name="cashier_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete">🗑️</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Add new cashier form -->
        <div style="border-top:1px solid var(--border);padding-top:18px;margin-top:4px">
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                        color:var(--accent);margin-bottom:14px">➕ Add New Cashier</div>
            <form method="POST" action="settings.php">
            <input type="hidden" name="section" value="cashier_add">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input class="form-input" type="text" name="cashier_name" placeholder="e.g. Sarah Perera" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input class="form-input" type="email" name="cashier_email" placeholder="cashier@minmi.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <div class="input-eye-wrap">
                        <input class="form-input" type="password" name="cashier_pw" id="cashier_pw"
                               placeholder="Min 8 characters"
                               oninput="checkStrength(this.value,'cPwFill','cPwLabel')">
                        <button class="eye-btn" type="button" onclick="togglePw('cashier_pw',this)">👁️</button>
                    </div>
                    <div class="pw-strength-bar"><div class="pw-strength-fill" id="cPwFill"></div></div>
                    <span class="form-hint" id="cPwLabel">Enter a password</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <div class="input-eye-wrap">
                        <input class="form-input" type="password" name="cashier_pw2" id="cashier_pw2" placeholder="Repeat password">
                        <button class="eye-btn" type="button" onclick="togglePw('cashier_pw2',this)">👁️</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-input" name="cashier_status">
                        <option value="active">🟢 Active</option>
                        <option value="inactive">🔴 Inactive</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:14px">
                <button type="submit" class="btn btn-primary">➕ Create Cashier</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Cashier Modal -->
<div class="modal-backdrop" id="editCashierModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Cashier</div>
            <button class="modal-close" onclick="closeModal('editCashierModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="settings.php">
                <input type="hidden" name="section"    value="cashier_edit">
                <input type="hidden" name="cashier_id" id="edit_cashier_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" type="text" name="cashier_name" id="edit_cashier_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input class="form-input" type="email" name="cashier_email" id="edit_cashier_email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="input-eye-wrap">
                            <input class="form-input" type="password" name="cashier_pw" id="edit_cashier_pw"
                                   placeholder="Leave blank to keep current">
                            <button class="eye-btn" type="button" onclick="togglePw('edit_cashier_pw',this)">👁️</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-eye-wrap">
                            <input class="form-input" type="password" name="cashier_pw2" id="edit_cashier_pw2"
                                   placeholder="Repeat new password">
                            <button class="eye-btn" type="button" onclick="togglePw('edit_cashier_pw2',this)">👁️</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="cashier_status" id="edit_cashier_status">
                            <option value="active">🟢 Active</option>
                            <option value="inactive">🔴 Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('editCashierModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div><!-- end tab-security -->

<!-- Confirm Modal -->
<div class="modal-backdrop" id="confirmModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-body" style="text-align:center;padding:32px 28px 24px">
            <div style="font-size:2.8rem;margin-bottom:12px">⚠️</div>
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px" id="confirmTitle"></h3>
            <p id="confirmMsg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <div style="display:flex;gap:10px;justify-content:center">
                <button class="btn btn-ghost" onclick="closeConfirm()">Cancel</button>
                <button class="btn btn-danger" id="confirmOkBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>
<div class="toast" id="toast"></div>

<style>
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:var(--green-dim);color:var(--green);border:1px solid var(--green)}
.flash-danger{background:var(--red-dim);color:var(--red);border:1px solid var(--red)}
.settings-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:24px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:6px}
.tab-btn{background:none;border:none;color:var(--text-2);padding:8px 16px;border-radius:var(--radius);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:500;transition:all .2s}
.tab-btn:hover{color:var(--text);background:var(--bg-3)}
.tab-btn.active{background:var(--accent);color:#fff}
.tab-panel{display:none}.tab-panel.active{display:block}
.settings-section{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:16px;overflow:hidden}
.section-hdr{display:flex;align-items:center;gap:14px;padding:18px 20px;border-bottom:1px solid var(--border)}
.section-icon{font-size:1.4rem;width:40px;height:40px;background:var(--bg-3);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.section-title{font-weight:600;font-size:.95rem}
.section-sub{color:var(--text-3);font-size:.78rem;margin-top:2px}
.settings-body{padding:20px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.span2{grid-column:span 2}
.form-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-3)}
.form-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:9px 13px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;width:100%;box-sizing:border-box}
.form-input:focus{border-color:var(--accent)}
select.form-input{cursor:pointer}
textarea.form-input{resize:vertical}
.form-hint{font-size:.72rem;color:var(--text-3);margin-top:2px}
.form-actions-bar{display:flex;justify-content:flex-end;margin-bottom:24px}
.input-prefix-wrap{display:flex;align-items:center;background:var(--bg-3);border:1px solid var(--border-l);border-radius:var(--radius);overflow:hidden}
.input-prefix{padding:9px 10px 9px 13px;color:var(--text-3);font-size:.83rem;white-space:nowrap}
.form-input.has-prefix{border:none;background:transparent;padding-left:4px}
.hours-row{display:flex;align-items:center;gap:20px;padding:12px 0;border-bottom:1px solid var(--border)}
.hours-row:last-child{border-bottom:none}
.hours-day{display:flex;align-items:center;gap:10px;min-width:200px}
.day-name{font-weight:600;font-size:.88rem;min-width:90px}
.hours-times{display:flex;align-items:center;gap:10px}
.hours-times.disabled{opacity:.4;pointer-events:none}
.time-field{display:flex;flex-direction:column;gap:4px}
.time-input{width:130px}
.time-sep{color:var(--text-3)}
.closure-row{display:flex;gap:10px;align-items:center;margin-bottom:8px}
.toggle-wrap{position:relative;display:inline-flex;align-items:center;cursor:pointer}
.toggle-input{opacity:0;width:0;height:0;position:absolute}
.toggle-slider{width:40px;height:22px;background:var(--bg-3);border:1px solid var(--border-l);border-radius:11px;transition:.2s;flex-shrink:0;position:relative}
.toggle-slider::after{content:'';position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:2px;left:2px;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.3)}
.toggle-input:checked+.toggle-slider{background:var(--accent);border-color:var(--accent)}
.toggle-input:checked+.toggle-slider::after{transform:translateX(18px)}
.checkbox-row{display:flex;align-items:center;gap:10px;font-size:.85rem;cursor:pointer;padding:8px 0;border-bottom:1px solid var(--border)}
.checkbox-row:last-child{border-bottom:none}
.checkbox-row input{accent-color:var(--accent);width:16px;height:16px;cursor:pointer}
.notif-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)}
.notif-row:last-child{border-bottom:none}
.notif-label{font-size:.85rem}
.payment-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.payment-card{display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius);transition:border-color .2s}
.payment-card.enabled{border-color:var(--accent)}
.pm-icon{font-size:1.2rem}.pm-label{font-size:.83rem;font-weight:500}
.colour-swatches{display:flex;gap:10px;flex-wrap:wrap}
.colour-swatch{width:36px;height:36px;border-radius:50%;border:3px solid transparent;cursor:pointer;font-size:.8rem;color:#fff;display:flex;align-items:center;justify-content:center;transition:transform .15s,border-color .15s}
.colour-swatch:hover{transform:scale(1.15)}
.colour-swatch.active{border-color:#fff;box-shadow:0 0 0 2px var(--accent)}
.input-eye-wrap{position:relative}
.input-eye-wrap .form-input{padding-right:40px}
.eye-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;padding:0}
.pw-strength-bar{height:4px;background:var(--bg-3);border-radius:2px;margin-top:6px;overflow:hidden}
.pw-strength-fill{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s}
.danger-zone{margin-top:24px;border:1px solid var(--red);border-radius:var(--radius-lg);overflow:hidden}
.danger-title{background:rgba(239,68,68,.1);color:var(--red);font-weight:700;font-size:.8rem;padding:10px 16px;letter-spacing:.05em}
.danger-body{padding:0 16px}
.danger-row{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:14px 0;border-bottom:1px solid var(--border)}
.danger-row:last-child{border-bottom:none}
.danger-row p{color:var(--text-3);font-size:.78rem;margin:4px 0 0}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;padding:4px;border-radius:6px}
.modal-close:hover{color:var(--text)}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}.form-group.span2{grid-column:span 1}.payment-grid{grid-template-columns:1fr}.hours-row{flex-direction:column;align-items:flex-start}}
</style>

<script>
function switchTab(id,btn){
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('tab-'+id).classList.add('active');
    btn.classList.add('active');
}
function toggleDay(day,isOpen){
    const times=document.getElementById('times-'+day);
    const badge=document.getElementById('daybadge-'+day);
    times.classList.toggle('disabled',!isOpen);
    times.querySelectorAll('input').forEach(i=>i.disabled=!isOpen);
    badge.textContent=isOpen?'Open':'Closed';
    badge.className='badge '+(isOpen?'badge-green':'badge-red');
    badge.style.fontSize='.65rem';
}
function addClosure(){
    const row=document.createElement('div');
    row.className='closure-row';
    row.innerHTML=`<input class="form-input closure-date" type="date" style="max-width:180px"><input class="form-input closure-reason" type="text" placeholder="Reason" style="flex:1"><button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeClosure(this)">✕</button>`;
    document.getElementById('closuresList').appendChild(row);
}
function removeClosure(btn){btn.closest('.closure-row').remove();}
function serializeClosures(){
    const data=[];
    document.querySelectorAll('#closuresList .closure-row').forEach(r=>{
        const date=r.querySelector('.closure-date').value;
        const reason=r.querySelector('.closure-reason').value;
        if(date) data.push({date,reason});
    });
    document.getElementById('closuresInput').value=JSON.stringify(data);
}
function selectAccent(btn,hex){
    document.querySelectorAll('.colour-swatch').forEach(b=>{b.classList.remove('active');b.textContent='';});
    btn.classList.add('active');btn.textContent='✓';
    document.getElementById('accentInput').value=hex;
}
function togglePw(id,btn){
    const input=document.getElementById(id);
    const show=input.type==='password';
    input.type=show?'text':'password';
    btn.textContent=show?'🙈':'👁️';
}
function checkStrength(pw,fillId,labelId){
    const fill=document.getElementById(fillId);
    const label=document.getElementById(labelId);
    let score=0;
    if(pw.length>=8) score++;
    if(/[A-Z]/.test(pw)) score++;
    if(/[0-9]/.test(pw)) score++;
    if(/[^A-Za-z0-9]/.test(pw)) score++;
    const levels=[
        {pct:'0%',color:'transparent',text:'Enter a password'},
        {pct:'25%',color:'#e84242',text:'Weak'},
        {pct:'50%',color:'#f5c842',text:'Fair'},
        {pct:'75%',color:'#4e9cf7',text:'Good'},
        {pct:'100%',color:'#3ecf8e',text:'Strong ✓'},
    ];
    fill.style.width=levels[score].pct;
    fill.style.background=levels[score].color;
    label.textContent=levels[score].text;
}
function openEditCashier(c) {
    document.getElementById('edit_cashier_id').value     = c.id;
    document.getElementById('edit_cashier_name').value   = c.name;
    document.getElementById('edit_cashier_email').value  = c.email;
    document.getElementById('edit_cashier_status').value = c.status;
    document.getElementById('edit_cashier_pw').value     = '';
    document.getElementById('edit_cashier_pw2').value    = '';
    openModal('editCashierModal');
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); })
);
function confirmAction(title,msg,cb){
    document.getElementById('confirmTitle').textContent=title;
    document.getElementById('confirmMsg').textContent=msg;
    _confirmCb=cb;
    document.getElementById('confirmModal').classList.add('open');
}
function closeConfirm(){document.getElementById('confirmModal').classList.remove('open');_confirmCb=null;}
document.getElementById('confirmOkBtn').addEventListener('click',()=>{if(_confirmCb)_confirmCb();closeConfirm();});
document.getElementById('confirmModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeConfirm();});
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),3000);}
const flash=document.getElementById('flashMsg');
if(flash) setTimeout(()=>flash.style.opacity='0',4000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>