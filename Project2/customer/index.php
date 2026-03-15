<?php
// ============================================================
//  HOME — Minmi Restaurent Customer Website
//  Place in: customer/index.php
// ============================================================
require_once __DIR__ . '/includes/db.php';

$is_logged_in = isset($_SESSION['customer_id']);
$welcome      = isset($_GET['welcome']);

// Featured menu items (active items only, limit 6)
$featured = $pdo->query("SELECT * FROM menu_items WHERE status IN ('Available','Seasonal') ORDER BY RAND() LIMIT 6")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM menu_items WHERE status IN ('Available','Seasonal') ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// ── Image base URL ──
$_doc_root    = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']), '/');
$_script_file = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$_web_path    = str_replace($_doc_root, '', $_script_file);
$_project_dir = substr($_web_path, 0, strpos($_web_path, '/customer/'));
$img_base_url = $_project_dir . '/dashboard/';

$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($welcome): ?>
<div class="flash flash-success" id="welcomeFlash">
    🎉 Welcome to Minmi Restaurent! Browse our menu and place your first order.
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:1.1rem">✕</button>
</div>
<?php endif; ?>

<!-- HERO -->
<div style="background:linear-gradient(135deg,#1a1512 0%,#2d1f14 50%,#3d2a1a 100%);border-radius:20px;padding:56px 48px;margin-bottom:32px;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;background:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2280%22 cy=%2220%22 r=%2240%22 fill=%22rgba(232,98,42,.08)%22/><circle cx=%2220%22 cy=%2280%22 r=%2230%22 fill=%22rgba(232,98,42,.05)%22/></svg>');border-radius:20px"></div>
    <div style="position:relative;max-width:560px">
        <div style="font-size:.8rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#e8622a;margin-bottom:12px">🔥 Authentic Sri Lankan Cuisine</div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:2.8rem;font-weight:400;color:#fff;line-height:1.2;margin-bottom:16px;letter-spacing:-.02em">
            Fire-Crafted Flavours,<br>Every Night
        </h1>
        <p style="color:#a8998a;font-size:1rem;line-height:1.7;margin-bottom:28px">
            From rice and curry to kottu and hoppers — experience the true taste of Sri Lanka.
            Order online for delivery or book your table today.
        </p>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a href="menu.php" class="btn btn-primary" style="font-size:.95rem;padding:12px 28px">🍽️ Order Now</a>
            <a href="reservations.php" class="btn btn-ghost" style="font-size:.95rem;padding:12px 28px;color:#fff;border-color:rgba(255,255,255,.3)">📅 Book a Table</a>
        </div>
    </div>
</div>

<!-- QUICK CATEGORY PILLS -->
<?php if (!empty($categories)): ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px">
    <a href="menu.php" style="padding:8px 18px;border-radius:20px;background:#fff;border:1.5px solid var(--border);color:var(--text-2);text-decoration:none;font-size:.85rem;font-weight:600;transition:all .2s"
       onmouseover="this.style.borderColor='#e8622a';this.style.color='#e8622a'"
       onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-2)'">All Items</a>
    <?php foreach ($categories as $cat): ?>
    <a href="menu.php?category=<?= urlencode($cat) ?>"
       style="padding:8px 18px;border-radius:20px;background:#fff;border:1.5px solid var(--border);color:var(--text-2);text-decoration:none;font-size:.85rem;font-weight:600;transition:all .2s"
       onmouseover="this.style.borderColor='#e8622a';this.style.color='#e8622a'"
       onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-2)'">
        <?= htmlspecialchars($cat) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- FEATURED MENU -->
<?php if (!empty($featured)): ?>
<div style="margin-bottom:32px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
        <h2 style="font-family:'DM Serif Display',serif;font-size:1.6rem;font-weight:400">Today's Picks</h2>
        <a href="menu.php" style="color:var(--accent);text-decoration:none;font-size:.88rem;font-weight:600">View full menu →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px">
        <?php foreach ($featured as $item): ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);transition:transform .2s,box-shadow .2s"
             onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 28px rgba(0,0,0,.12)'"
             onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 12px rgba(0,0,0,.06)'">
            <?php if ($item['image']): ?>
            <div style="height:160px;overflow:hidden">
                <img src="<?= $img_base_url . htmlspecialchars($item['image']) ?>"
                     alt="<?= htmlspecialchars($item['name']) ?>"
                     style="width:100%;height:100%;object-fit:cover">
            </div>
            <?php else: ?>
            <div style="height:160px;background:linear-gradient(135deg,#f4f0ea,#ede5d8);display:flex;align-items:center;justify-content:center;font-size:3rem">🍽️</div>
            <?php endif; ?>
            <div style="padding:16px">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:8px;margin-bottom:6px">
                    <strong style="font-size:.95rem"><?= htmlspecialchars($item['name']) ?></strong>
                    <span style="color:var(--accent);font-weight:700;font-size:.95rem;white-space:nowrap">Rs. <?= number_format($item['price'], 0) ?></span>
                </div>
                <?php if ($item['description']): ?>
                <p style="color:var(--text-3);font-size:.8rem;line-height:1.5;margin-bottom:12px">
                    <?= htmlspecialchars(mb_strimwidth($item['description'], 0, 60, '…')) ?>
                </p>
                <?php endif; ?>
                <?php if ($is_logged_in): ?>
                <a href="cart.php?add=<?= $item['id'] ?>" class="btn btn-primary btn-sm" style="width:100%;justify-content:center">+ Add to Cart</a>
                <?php else: ?>
                <a href="login.php" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center">Login to Order</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- WHY CHOOSE US -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:32px">
    <?php foreach([
        ['🍛','Authentic Recipes','Traditional Sri Lankan dishes made with fresh local ingredients.'],
        ['⚡','Fast Service','Quick preparation without compromising on taste or quality.'],
        ['📱','Order Online','Place orders online and track them in real time.'],
        ['🪑','Book a Table','Reserve your table in advance for a seamless dining experience.'],
    ] as [$icon, $title, $desc]): ?>
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);padding:22px;text-align:center">
        <div style="font-size:2rem;margin-bottom:10px"><?= $icon ?></div>
        <div style="font-weight:700;font-size:.9rem;margin-bottom:6px"><?= $title ?></div>
        <div style="color:var(--text-3);font-size:.8rem;line-height:1.6"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
