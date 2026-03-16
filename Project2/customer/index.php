<?php
// ============================================================
//  HOME — Minmi Restaurent Customer Website
//  Place in: customer/index.php
// ============================================================
require_once __DIR__ . '/includes/db.php';

$is_logged_in = isset($_SESSION['customer_id']);
$welcome      = isset($_GET['welcome']);

$featured   = $pdo->query("SELECT * FROM menu_items WHERE status IN ('Available','Seasonal') ORDER BY RAND() LIMIT 6")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM menu_items WHERE status IN ('Available','Seasonal') ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$_doc_root    = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']), '/');
$_script_file = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$_web_path    = str_replace($_doc_root, '', $_script_file);
$_project_dir = substr($_web_path, 0, strpos($_web_path, '/customer/'));
$img_base_url = $_project_dir . '/dashboard/';

$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($welcome): ?>
<div class="flash flash-success" id="welcomeFlash" style="margin-bottom:24px">
    🎉 Welcome to Minmi Restaurent! Browse our menu and place your first order.
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:inherit">✕</button>
</div>
<?php endif; ?>

<!-- HERO -->
<div class="hero">
    <div class="hero-dots"></div>
    <div class="hero-content">
        <div class="hero-tag"> Authentic Sri Lankan Cuisine</div>
        <h1>Fire-Crafted<br><em>Flavours</em>,<br>Every Night</h1>
          <p>From rice and curry to kottu and hoppers — experience the true taste of Sri Lanka, 
            crafted with passion and served with love.
          </p>
        <div class="hero-btns">
            <a href="menu.php" class="btn btn-amber" style="font-size:1rem;padding:14px 32px">🍽️ Order Now</a>
            <a href="reservations.php" class="btn" style="font-size:1rem;padding:14px 32px;background:rgba(255,255,255,.1);color:#fff;border:2px solid rgba(255,255,255,.25);backdrop-filter:blur(8px)">📅 Book a Table</a>
        </div>
    </div>
    <div class="hero-float">
        <div class="float-emoji">🍛</div>
        <div class="float-emoji">🥘</div>
        <div class="float-emoji">🍜</div>
        <div class="float-emoji">🌶️</div>
    </div>
    <div class="hero-stats">
        <div class="hero-stat"><strong>30+</strong><span>Menu Items</span></div>
        <div class="hero-stat"><strong>4★+</strong><span>Rating</span></div>
        <div class="hero-stat"><strong>Fast</strong><span>Delivery</span></div>
    </div>
</div>

<!-- CATEGORY PILLS -->
<?php if (!empty($categories)): ?>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:40px;align-items:center">
    <span style="font-size:.8rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--brown-3);margin-right:4px">Browse:</span>
    <a href="menu.php" class="cat-pill active">✨ All Items</a>
    <?php
    $cat_icons = ['Rice'=>'🍚','Curry'=>'🍛','Kottu'=>'🥘','Hoppers'=>'🫓','Drinks'=>'🥤','Desserts'=>'🍮','Seafood'=>'🦐','Chicken'=>'🍗','Vegetarian'=>'🥗','Soup'=>'🍲'];
    foreach ($categories as $cat):
        $icon = $cat_icons[$cat] ?? '🍽️';
    ?>
    <a href="menu.php?category=<?= urlencode($cat) ?>" class="cat-pill">
        <?= $icon ?> <?= htmlspecialchars($cat) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- PROMO BANNER -->
<?php if ($is_logged_in): ?>
<div class="promo-banner">
    <div class="promo-text">
        <h3>🎉 Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['customer_name'])[0]) ?>!</h3>
        <p>Your favourite Sri Lankan flavours are just a click away. What are you craving today?</p>
    </div>
    <div class="promo-btns">
        <a href="menu.php" class="btn" style="background:#fff;color:var(--fire);font-weight:800">Browse Menu →</a>
        <a href="orders.php" class="btn" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3)">My Orders</a>
    </div>
</div>
<?php else: ?>
<div class="promo-banner">
    <div class="promo-text">
        <h3>🍽️ Ready to order?</h3>
        <p>Create a free account to place orders, book tables, and track your food in real time.</p>
    </div>
    <div class="promo-btns">
        <a href="register.php" class="btn" style="background:#fff;color:var(--fire);font-weight:800">Register Free →</a>
        <a href="login.php" class="btn" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3)">Login</a>
    </div>
</div>
<?php endif; ?>

<!-- FEATURED MENU -->
<?php if (!empty($featured)): ?>
<div style="margin-bottom:44px">
    <div class="sec-header">
        <div class="sec-title">Today's <span>Picks</span></div>
        <a href="menu.php" class="btn btn-ghost btn-sm">View Full Menu →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
        <?php foreach ($featured as $item): ?>
        <div class="menu-card">
            <div class="menu-card-img">
                <?php if ($item['image']): ?>
                <img src="<?= $img_base_url . htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                <div class="no-img">🍽️</div>
                <?php endif; ?>
                <?php if ($item['status'] === 'Seasonal'): ?>
                <div class="menu-card-badge">🌿 Seasonal</div>
                <?php endif; ?>
            </div>
            <div class="menu-card-body">
                <div class="menu-card-name"><?= htmlspecialchars($item['name']) ?></div>
                <?php if ($item['description']): ?>
                <div class="menu-card-desc"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 65, '…')) ?></div>
                <?php endif; ?>
                <div class="menu-card-footer">
                    <div class="menu-card-price">Rs. <?= number_format($item['price'], 0) ?></div>
                    <?php if ($is_logged_in): ?>
                    <a href="menu.php?add=<?= urlencode($item['id']) ?>" class="btn btn-primary btn-sm">+ Add</a>
                    <?php else: ?>
                    <a href="login.php" class="btn btn-ghost btn-sm">Login to Order</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- WHY CHOOSE US -->
<div style="margin-bottom:20px">
    <div class="sec-header">
        <div class="sec-title">Why Choose <span>Minmi?</span></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:50px">
        <?php foreach([
            ['🍛','#FFF3E0','Authentic Recipes',  'Traditional Sri Lankan dishes made with fresh local ingredients.'],
            ['⚡','#FFF8E1','Lightning Fast',       'Quick preparation without compromising on taste or quality.'],
            ['📱','#F3E5F5','Order Online',         'Place orders from your phone and track them in real time.'],
            ['🪑','#E8F5E9','Book a Table',         'Reserve your table in advance for a seamless dine-in experience.'],
        ] as [$icon, $bg, $title, $desc]): ?>
        <div class="why-card">
            <div class="why-icon" style="background:<?= $bg ?>"><?= $icon ?></div>
            <div style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:8px;color:var(--brown)"><?= $title ?></div>
            <div style="color:var(--brown-3);font-size:.82rem;line-height:1.65"><?= $desc ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
const wf = document.getElementById('welcomeFlash');
if (wf) setTimeout(() => { wf.style.transition='opacity .6s'; wf.style.opacity='0'; setTimeout(()=>wf.remove(),600); }, 5000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>