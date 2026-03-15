<?php
// ============================================================
//  FEEDBACK — Minmi Restaurent Customer Website
//  Place in: customer/feedback.php
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$customer_id = $_SESSION['customer_id'];
$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = intval($_POST['rating']  ?? 5);
    $message = trim($_POST['message']   ?? '');
    $rating  = max(1, min(5, $rating));

    if (!$message) {
        $msg = '⚠️ Please write your feedback.'; $msg_type = 'warning';
    } else {
        $pdo->prepare("INSERT INTO feedback (customer_id, name, rating, message) VALUES (?,?,?,?)")
            ->execute([$customer_id, $_SESSION['customer_name'], $rating, $message]);
        $msg = '✅ Thank you for your feedback! It means a lot to us. 🙏';
    }
}

// Fetch all feedback (public)
$all_feedback = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Customer's own feedback
$my_feedback = $pdo->prepare("SELECT * FROM feedback WHERE customer_id=? ORDER BY created_at DESC");
$my_feedback->execute([$customer_id]);
$my_feedback = $my_feedback->fetchAll();

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

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

    <!-- SUBMIT FEEDBACK -->
    <div class="card" style="margin-bottom:0">
        <div class="card-title">✍️ Write a Review</div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Your Rating</label>
                <div style="display:flex;gap:8px;margin-bottom:4px" id="starRow">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label style="cursor:pointer;font-size:1.8rem;transition:transform .1s" id="star<?= $i ?>">
                        <input type="radio" name="rating" value="<?= $i ?>" style="display:none" <?= $i===5?'checked':'' ?>>
                        ⭐
                    </label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Your Feedback *</label>
                <textarea name="message" class="form-input" rows="5"
                          placeholder="Tell us about your experience — the food, service, ambiance…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Submit Feedback 🙏</button>
        </form>
    </div>

    <!-- RECENT REVIEWS -->
    <div>
        <div style="font-family:'DM Serif Display',serif;font-size:1.15rem;margin-bottom:16px">What Others Say</div>
        <?php if (empty($all_feedback)): ?>
        <div style="text-align:center;padding:40px;background:#fff;border-radius:var(--radius-lg);border:1px solid var(--border);color:var(--text-3)">
            <div style="font-size:2rem;margin-bottom:8px">💬</div>
            <p>Be the first to leave a review!</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach ($all_feedback as $fb): ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.04)">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                <strong style="font-size:.88rem"><?= htmlspecialchars($fb['name']) ?></strong>
                <div style="display:flex;align-items:center;gap:4px">
                    <span style="font-size:.85rem"><?= str_repeat('⭐', $fb['rating']) ?></span>
                    <span style="color:var(--text-3);font-size:.72rem"><?= date('M d', strtotime($fb['created_at'])) ?></span>
                </div>
            </div>
            <p style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin:0"><?= htmlspecialchars($fb['message']) ?></p>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
