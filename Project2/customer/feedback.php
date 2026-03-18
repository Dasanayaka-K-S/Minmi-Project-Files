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
    $rating  = intval($_POST['rating'] ?? 5);
    $message = trim($_POST['message'] ?? '');
    $rating  = max(1, min(5, $rating));
    if (!$message) {
        $msg = '⚠️ Please write your feedback.'; $msg_type = 'warning';
    } else {
        $pdo->prepare("INSERT INTO feedback (customer_id, name, rating, message) VALUES (?,?,?,?)")
            ->execute([$customer_id, $_SESSION['customer_name'], $rating, $message]);
        header('Location: feedback.php?sent=1');
        exit;
    }
}

$all_feedback = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC LIMIT 20")->fetchAll();

if (isset($_GET['sent'])) {
    $msg = '✅ Thank you for your feedback! It means a lot to us. 🙏';
    $msg_type = 'success';
}

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

        <!-- Star rating display -->
        <div style="background:linear-gradient(135deg,var(--cream),#fff8f0);border-radius:16px;padding:20px;margin-bottom:20px;text-align:center;border:1px solid var(--border)">
            <div style="font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--brown-3);margin-bottom:10px">Your Rating</div>
            <div class="star-selector" style="display:flex;gap:4px;justify-content:center" id="starRow">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <label id="starLabel<?= $i ?>" style="font-size:2.2rem;cursor:pointer;transition:transform .15s;display:inline-block;opacity:<?= $i<=5?'1':'.4' ?>">
                    <input type="radio" name="rating" value="<?= $i ?>" form="feedbackForm" style="display:none" <?= $i===5?'checked':'' ?>>
                    ⭐
                </label>
                <?php endfor; ?>
            </div>
            <div style="font-size:.8rem;color:var(--brown-3);margin-top:8px" id="ratingText">5 out of 5 stars</div>
        </div>

        <form method="POST" id="feedbackForm">
            <input type="hidden" name="rating" id="ratingInput" value="5">
            <div class="form-group">
                <label class="form-label">Your Feedback *</label>
                <textarea name="message" class="form-input" rows="5"
                          placeholder="Tell us about your experience — the food, service, ambiance…" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Submit Feedback 🙏</button>
        </form>
    </div>

    <!-- RECENT REVIEWS -->
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
        <div style="display:flex;flex-direction:column;gap:12px;max-height:520px;overflow-y:auto;padding-right:4px">
        <?php foreach ($all_feedback as $fb): ?>
        <div class="review-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--fire),var(--amber));display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff">
                        <?= strtoupper(substr($fb['name'],0,1)) ?>
                    </div>
                    <strong style="font-size:.88rem;color:var(--brown)"><?= htmlspecialchars($fb['name']) ?></strong>
                </div>
                <div style="display:flex;align-items:center;gap:6px">
                    <span style="font-size:.82rem"><?= str_repeat('⭐', $fb['rating']) ?></span>
                    <span style="color:var(--brown-3);font-size:.72rem"><?= date('M d', strtotime($fb['created_at'])) ?></span>
                </div>
            </div>
            <p style="color:var(--brown-2);font-size:.85rem;line-height:1.65;margin:0"><?= htmlspecialchars($fb['message']) ?></p>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
// Remove ?sent=1 from URL so refresh doesn't re-trigger message
if (window.location.search.includes('sent=1')) {
    window.history.replaceState({}, '', 'feedback.php');
}

// Prevent form resubmission on back/refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Star rating interactivity
const labels     = document.querySelectorAll('#starRow label');
const ratingInput = document.getElementById('ratingInput');
const ratingText  = document.getElementById('ratingText');
const texts = ['','Terrible 😞','Poor 😕','Okay 😐','Good 😊','Excellent! 🔥'];

let currentRating = 5;

function highlightStars(count) {
    labels.forEach((lbl, i) => {
        lbl.style.filter    = i < count ? 'none' : 'grayscale(1) opacity(.35)';
        lbl.style.transform = 'scale(1)';
    });
}

// Init — show 5 stars selected
highlightStars(5);

labels.forEach((label, idx) => {
    // Click — set rating
    label.addEventListener('click', () => {
        currentRating      = idx + 1;
        ratingInput.value  = currentRating;
        ratingText.textContent = currentRating + ' out of 5 — ' + texts[currentRating];
        highlightStars(currentRating);
        label.style.transform = 'scale(1.25)';
        setTimeout(() => label.style.transform = 'scale(1)', 200);
    });

    // Hover — preview
    label.addEventListener('mouseenter', () => {
        highlightStars(idx + 1);
        label.style.transform = 'scale(1.2)';
    });

    // Mouse leave — restore selected rating
    label.addEventListener('mouseleave', () => {
        highlightStars(currentRating);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>