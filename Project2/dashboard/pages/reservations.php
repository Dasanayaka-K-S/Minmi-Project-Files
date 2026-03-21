<?php
// ============================================================
//  RESERVATIONS — Minmi Restaurent Admin
//  Place in: dashboard/pages/reservations.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// ── Auto-add email_sent_status column if not exists ──
// This column tracks the last status for which email was sent
// preventing duplicate emails on page refresh
try { $pdo->query("SELECT email_sent_status FROM reservations LIMIT 1"); }
catch (PDOException $e) {
    $pdo->exec("ALTER TABLE reservations ADD COLUMN email_sent_status VARCHAR(20) DEFAULT '' AFTER status");
}

// ════════════════════════════════════════
//  HANDLE POST ACTIONS — PRG Pattern
// ════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $item_id = $_POST['item_id'] ?? '';
    $msg     = '';
    $msg_type = 'success';

    if ($action === 'add') {
        $new_id     = 'RES-' . strtoupper(substr(uniqid(), -6));
        $cust_name  = trim($_POST['customer_name']);
        $cust_email = trim($_POST['email']  ?? '');
        $cust_phone = trim($_POST['phone']  ?? '');
        $res_date   = $_POST['date'];
        $res_time   = $_POST['time'];
        $res_status = $_POST['status'] ?? 'Pending';
        $res_notes  = trim($_POST['notes'] ?? '');

        $pdo->prepare("INSERT INTO reservations (id, customer_name, email, phone, date, time, status, notes)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$new_id, $cust_name, $cust_email, $cust_phone, $res_date, $res_time, $res_status, $res_notes]);

        $msg = '✅ Reservation for "' . htmlspecialchars($cust_name) . '" added!';

        if ($cust_email) {
            $formatted_date = date('l, d F Y', strtotime($res_date));
            $formatted_time = date('g:i A',    strtotime($res_time));

            $email_body = "Thank you for choosing Minmi Restaurent! Your table reservation has been received and is currently <strong>{$res_status}</strong>.

<div style='background:#fff8f5;border-left:4px solid #e8622a;border-radius:0 8px 8px 0;padding:18px 20px;margin:20px 0'>
    <div style='font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#e8622a;margin-bottom:12px'>📋 Reservation Details</div>
    <table width='100%' cellpadding='6' cellspacing='0' style='font-size:.9rem;color:#333'>
        <tr><td style='color:#888;width:40%'>Reservation ID</td><td><strong>{$new_id}</strong></td></tr>
        <tr><td style='color:#888'>Date</td><td><strong>{$formatted_date}</strong></td></tr>
        <tr><td style='color:#888'>Time</td><td><strong>{$formatted_time}</strong></td></tr>
        <tr><td style='color:#888'>Status</td><td><span style='color:#e8622a;font-weight:700'>{$res_status}</span></td></tr>
        " . ($cust_phone ? "<tr><td style='color:#888'>Phone</td><td>{$cust_phone}</td></tr>" : "") . "
        " . ($res_notes  ? "<tr><td style='color:#888'>Notes</td><td>{$res_notes}</td></tr>"  : "") . "
    </table>
</div>
Our team will confirm your booking shortly. If you have any questions, please contact us at
<a href='mailto:minmirestaurant@gmail.com' style='color:#e8622a'>minmirestaurant@gmail.com</a>.
We look forward to serving you! 🔥";

            $mail_result = sendMail($cust_email, $cust_name,
                '🍽️ Reservation Confirmation — Minmi Restaurent', $email_body);

            if ($mail_result['success']) {
                $msg .= ' 📧 Confirmation email sent to ' . htmlspecialchars($cust_email) . '.';
                // Mark email as sent for this status
                $pdo->prepare("UPDATE reservations SET email_sent_status=? WHERE id=?")
                    ->execute([$res_status, $new_id]);
            } else {
                $msg     .= ' ⚠️ Saved but email failed: ' . htmlspecialchars($mail_result['error'] ?? 'Unknown error');
                $msg_type = 'warning';
            }
        }
    }

    if ($action === 'edit' && $item_id) {
        $pdo->prepare("UPDATE reservations SET customer_name=?, email=?, phone=?, date=?, time=?, status=?, notes=? WHERE id=?")
            ->execute([
                trim($_POST['customer_name']),
                trim($_POST['email']  ?? ''),
                trim($_POST['phone']  ?? ''),
                $_POST['date'],
                $_POST['time'],
                $_POST['status'],
                trim($_POST['notes'] ?? ''),
                $item_id,
            ]);
        $msg = '✅ Reservation for "' . htmlspecialchars($_POST['customer_name']) . '" updated!';
    }

    if ($action === 'delete' && $item_id) {
        $row = $pdo->prepare("SELECT customer_name FROM reservations WHERE id=?");
        $row->execute([$item_id]);
        $del_name = $row->fetchColumn() ?: $item_id;
        $pdo->prepare("DELETE FROM reservations WHERE id=?")->execute([$item_id]);
        $msg      = '🗑️ Reservation for "' . htmlspecialchars($del_name) . '" deleted.';
        $msg_type = 'danger';
    }

    if ($action === 'status' && $item_id) {
        $new_status = $_POST['new_status'] ?? '';

        // ── Email lock: only send if this status hasn't been emailed yet ──
        $lock_check = $pdo->prepare("SELECT email_sent_status, email FROM reservations WHERE id=?");
        $lock_check->execute([$item_id]);
        $lock_row = $lock_check->fetch();

        $pdo->prepare("UPDATE reservations SET status=? WHERE id=?")->execute([$new_status, $item_id]);
        $msg = '✅ Reservation marked as ' . htmlspecialchars($new_status) . '.';

        // Only send email if:
        // 1. Customer has an email
        // 2. This status hasn't been emailed before for this reservation
        if ($lock_row && !empty($lock_row['email']) && $lock_row['email_sent_status'] !== $new_status) {

            $res_row = $pdo->prepare("SELECT * FROM reservations WHERE id=?");
            $res_row->execute([$item_id]);
            $updated_res = $res_row->fetch();

            $formatted_date = date('l, d F Y', strtotime($updated_res['date']));
            $formatted_time = date('g:i A',    strtotime($updated_res['time']));

            $status_colors   = ['Confirmed'=>'#3ecf8e','Seated'=>'#e8622a','Completed'=>'#3ecf8e','Cancelled'=>'#e84242','Pending'=>'#f5c842'];
            $status_color    = $status_colors[$new_status] ?? '#888';

            $status_messages = [
                'Confirmed' => 'Great news! Your reservation has been <strong style="color:#3ecf8e">confirmed</strong>. We look forward to welcoming you!',
                'Seated'    => 'You are now <strong style="color:#e8622a">seated</strong> at Minmi Restaurent. Enjoy your dining experience! 🍽️',
                'Completed' => 'Thank you for dining with us! We hope you had a wonderful experience. See you again soon! 🌟',
                'Cancelled' => 'Your reservation has been <strong style="color:#e84242">cancelled</strong>. If this was a mistake, please contact us to rebook.',
                'Pending'   => 'Your reservation status has been updated to <strong>Pending</strong>. We will confirm your booking shortly.',
            ];
            $status_msg = $status_messages[$new_status] ?? "Your reservation status has been updated to <strong>{$new_status}</strong>.";

            $update_body = "{$status_msg}

<div style='background:#fff8f5;border-left:4px solid #e8622a;border-radius:0 8px 8px 0;padding:18px 20px;margin:20px 0'>
    <div style='font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#e8622a;margin-bottom:12px'>📋 Your Reservation</div>
    <table width='100%' cellpadding='6' cellspacing='0' style='font-size:.9rem;color:#333'>
        <tr><td style='color:#888;width:40%'>Reservation ID</td><td><strong>{$item_id}</strong></td></tr>
        <tr><td style='color:#888'>Date</td><td><strong>{$formatted_date}</strong></td></tr>
        <tr><td style='color:#888'>Time</td><td><strong>{$formatted_time}</strong></td></tr>
        <tr><td style='color:#888'>Status</td><td><span style='color:{$status_color};font-weight:700'>{$new_status}</span></td></tr>
    </table>
</div>
For any questions, contact us at
<a href='mailto:minmirestaurant@gmail.com' style='color:#e8622a'>minmirestaurant@gmail.com</a>.";

            $subject_map = [
                'Confirmed' => '✅ Reservation Confirmed — Minmi Restaurent',
                'Cancelled' => '❌ Reservation Cancelled — Minmi Restaurent',
                'Completed' => '🌟 Thank You for Dining — Minmi Restaurent',
                'Seated'    => '🪑 You Are Now Seated — Minmi Restaurent',
                'Pending'   => '⏳ Reservation Update — Minmi Restaurent',
            ];
            $subject = $subject_map[$new_status] ?? '🔄 Reservation Update — Minmi Restaurent';

            $mail_result = sendMail($updated_res['email'], $updated_res['customer_name'], $subject, $update_body);

            if ($mail_result['success']) {
                $msg .= ' 📧 Status email sent to ' . htmlspecialchars($updated_res['email']) . '.';
                // ── Mark this status as emailed so it won't send again ──
                $pdo->prepare("UPDATE reservations SET email_sent_status=? WHERE id=?")
                    ->execute([$new_status, $item_id]);
            } else {
                $msg .= ' ⚠️ Status updated but email failed.';
            }

        } elseif (!empty($lock_row['email']) && $lock_row['email_sent_status'] === $new_status) {
            $msg .= ' ℹ️ Email already sent for this status.';
        }
    }

    // ── PRG: redirect to GET after every POST ──
    $_SESSION['admin_flash']      = $msg;
    $_SESSION['admin_flash_type'] = $msg_type;
    header('Location: reservations.php');
    exit;
}

// ── Read flash from session ──
$msg      = $_SESSION['admin_flash']      ?? '';
$msg_type = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);

// ════════════════════════════════════════
//  FETCH DATA
// ════════════════════════════════════════
$reservations = $pdo->query("SELECT * FROM reservations ORDER BY date ASC, time ASC")->fetchAll();

$cnt_pending   = count(array_filter($reservations, fn($r) => $r['status'] === 'Pending'));
$cnt_confirmed = count(array_filter($reservations, fn($r) => $r['status'] === 'Confirmed'));
$cnt_seated    = count(array_filter($reservations, fn($r) => $r['status'] === 'Seated'));
$cnt_completed = count(array_filter($reservations, fn($r) => $r['status'] === 'Completed'));
$cnt_cancelled = count(array_filter($reservations, fn($r) => $r['status'] === 'Cancelled'));

$today      = date('Y-m-d');
$today_res  = array_filter($reservations, fn($r) => $r['date'] === $today);
$res_json   = json_encode($reservations);
$page_title = 'Reservations';

function resBadge(string $status): string {
    $map = ['Pending'=>'badge-yellow','Confirmed'=>'badge-blue','Seated'=>'badge-orange','Completed'=>'badge-green','Cancelled'=>'badge-red'];
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

<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Reservations</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Manage table bookings and guest reservations.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">＋ New Reservation</button>
</div>

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card yellow"><div class="stat-icon">⏳</div><div class="stat-label">Pending</div><div class="stat-value"><?= $cnt_pending ?></div><div class="stat-sub">Awaiting confirmation</div></div>
    <div class="stat-card blue"><div class="stat-icon">✔️</div><div class="stat-label">Confirmed</div><div class="stat-value"><?= $cnt_confirmed ?></div><div class="stat-sub">Ready to seat</div></div>
    <div class="stat-card orange"><div class="stat-icon">🪑</div><div class="stat-label">Seated</div><div class="stat-value"><?= $cnt_seated ?></div><div class="stat-sub">Currently dining</div></div>
    <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-label">Completed</div><div class="stat-value"><?= $cnt_completed ?></div><div class="stat-sub">Finished dining</div></div>
    <div class="stat-card red"><div class="stat-icon">❌</div><div class="stat-label">Cancelled</div><div class="stat-value"><?= $cnt_cancelled ?></div><div class="stat-sub">Total cancelled</div></div>
</div>

<!-- TODAY'S RESERVATIONS -->
<?php if (!empty($today_res)): ?>
<div class="card">
    <div class="card-header">
        <div class="card-title">📅 Today's Reservations</div>
        <span class="badge badge-orange"><?= count($today_res) ?> bookings today</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
        <?php foreach ($today_res as $r): ?>
        <div style="background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius);padding:14px;display:flex;flex-direction:column;gap:6px">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <strong style="font-size:.9rem"><?= htmlspecialchars($r['customer_name']) ?></strong>
                <?= resBadge($r['status']) ?>
            </div>
            <div style="color:var(--text-3);font-size:.78rem">
                🕐 <?= date('g:i A', strtotime($r['time'])) ?> &nbsp;·&nbsp; 📞 <?= htmlspecialchars($r['phone'] ?: '—') ?>
            </div>
            <?php if ($r['notes']): ?>
            <div style="color:var(--text-2);font-size:.76rem;font-style:italic">"<?= htmlspecialchars(mb_strimwidth($r['notes'], 0, 50, '…')) ?>"</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ALL RESERVATIONS TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">All Reservations</div>
        <span class="badge badge-gray" id="resCount"><?= count($reservations) ?> total</span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="text" id="searchRes" class="search-input" placeholder="🔍  Search name, email, phone…" oninput="filterRes()" style="flex:1;min-width:200px">
        <select id="filterResStatus" class="search-input" style="min-width:150px" onchange="filterRes()">
            <option value="">All Statuses</option>
            <option>Pending</option><option>Confirmed</option><option>Seated</option><option>Completed</option><option>Cancelled</option>
        </select>
        <select id="filterResDate" class="search-input" style="min-width:150px" onchange="filterRes()">
            <option value="">All Dates</option>
            <option value="today">Today</option><option value="upcoming">Upcoming</option><option value="past">Past</option>
        </select>
    </div>

    <?php if (empty($reservations)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-3)">
        <div style="font-size:2.5rem;margin-bottom:10px">🍽️</div>
        <p>No reservations yet. Click <strong>＋ New Reservation</strong> to get started.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table id="resTable">
            <thead>
                <tr><th>ID</th><th>Customer</th><th>Contact</th><th>Date</th><th>Time</th><th>Status</th><th>Notes</th><th style="text-align:center">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reservations as $r):
                $res_date   = $r['date'];
                $date_style = $res_date === $today ? 'color:var(--accent);font-weight:700' : 'color:var(--text-2)';
                $date_label = $res_date === $today ? '📅 Today' : date('M d, Y', strtotime($res_date));
            ?>
            <tr data-name="<?= strtolower(htmlspecialchars($r['customer_name'])) ?>"
                data-email="<?= strtolower(htmlspecialchars($r['email'])) ?>"
                data-phone="<?= htmlspecialchars($r['phone']) ?>"
                data-status="<?= htmlspecialchars($r['status']) ?>"
                data-date="<?= htmlspecialchars($r['date']) ?>">
                <td style="color:var(--text-3);font-size:.78rem"><?= htmlspecialchars($r['id']) ?></td>
                <td><strong><?= htmlspecialchars($r['customer_name']) ?></strong></td>
                <td style="font-size:.8rem">
                    <div style="color:var(--text-2)"><?= htmlspecialchars($r['email'] ?: '—') ?></div>
                    <div style="color:var(--text-3)"><?= htmlspecialchars($r['phone'] ?: '—') ?></div>
                </td>
                <td><span style="<?= $date_style ?>;font-size:.83rem"><?= $date_label ?></span></td>
                <td style="color:var(--text-2);font-size:.83rem"><?= date('g:i A', strtotime($r['time'])) ?></td>
                <td><?= resBadge($r['status']) ?></td>
                <td style="color:var(--text-3);font-size:.78rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="<?= htmlspecialchars($r['notes'] ?? '') ?>">
                    <?= $r['notes'] ? htmlspecialchars(mb_strimwidth($r['notes'], 0, 30, '…')) : '—' ?>
                </td>
                <td>
                    <div style="display:flex;gap:4px;justify-content:center">
                        <button class="btn btn-ghost btn-sm btn-icon btn-view"   data-id="<?= htmlspecialchars($r['id']) ?>" title="View">👁️</button>
                        <button class="btn btn-ghost btn-sm btn-icon btn-status" data-id="<?= htmlspecialchars($r['id']) ?>" data-status="<?= htmlspecialchars($r['status']) ?>" title="Change Status">🔄</button>
                        <button class="btn btn-ghost btn-sm btn-icon btn-edit"   data-id="<?= htmlspecialchars($r['id']) ?>" title="Edit">✏️</button>
                        <button class="btn btn-danger  btn-sm btn-icon btn-delete" data-id="<?= htmlspecialchars($r['id']) ?>" data-name="<?= htmlspecialchars($r['customer_name']) ?>" title="Delete">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ══ ADD MODAL ══ -->
<div class="modal-backdrop" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">＋ New Reservation</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="reservations.php">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group span2">
                        <label class="form-label">Customer Name *</label>
                        <input class="form-input" type="text" name="customer_name" placeholder="e.g. John Smith" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span style="color:#3ecf8e;font-size:.68rem;font-weight:400;text-transform:none"> — confirmation will be sent</span></label>
                        <input class="form-input" type="email" name="email" placeholder="customer@email.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-input" type="tel" name="phone" placeholder="+94 77 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input class="form-input" type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time *</label>
                        <input class="form-input" type="time" name="time" value="19:00" required>
                    </div>
                    <div class="form-group span2">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status">
                            <option value="Pending">⏳ Pending</option>
                            <option value="Confirmed">✔️ Confirmed</option>
                            <option value="Seated">🪑 Seated</option>
                            <option value="Completed">✅ Completed</option>
                            <option value="Cancelled">❌ Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group span2">
                        <label class="form-label">Notes</label>
                        <textarea class="form-input" name="notes" rows="3" placeholder="e.g. Window table preferred, birthday celebration…"></textarea>
                    </div>
                </div>
                <div style="background:rgba(62,207,142,.07);border:1px solid rgba(62,207,142,.25);border-radius:var(--radius);padding:10px 14px;margin-top:4px;font-size:.78rem;color:#3ecf8e">
                    📧 A booking confirmation email will automatically be sent to the customer if an email is provided.
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">＋ Add Reservation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-backdrop" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Reservation</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="reservations.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="item_id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group span2">
                        <label class="form-label">Customer Name *</label>
                        <input class="form-input" type="text" name="customer_name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input class="form-input" type="email" name="email" id="edit_email">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-input" type="tel" name="phone" id="edit_phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input class="form-input" type="date" name="date" id="edit_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time *</label>
                        <input class="form-input" type="time" name="time" id="edit_time" required>
                    </div>
                    <div class="form-group span2">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status" id="edit_status">
                            <option value="Pending">⏳ Pending</option>
                            <option value="Confirmed">✔️ Confirmed</option>
                            <option value="Seated">🪑 Seated</option>
                            <option value="Completed">✅ Completed</option>
                            <option value="Cancelled">❌ Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group span2">
                        <label class="form-label">Notes</label>
                        <textarea class="form-input" name="notes" id="edit_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ VIEW MODAL ══ -->
<div class="modal-backdrop" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👁️ Reservation Details</div>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <div class="modal-body" id="viewContent"></div>
    </div>
</div>

<!-- ══ STATUS MODAL ══ -->
<div class="modal-backdrop" id="statusModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <div class="modal-title">🔄 Change Status</div>
            <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-2);font-size:.85rem;margin-bottom:16px">Reservation: <strong id="status_res_id"></strong></p>
            <form method="POST" action="reservations.php">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="item_id" id="status_id">
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">
                    <?php foreach([
                        ['Pending',   '⏳', 'badge-yellow'],
                        ['Confirmed', '✔️', 'badge-blue'],
                        ['Seated',    '🪑', 'badge-orange'],
                        ['Completed', '✅', 'badge-green'],
                        ['Cancelled', '❌', 'badge-red'],
                    ] as [$st, $ic, $bc]): ?>
                    <label class="status-option">
                        <input type="radio" name="new_status" value="<?= $st ?>">
                        <span style="font-size:1.1rem"><?= $ic ?></span>
                        <span class="badge <?= $bc ?>"><?= $st ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="background:rgba(62,207,142,.07);border:1px solid rgba(62,207,142,.25);border-radius:var(--radius);padding:10px 14px;margin-bottom:16px;font-size:.77rem;color:#3ecf8e">
                    📧 A status update email will automatically be sent to the customer.
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
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">Delete Reservation?</h3>
            <p id="deleteMsg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <form method="POST" action="reservations.php">
                <input type="hidden" name="action" value="delete">
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
.form-group.span2{grid-column:span 2}
.form-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-3)}
.form-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:9px 13px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;width:100%;box-sizing:border-box}
.form-input:focus{border-color:var(--accent)}
select.form-input{cursor:pointer}
textarea.form-input{resize:vertical}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)}
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
.status-option{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:border-color .2s,background .2s}
.status-option:has(input:checked){border-color:var(--accent);background:rgba(232,98,42,.06)}
.status-option input{accent-color:var(--accent)}
</style>

<script>
const RESERVATIONS = <?= $res_json ?>;
const TODAY = '<?= $today ?>';

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.open').forEach(bd => bd.classList.remove('open'));
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const id = btn.dataset.id;
    const r  = id ? RESERVATIONS.find(x => x.id === id) : null;

    if (btn.classList.contains('btn-view') && r) {
        const statusColors = {Pending:'#f5c842',Confirmed:'#4e9cf7',Seated:'#e8622a',Completed:'#3ecf8e',Cancelled:'#e84242'};
        const dateLabel = r.date === TODAY ? '📅 Today' : r.date;
        document.getElementById('viewContent').innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
                <div>
                    <div style="font-family:'DM Serif Display',serif;font-size:1.3rem">${r.customer_name}</div>
                    <div style="color:var(--text-3);font-size:.8rem;margin-top:2px">${r.id}</div>
                </div>
                <span style="padding:5px 14px;border-radius:20px;font-size:.78rem;font-weight:700;background:rgba(255,255,255,.06);color:${statusColors[r.status]||'#aaa'}">${r.status}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
                ${vRow('📅','Date',dateLabel)}
                ${vRow('🕐','Time',r.time)}
                ${vRow('📧','Email',r.email||'—')}
                ${vRow('📞','Phone',r.phone||'—')}
            </div>
            ${r.notes ? `<div style="background:var(--bg-3);border-radius:var(--radius);padding:14px;margin-bottom:16px"><div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:6px">📝 Notes</div><div style="font-size:.85rem;line-height:1.7">${r.notes}</div></div>` : ''}
            <div style="display:flex;gap:8px">
                <button class="btn btn-ghost btn-sm" style="flex:1" onclick="closeModal('viewModal');setTimeout(()=>document.querySelector('.btn-edit[data-id=\\'${r.id}\\']').click(),100)">✏️ Edit</button>
                <button class="btn btn-ghost btn-sm" style="flex:1" onclick="closeModal('viewModal');setTimeout(()=>document.querySelector('.btn-status[data-id=\\'${r.id}\\']').click(),100)">🔄 Change Status</button>
            </div>`;
        openModal('viewModal');
    }

    if (btn.classList.contains('btn-edit') && r) {
        document.getElementById('edit_id').value     = r.id;
        document.getElementById('edit_name').value   = r.customer_name;
        document.getElementById('edit_email').value  = r.email  || '';
        document.getElementById('edit_phone').value  = r.phone  || '';
        document.getElementById('edit_date').value   = r.date;
        document.getElementById('edit_time').value   = r.time;
        document.getElementById('edit_status').value = r.status;
        document.getElementById('edit_notes').value  = r.notes  || '';
        openModal('editModal');
    }

    if (btn.classList.contains('btn-status') && r) {
        document.getElementById('status_id').value = r.id;
        document.getElementById('status_res_id').textContent = r.id + ' — ' + r.customer_name;
        const radio = document.querySelector(`input[name="new_status"][value="${r.status}"]`);
        if (radio) radio.checked = true;
        openModal('statusModal');
    }

    if (btn.classList.contains('btn-delete') && id) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteMsg').textContent = 'This will permanently delete the reservation for "' + btn.dataset.name + '". This cannot be undone.';
        openModal('deleteModal');
    }
});

function vRow(icon, label, value) {
    return `<div style="background:var(--bg-3);border-radius:var(--radius);padding:12px"><div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:4px">${icon} ${label}</div><div style="font-size:.85rem;font-weight:600">${value}</div></div>`;
}

function filterRes() {
    const q  = document.getElementById('searchRes').value.toLowerCase();
    const st = document.getElementById('filterResStatus').value;
    const df = document.getElementById('filterResDate').value;
    let n = 0;
    document.querySelectorAll('#resTable tbody tr').forEach(row => {
        const rowDate = row.dataset.date;
        let dateMatch = true;
        if (df === 'today')    dateMatch = rowDate === TODAY;
        if (df === 'upcoming') dateMatch = rowDate > TODAY;
        if (df === 'past')     dateMatch = rowDate < TODAY;
        const show = (!q || row.dataset.name.includes(q) || row.dataset.email.includes(q) || row.dataset.phone.includes(q))
                  && (!st || row.dataset.status === st)
                  && dateMatch;
        row.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.getElementById('resCount').textContent = n + ' total';
}

const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => { flash.style.transition = 'opacity .5s'; flash.style.opacity = '0'; }, 5000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>