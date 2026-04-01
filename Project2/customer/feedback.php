<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$customer_id = $_SESSION['customer_id'];

// ── POST ACTIONS with PRG ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? 'add';
    $msg = ''; $msg_type = 'success';

    // ── Add Feedback ──
    if ($action === 'add') {
        $rating   = max(1, min(5, intval($_POST['rating'] ?? 5)));
        $message  = trim($_POST['message'] ?? '');
        $order_id = trim($_POST['order_id'] ?? '');

        if (!$message) {
            $msg = '⚠️ Please write your feedback.'; $msg_type = 'warning';
        } else {
            $pdo->prepare("INSERT INTO feedback (customer_id, name, rating, message, order_id) VALUES (?,?,?,?,?)")
                ->execute([$customer_id, $_SESSION['customer_name'], $rating, $message, $order_id ?: null]);
            $msg = '✅ Thank you for your feedback! It means a lot to us. 🙏';
        }
    }

    // ── Edit Feedback ──
    if ($action === 'edit') {
        $fb_id   = intval($_POST['fb_id']);
        $rating  = max(1, min(5, intval($_POST['rating'] ?? 5)));
        $message = trim($_POST['message'] ?? '');

        // Verify ownership
        $check = $pdo->prepare("SELECT id FROM feedback WHERE id=? AND customer_id=?");
        $check->execute([$fb_id, $customer_id]);
        if ($check->fetch() && $message) {
            $pdo->prepare("UPDATE feedback SET rating=?, message=? WHERE id=? AND customer_id=?")
                ->execute([$rating, $message, $fb_id, $customer_id]);
            $msg = '✅ Your feedback has been updated!';
        } else {
            $msg = '❌ Could not update feedback.'; $msg_type = 'danger';
        }
    }

    // ── Delete Feedback ──
    if ($action === 'delete') {
        $fb_id = intval($_POST['fb_id']);
        $check = $pdo->prepare("SELECT id FROM feedback WHERE id=? AND customer_id=?");
        $check->execute([$fb_id, $customer_id]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM feedback WHERE id=? AND customer_id=?")->execute([$fb_id, $customer_id]);
            $msg = '✅ Your feedback has been deleted.';
        } else {
            $msg = '❌ Could not delete feedback.'; $msg_type = 'danger';
        }
    }

    // PRG redirect
    $_SESSION['cust_flash']      = $msg;
    $_SESSION['cust_flash_type'] = $msg_type;
    header('Location: feedback.php');
    exit;
}

// ── Read flash ──
$msg      = $_SESSION['cust_flash']      ?? '';
$msg_type = $_SESSION['cust_flash_type'] ?? 'success';
unset($_SESSION['cust_flash'], $_SESSION['cust_flash_type']);

// ── Auto-add order_id column if missing ──
try { $pdo->query("SELECT order_id FROM feedback LIMIT 1"); }
catch (PDOException $e) {
    $pdo->exec("ALTER TABLE feedback ADD COLUMN order_id VARCHAR(50) DEFAULT NULL AFTER message");
}

// Fetch customer's delivered orders for dropdown
$my_orders = $pdo->prepare("SELECT id, date, total FROM orders WHERE customer_email=? AND status='Delivered' ORDER BY id DESC");
$my_orders->execute([$_SESSION['customer_email']]);
$my_orders = $my_orders->fetchAll();

// Fetch all public feedback
$all_feedback = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Fetch customer's own feedback
$my_feedback = $pdo->prepare("SELECT * FROM feedback WHERE customer_id=? ORDER BY created_at DESC");
$my_feedback->execute([$customer_id]);
$my_feedback = $my_feedback->fetchAll();
$my_feedback_ids = array_column($my_feedback, 'id');

$page_title = 'Feedback';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash flash-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Feedback</h1>
    <p>Share your dining experience with us.</p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start">

    <!-- SUBMIT FEEDBACK -->
    <div class="card" style="margin-bottom:0">
        <div class="card-title">✍️ Write a Review</div>

        <!-- Star Rating -->
        <div style="background:linear-gradient(135deg,var(--cream),#fff8f0);border-radius:16px;padding:20px;margin-bottom:20px;text-align:center;border:1px solid var(--border)">
            <div style="font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--brown-3);margin-bottom:10px">Your Rating</div>
            <div class="star-selector" style="display:flex;gap:4px;justify-content:center" id="starRow">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <label id="starLabel<?= $i ?>" style="font-size:2.2rem;cursor:pointer;transition:transform .15s;display:inline-block">
                    <input type="radio" name="rating" value="<?= $i ?>" form="feedbackForm" style="display:none" <?= $i===5?'checked':'' ?>>
                    ⭐
                </label>
                <?php endfor; ?>
            </div>
            <div style="font-size:.8rem;color:var(--brown-3);margin-top:8px" id="ratingText">5 out of 5 — Excellent! 🔥</div>
        </div>

        <form method="POST" id="feedbackForm">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="rating" id="ratingInput" value="5">

            <!-- Order Selection -->
            <div class="form-group">
                <label class="form-label">Link to an Order <span style="color:var(--brown-3);font-weight:400;text-transform:none;letter-spacing:0;font-size:.72rem"> — optional</span></label>
                <select name="order_id" class="form-input">
                    <option value="">— General feedback (no specific order) —</option>
                    <?php foreach ($my_orders as $ord): ?>
                    <option value="<?= htmlspecialchars($ord['id']) ?>">
                        Order #<?= htmlspecialchars($ord['id']) ?> — <?= htmlspecialchars($ord['date']) ?> — Rs. <?= number_format($ord['total'], 0) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($my_orders)): ?>
                <div style="font-size:.74rem;color:var(--brown-3);margin-top:4px">⚠️ No delivered orders yet — you can still leave general feedback.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Your Feedback *</label>
                <textarea name="message" class="form-input" rows="5"
                          placeholder="Tell us about your experience — the food, service, ambiance…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Submit Feedback 🙏</button>
        </form>
    </div>

    <!-- REVIEWS -->
    <div>
        <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;margin-bottom:16px;color:var(--brown)">
            What Others <span style="background:linear-gradient(135deg,var(--fire),var(--amber));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">Say</span>
        </div>

        <?php if (empty($all_feedback)): ?>
        <div style="text-align:center;padding:48px;background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border)">
            <div style="font-size:2.5rem;margin-bottom:10px">💬</div>
            <p style="color:var(--brown-3)">Be the first to leave a review!</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;max-height:600px;overflow-y:auto;padding-right:4px">
        <?php foreach ($all_feedback as $fb):
            $is_mine = in_array($fb['id'], $my_feedback_ids);
        ?>
        <div class="review-card" id="review-<?= $fb['id'] ?>">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--fire),var(--amber));display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff">
                        <?= strtoupper(substr($fb['name'],0,1)) ?>
                    </div>
                    <div>
                        <strong style="font-size:.88rem;color:var(--brown)"><?= htmlspecialchars($fb['name']) ?></strong>
                        <?php if ($fb['order_id']): ?>
                        <div style="font-size:.7rem;color:var(--brown-3)">Order #<?= htmlspecialchars($fb['order_id']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:.82rem"><?= str_repeat('⭐', $fb['rating']) ?></span>
                    <span style="color:var(--brown-3);font-size:.72rem"><?= date('M d', strtotime($fb['created_at'])) ?></span>
                    <?php if ($is_mine): ?>
                    <button onclick="openEditModal(<?= $fb['id'] ?>, <?= $fb['rating'] ?>, <?= htmlspecialchars(json_encode($fb['message'])) ?>)"
                            class="btn btn-ghost btn-sm" style="padding:4px 10px;font-size:.72rem" title="Edit">✏️</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this feedback?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="fb_id" value="<?= $fb['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 10px;font-size:.72rem" title="Delete">🗑️</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <p style="color:var(--brown-2);font-size:.85rem;line-height:1.65;margin:0"><?= htmlspecialchars($fb['message']) ?></p>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Your Review</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="fb_id" id="edit_fb_id">
                <input type="hidden" name="rating" id="edit_rating_input" value="5">

                <!-- Edit Star Rating -->
                <div style="background:linear-gradient(135deg,var(--cream),#fff8f0);border-radius:14px;padding:16px;margin-bottom:18px;text-align:center;border:1px solid var(--border)">
                    <div style="font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--brown-3);margin-bottom:8px">Update Rating</div>
                    <div style="display:flex;gap:4px;justify-content:center" id="editStarRow">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label style="font-size:2rem;cursor:pointer;transition:transform .15s;display:inline-block" id="editStar<?= $i ?>">⭐</label>
                        <?php endfor; ?>
                    </div>
                    <div style="font-size:.78rem;color:var(--brown-3);margin-top:6px" id="editRatingText">5 out of 5</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Your Feedback *</label>
                    <textarea name="message" id="edit_message" class="form-input" rows="5" required></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Star rating (Add form) ──
const labels      = document.querySelectorAll('#starRow label');
const ratingInput = document.getElementById('ratingInput');
const ratingText  = document.getElementById('ratingText');
const texts = ['','Terrible 😞','Poor 😕','Okay 😐','Good 😊','Excellent! 🔥'];
let currentRating = 5;

function highlightStars(row, count) {
    row.forEach((lbl, i) => {
        lbl.style.filter    = i < count ? 'none' : 'grayscale(1) opacity(.35)';
        lbl.style.transform = 'scale(1)';
    });
}
highlightStars(labels, 5);

labels.forEach((label, idx) => {
    label.addEventListener('click', () => {
        currentRating     = idx + 1;
        ratingInput.value = currentRating;
        ratingText.textContent = currentRating + ' out of 5 — ' + texts[currentRating];
        highlightStars(labels, currentRating);
        label.style.transform = 'scale(1.25)';
        setTimeout(() => label.style.transform = 'scale(1)', 200);
    });
    label.addEventListener('mouseenter', () => { highlightStars(labels, idx + 1); label.style.transform = 'scale(1.2)'; });
    label.addEventListener('mouseleave', () => { highlightStars(labels, currentRating); });
});

// ── Edit Star Rating ──
const editLabels      = document.querySelectorAll('#editStarRow label');
const editRatingInput = document.getElementById('edit_rating_input');
const editRatingText  = document.getElementById('editRatingText');
let editCurrentRating = 5;

editLabels.forEach((label, idx) => {
    label.addEventListener('click', () => {
        editCurrentRating     = idx + 1;
        editRatingInput.value = editCurrentRating;
        editRatingText.textContent = editCurrentRating + ' out of 5 — ' + texts[editCurrentRating];
        highlightStars(editLabels, editCurrentRating);
        label.style.transform = 'scale(1.25)';
        setTimeout(() => label.style.transform = 'scale(1)', 200);
    });
    label.addEventListener('mouseenter', () => { highlightStars(editLabels, idx + 1); label.style.transform = 'scale(1.2)'; });
    label.addEventListener('mouseleave', () => { highlightStars(editLabels, editCurrentRating); });
});

// ── Open Edit Modal ──
function openEditModal(id, rating, message) {
    document.getElementById('edit_fb_id').value    = id;
    document.getElementById('edit_message').value  = message;
    document.getElementById('edit_rating_input').value = rating;
    editCurrentRating = rating;
    highlightStars(editLabels, rating);
    editRatingText.textContent = rating + ' out of 5 — ' + texts[rating];
    openModal('editModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>