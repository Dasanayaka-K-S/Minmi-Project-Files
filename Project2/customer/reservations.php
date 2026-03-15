<?php
// ============================================================
//  RESERVATIONS — Minmi Restaurent Customer Website
//  Place in: customer/reservations.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$customer_id = $_SESSION['customer_id'];
$msg = ''; $msg_type = 'success';

// ── Add Reservation ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
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

        // Send confirmation email
        $mailer_path = __DIR__ . '/../dashboard/includes/mailer.php';
        if (file_exists($mailer_path)) {
            require_once $mailer_path;
            $body = "Your table reservation at Minmi Restaurent has been received and is currently <strong>Pending</strong>.

<div style='background:#fff8f5;border-left:4px solid #e8622a;border-radius:0 8px 8px 0;padding:18px 20px;margin:20px 0'>
    <table width='100%' cellpadding='6' cellspacing='0' style='font-size:.9rem;color:#333'>
        <tr><td style='color:#888;width:40%'>Reservation ID</td><td><strong>{$new_id}</strong></td></tr>
        <tr><td style='color:#888'>Date</td><td><strong>" . date('l, d F Y', strtotime($date)) . "</strong></td></tr>
        <tr><td style='color:#888'>Time</td><td><strong>" . date('g:i A', strtotime($time)) . "</strong></td></tr>
        <tr><td style='color:#888'>Status</td><td><span style='color:#f5c842;font-weight:700'>Pending</span></td></tr>
        " . ($phone ? "<tr><td style='color:#888'>Phone</td><td>{$phone}</td></tr>" : "") . "
        " . ($notes ? "<tr><td style='color:#888'>Notes</td><td>" . htmlspecialchars($notes) . "</td></tr>" : "") . "
    </table>
</div>
Our team will confirm your booking shortly. We look forward to welcoming you! 🔥";
            sendMail($_SESSION['customer_email'], $_SESSION['customer_name'],
                '📅 Reservation Confirmation — Minmi Restaurent', $body);
        }

        $msg = '✅ Reservation booked! ID: ' . $new_id . '. We will confirm it shortly.';
    }
}

// ── Cancel Reservation ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
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
    <button class="btn btn-primary" onclick="document.getElementById('bookModal').classList.add('open')">＋ Book a Table</button>
</div>

<!-- RESERVATIONS LIST -->
<?php if (empty($reservations)): ?>
<div style="text-align:center;padding:60px;background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border)">
    <div style="font-size:3.5rem;margin-bottom:14px">📅</div>
    <h3 style="font-family:'DM Serif Display',serif;font-size:1.3rem;font-weight:400;margin-bottom:8px">No reservations yet</h3>
    <p style="color:var(--text-3);margin-bottom:20px">Book a table for a wonderful dining experience!</p>
    <button class="btn btn-primary" onclick="document.getElementById('bookModal').classList.add('open')">＋ Book a Table</button>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach ($reservations as $r):
    $is_future   = $r['date'] >= $today;
    $can_cancel  = $r['status'] === 'Pending';
    $date_label  = $r['date'] === $today ? '📅 Today' : date('l, d F Y', strtotime($r['date']));
?>
<div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div style="display:flex;align-items:center;gap:16px">
        <div style="width:52px;height:52px;border-radius:12px;background:<?= $is_future ? 'rgba(232,98,42,.1)' : 'var(--bg-3)' ?>;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
            <?= $r['status'] === 'Completed' ? '✅' : ($r['status'] === 'Cancelled' ? '❌' : '🪑') ?>
        </div>
        <div>
            <div style="font-weight:700;font-size:.95rem;margin-bottom:2px"><?= $date_label ?></div>
            <div style="color:var(--text-3);font-size:.82rem">🕐 <?= date('g:i A', strtotime($r['time'])) ?> &nbsp;·&nbsp; ID: <?= htmlspecialchars($r['id']) ?></div>
            <?php if ($r['notes']): ?>
            <div style="color:var(--text-3);font-size:.76rem;font-style:italic;margin-top:3px">"<?= htmlspecialchars(mb_strimwidth($r['notes'],0,50,'…')) ?>"</div>
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
            <button class="modal-close" onclick="document.getElementById('bookModal').classList.remove('open')">✕</button>
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
                <div style="background:rgba(62,207,142,.07);border:1px solid rgba(62,207,142,.25);border-radius:var(--radius);padding:10px 14px;margin-bottom:16px;font-size:.78rem;color:#1a7a4a">
                    📧 A confirmation email will be sent to <?= htmlspecialchars($_SESSION['customer_email']) ?>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('bookModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn btn-primary">📅 Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('bookModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
