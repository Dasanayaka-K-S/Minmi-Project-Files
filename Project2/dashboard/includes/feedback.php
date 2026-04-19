<?php
// ============================================================
//  FEEDBACK — Minmi Restaurent Admin
//  Place in: dashboard/pages/feedback.php
//  Accessible by: admin & cashier
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// ── PRG: Handle delete (admin only) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $item_id = $_POST['item_id'] ?? '';
    $msg = ''; $msg_type = 'success';

    if ($action === 'delete' && $item_id && $_SESSION['user_role'] === 'admin') {
        $pdo->prepare("DELETE FROM feedback WHERE id=?")->execute([$item_id]);
        $msg = 'Feedback deleted.'; $msg_type = 'danger';
    }

    $_SESSION['admin_flash']      = $msg;
    $_SESSION['admin_flash_type'] = $msg_type;
    header('Location: feedback.php');
    exit;
}

$msg      = $_SESSION['admin_flash']      ?? '';
$msg_type = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);

// Auto-add order_id column
try { $pdo->query("SELECT order_id FROM feedback LIMIT 1"); }
catch (PDOException $e) {
    $pdo->exec("ALTER TABLE feedback ADD COLUMN order_id VARCHAR(50) DEFAULT NULL AFTER message");
}

$feedback = $pdo->query("SELECT f.*, c.email as customer_email
    FROM feedback f
    LEFT JOIN customers c ON f.customer_id = c.id
    ORDER BY f.created_at DESC")->fetchAll();

$total      = count($feedback);
$avg_rating = $total > 0 ? round(array_sum(array_column($feedback,'rating')) / $total, 1) : 0;
$cnt_5      = count(array_filter($feedback, fn($f) => $f['rating']==5));
$cnt_4      = count(array_filter($feedback, fn($f) => $f['rating']==4));
$cnt_3      = count(array_filter($feedback, fn($f) => $f['rating']==3));
$cnt_2      = count(array_filter($feedback, fn($f) => $f['rating']==2));
$cnt_1      = count(array_filter($feedback, fn($f) => $f['rating']==1));
$cnt_low    = $cnt_2 + $cnt_1;

$page_title = 'Feedback';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-msg flash-<?= $msg_type ?>" id="flashMsg">
    <?= $msg ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:12px">x</button>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Customer Feedback</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">View and manage customer reviews and ratings.</p>
    </div>
    <?php if ($avg_rating > 0): ?>
    <div style="background:linear-gradient(135deg,var(--accent),#ff9500);color:#fff;padding:10px 24px;border-radius:var(--radius-lg);font-weight:800;font-size:1rem;box-shadow:0 4px 14px rgba(232,98,42,.35)">
        Average: <?= $avg_rating ?> / 5.0
    </div>
    <?php endif; ?>
</div>

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card blue"><div class="stat-icon">Total</div><div class="stat-label">Reviews</div><div class="stat-value"><?= $total ?></div><div class="stat-sub">All feedback</div></div>
    <div class="stat-card green"><div class="stat-icon">Avg</div><div class="stat-label">Rating</div><div class="stat-value"><?= $avg_rating ?></div><div class="stat-sub">Out of 5.0</div></div>
    <div class="stat-card yellow"><div class="stat-icon">5</div><div class="stat-label">Five Star</div><div class="stat-value"><?= $cnt_5 ?></div><div class="stat-sub">Excellent</div></div>
    <div class="stat-card orange"><div class="stat-icon">4</div><div class="stat-label">Four Star</div><div class="stat-value"><?= $cnt_4 ?></div><div class="stat-sub">Good reviews</div></div>
    <div class="stat-card red"><div class="stat-icon">Low</div><div class="stat-label">Low Ratings</div><div class="stat-value"><?= $cnt_low ?></div><div class="stat-sub">Needs attention</div></div>
</div>

<!-- RATING DISTRIBUTION -->
<?php if ($total > 0): ?>
<div class="card">
    <div class="card-header">
        <div class="card-title">Rating Distribution</div>
        <span class="badge badge-gray"><?= $total ?> reviews</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:12px">
        <?php
        $dist = [5=>['#3ecf8e',$cnt_5], 4=>['#4e9cf7',$cnt_4], 3=>['#f5c842',$cnt_3], 2=>['#e8622a',$cnt_2], 1=>['#e84242',$cnt_1]];
        foreach ($dist as $stars => [$color, $count]):
            $pct = $total > 0 ? round(($count / $total) * 100) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:50px;font-size:.8rem;font-weight:700;color:var(--text-2);text-align:right;flex-shrink:0"><?= $stars ?> star</div>
            <div style="flex:1;background:var(--bg-3);border-radius:50px;height:10px;overflow:hidden">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:50px"></div>
            </div>
            <div style="width:60px;font-size:.78rem;color:var(--text-3);flex-shrink:0"><?= $count ?> (<?= $pct ?>%)</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ALL FEEDBACK -->
<div class="card">
    <div class="card-header">
        <div class="card-title">All Reviews</div>
        <span class="badge badge-gray" id="fbCount"><?= $total ?> total</span>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="text" id="searchFb" class="search-input"
               placeholder="Search name or message..."
               oninput="filterFb()" style="flex:1;min-width:200px">
        <select id="filterRating" class="search-input" style="min-width:150px" onchange="filterFb()">
            <option value="">All Ratings</option>
            <option value="5">5 Stars</option>
            <option value="4">4 Stars</option>
            <option value="3">3 Stars</option>
            <option value="2">2 Stars</option>
            <option value="1">1 Star</option>
        </select>
    </div>

    <?php if (empty($feedback)): ?>
    <div style="text-align:center;padding:60px;color:var(--text-3)">
        <div style="font-size:3rem;margin-bottom:12px">No reviews yet.</div>
        <p>Customer reviews submitted from the website will appear here.</p>
    </div>
    <?php else: ?>
    <div id="fbList" style="display:flex;flex-direction:column;gap:14px">
        <?php foreach ($feedback as $fb):
            $rating_color = $fb['rating']>=4 ? '#3ecf8e' : ($fb['rating']==3 ? '#f5c842' : '#e84242');
            $initial = strtoupper(substr($fb['name'],0,1));
        ?>
        <div class="fb-card"
             data-name="<?= strtolower(htmlspecialchars($fb['name'])) ?>"
             data-msg="<?= strtolower(htmlspecialchars($fb['message'])) ?>"
             data-rating="<?= $fb['rating'] ?>">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
                <div style="display:flex;align-items:flex-start;gap:14px;flex:1;min-width:0">
                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#ff9500);display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:800;color:#fff;flex-shrink:0">
                        <?= $initial ?>
                    </div>
                    <div style="min-width:0;flex:1">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
                            <strong style="font-size:.92rem"><?= htmlspecialchars($fb['name']) ?></strong>
                            <span style="background:<?= $rating_color ?>22;color:<?= $rating_color ?>;border:1px solid <?= $rating_color ?>55;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:800">
                                <?= str_repeat('*', $fb['rating']) ?> <?= $fb['rating'] ?>/5
                            </span>
                            <?php if ($fb['order_id']): ?>
                            <span class="badge badge-gray" style="font-size:.68rem">Order #<?= htmlspecialchars($fb['order_id']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="color:var(--text-3);font-size:.76rem;margin-bottom:8px">
                            <?= htmlspecialchars($fb['customer_email'] ?? 'No email') ?>
                            &nbsp;|&nbsp;
                            <?= date('M d, Y g:i A', strtotime($fb['created_at'])) ?>
                        </div>
                        <p style="color:var(--text-2);font-size:.86rem;line-height:1.65;margin:0"><?= htmlspecialchars($fb['message']) ?></p>
                    </div>
                </div>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <div style="flex-shrink:0">
                    <button class="btn btn-danger btn-sm btn-icon btn-delete-fb"
                            data-id="<?= $fb['id'] ?>"
                            data-name="<?= htmlspecialchars($fb['name']) ?>"
                            title="Delete">X</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- DELETE MODAL -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-body" style="text-align:center;padding:32px 28px 24px">
            <div style="font-size:2.5rem;margin-bottom:12px">Delete?</div>
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">Delete Feedback?</h3>
            <p id="deleteMsg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <form method="POST" action="feedback.php">
                <input type="hidden" name="action"  value="delete">
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
.fb-card{background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;transition:all .2s}
.fb-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.08);border-color:var(--border-l)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-body{padding:18px 24px 24px}
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
</style>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(bd => bd.addEventListener('click', e => { if(e.target===bd) bd.classList.remove('open'); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-backdrop.open').forEach(bd=>bd.classList.remove('open')); });

document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    if (btn.classList.contains('btn-delete-fb')) {
        document.getElementById('delete_id').value = btn.dataset.id;
        document.getElementById('deleteMsg').textContent = 'This will permanently delete the review from "' + btn.dataset.name + '". Cannot be undone.';
        openModal('deleteModal');
    }
});

function filterFb() {
    const q  = document.getElementById('searchFb').value.toLowerCase();
    const rt = document.getElementById('filterRating').value;
    let n = 0;
    document.querySelectorAll('.fb-card').forEach(card => {
        const show = (!q || card.dataset.name.includes(q) || card.dataset.msg.includes(q)) && (!rt || card.dataset.rating === rt);
        card.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.getElementById('fbCount').textContent = n + ' total';
}

const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => { flash.style.transition='opacity .5s'; flash.style.opacity='0'; }, 5000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>