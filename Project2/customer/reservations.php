<?php
// ============================================================
//  RESERVATIONS — Minmi Restaurent Customer Website
//  Place in: customer/reservations.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$customer_id = $_SESSION['customer_id'];

// ── POST ACTIONS — PRG pattern to prevent resubmission on refresh ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $msg = ''; $msg_type = 'success';

    // ── Add Reservation ──
    if ($action === 'add') {
        $date  = $_POST['date']  ?? '';
        $time  = $_POST['time']  ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$date || !$time) {
            $msg = '⚠️ Please select a date and time.'; $msg_type = 'warning';
        } elseif ($date < date('Y-m-d')) {
            $msg = '⚠️ Please select a future date.'; $msg_type = 'warning';
        } else {
            $new_id = 'RES-' . strtoupper(substr(uniqid(), -6));
            $pdo->prepare("INSERT INTO reservations (id, customer_name, email, phone, date, time, status, notes)
                           VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)")
                ->execute([$new_id, $_SESSION['customer_name'], $_SESSION['customer_email'], $phone, $date, $time, $notes]);

            $msg = '✅ Reservation booked! ID: ' . $new_id . '. We will confirm it shortly.';
        }
    }

    // ── Cancel Reservation ──
    if ($action === 'cancel') {
        $res_id = $_POST['res_id'] ?? '';
        $check  = $pdo->prepare("SELECT * FROM reservations WHERE id=? AND email=?");
        $check->execute([$res_id, $_SESSION['customer_email']]);
        $res = $check->fetch();
        if ($res && $res['status'] === 'Pending') {
            $pdo->prepare("UPDATE reservations SET status='Cancelled' WHERE id=?")->execute([$res_id]);
            $msg = '✅ Reservation ' . $res_id . ' has been cancelled.';
        } else {
            $msg = '❌ Cannot cancel this reservation.'; $msg_type = 'danger';
        }
    }

    // ── PRG: Store in session then redirect to GET ──
    // Prevents reservation being re-booked on page refresh
    $_SESSION['cust_flash']      = $msg;
    $_SESSION['cust_flash_type'] = $msg_type;
    header('Location: reservations.php');
    exit;
}

// ── Read flash from session ──
$msg      = $_SESSION['cust_flash']      ?? '';
$msg_type = $_SESSION['cust_flash_type'] ?? 'success';
unset($_SESSION['cust_flash'], $_SESSION['cust_flash_type']);

// Fetch customer's reservations
$reservations = $pdo->prepare("SELECT * FROM reservations WHERE email=? ORDER BY date DESC, time DESC");
$reservations->execute([$_SESSION['customer_email']]);
$reservations = $reservations->fetchAll();

$today = date('Y-m-d');

function resBadge(string $s): string {
    $map = ['Pending'=>'badge-yellow','Confirmed'=>'badge-blue','Seated'=>'badge-orange','Completed'=>'badge-green','Cancelled'=>'badge-red'];
    return '<span class="badge ' . ($map[$s] ?? 'badge-gray') . '">' . htmlspecialchars($s) . '</span>';
}

$page_title = 'Reservations';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash flash-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <h1>Table Reservations</h1>
        <p>Book a table and manage your upcoming reservations.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('bookModal')">＋ Book a Table</button>
</div>

<!-- RESERVATIONS LIST -->
<?php if (empty($reservations)): ?>
<div style="text-align:center;padding:60px;background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border)">
    <div style="font-size:3.5rem;margin-bottom:14px">📅</div>
    <h3 style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:400;margin-bottom:8px">No reservations yet</h3>
    <p style="color:var(--brown-3);margin-bottom:20px">Book a table for a wonderful dining experience!</p>
    <button class="btn btn-primary" onclick="openModal('bookModal')">＋ Book a Table</button>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach ($reservations as $r):
    $is_future  = $r['date'] >= $today;
    $can_cancel = $r['status'] === 'Pending';
    $date_label = $r['date'] === $today ? '📅 Today' : date('l, d F Y', strtotime($r['date']));
    $icon = $r['status'] === 'Completed' ? '✅' : ($r['status'] === 'Cancelled' ? '❌' : ($r['status'] === 'Seated' ? '🪑' : ($r['status'] === 'Confirmed' ? '✔️' : '⏳')));
?>
<div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;transition:all .2s"
     onmouseover="this.style.boxShadow='0 8px 28px rgba(61,31,10,.1)';this.style.transform='translateY(-2px)'"
     onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,.05)';this.style.transform='none'">
    <div style="display:flex;align-items:center;gap:16px">
        <div style="width:52px;height:52px;border-radius:12px;background:<?= $is_future && $r['status'] !== 'Cancelled' ? 'rgba(255,69,0,.1)' : 'var(--cream)' ?>;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
            <?= $icon ?>
        </div>
        <div>
            <div style="font-weight:700;font-size:.95rem;margin-bottom:2px;color:var(--brown)"><?= $date_label ?></div>
            <div style="color:var(--brown-3);font-size:.82rem">🕐 <?= date('g:i A', strtotime($r['time'])) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($r['id']) ?></div>
            <?php if ($r['notes']): ?>
            <div style="color:var(--brown-3);font-size:.76rem;font-style:italic;margin-top:3px">"<?= htmlspecialchars(mb_strimwidth($r['notes'],0,50,'…')) ?>"</div>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
        <?= resBadge($r['status']) ?>
        <?php if ($can_cancel): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Cancel this reservation?')">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="res_id" value="<?= htmlspecialchars($r['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- BOOK TABLE MODAL -->
<div class="modal-backdrop" id="bookModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">📅 Book a Table</div>
            <button class="modal-close" onclick="closeModal('bookModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="date" class="form-input" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Time *</label>
                    <input type="time" name="time" class="form-input" value="19:00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-input" placeholder="+94 77 000 0000"
                           value="<?= htmlspecialchars($_SESSION['customer_phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Special Requests</label>
                    <textarea name="notes" class="form-input" rows="3" placeholder="e.g. Window table, birthday celebration, dietary requirements…"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('bookModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">📅 Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>