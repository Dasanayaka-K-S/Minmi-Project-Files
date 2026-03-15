<?php
// ============================================================
//  MENU — Minmi Restaurent Customer Website
//  Place in: customer/menu.php
//  READ-ONLY view of menu_items — no add/edit/delete
// ============================================================
require_once __DIR__ . '/includes/db.php';

$is_logged_in = isset($_SESSION['customer_id']);

// ── Image base URL ──
// SCRIPT_FILENAME: C:/xampp/htdocs/Minmi-Project-Files - copy/Project2/customer/menu.php
// DOCUMENT_ROOT:   C:/xampp/htdocs
// So web path =    /Minmi-Project-Files - copy/Project2/dashboard/uploads/menu/
$_doc_root    = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']), '/');
$_script_file = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$_web_path    = str_replace($_doc_root, '', $_script_file);
$_project_dir = substr($_web_path, 0, strpos($_web_path, '/customer/'));
$img_base_url = $_project_dir . '/dashboard/';

// Handle add to cart
if ($is_logged_in && isset($_GET['add'])) {
    // Use raw value — supports both integer and string IDs
    $add_id_raw = trim($_GET['add']);
    $add_id     = is_numeric($add_id_raw) ? intval($add_id_raw) : $add_id_raw;

    $item = $pdo->prepare("SELECT * FROM menu_items WHERE id=? AND status IN ('Available','Seasonal')");
    $item->execute([$add_id]);
    $menu_item = $item->fetch();

    if ($menu_item) {
        $cart_key = $menu_item['id'];
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['qty']++;
        } else {
            $_SESSION['cart'][$cart_key] = [
                'id'    => $menu_item['id'],
                'name'  => $menu_item['name'],
                'price' => $menu_item['price'],
                'image' => $menu_item['image'],
                'qty'   => 1,
            ];
        }
        session_write_close();
        $cat = urlencode($_GET['category'] ?? '');
        header("Location: menu.php?category={$cat}&added={$cart_key}");
        exit;
    }
}

// Fetch categories and items
$active_cat = $_GET['category'] ?? '';
$search     = trim($_GET['search'] ?? '');

$categories = $pdo->query("SELECT DISTINCT category FROM menu_items WHERE status IN ('Available','Seasonal') ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$where  = "WHERE status IN ('Available','Seasonal')";
$params = [];
if ($active_cat) { $where .= " AND category=?"; $params[] = $active_cat; }
if ($search)     { $where .= " AND (name LIKE ? OR description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $pdo->prepare("SELECT * FROM menu_items $where ORDER BY category, name");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Group by category
$grouped = [];
foreach ($items as $item) $grouped[$item['category']][] = $item;

$cart_count = 0;
if (!empty($_SESSION['cart'])) foreach ($_SESSION['cart'] as $c) $cart_count += $c['qty'];

$added_id   = $_GET['added'] ?? '';
$page_title = 'Menu';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($added_id): ?>
<div class="flash flash-success" id="addedFlash">
    ✅ Item added to your cart!
    <a href="cart.php" style="color:#1a7a4a;font-weight:700;margin-left:8px">View Cart →</a>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;margin-left:8px">✕</button>
</div>
<?php endif; ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <h1>Our Menu</h1>
        <p>Fresh ingredients, authentic recipes, fire-crafted every day.</p>
    </div>
    <?php if ($is_logged_in && $cart_count > 0): ?>
    <a href="cart.php" class="btn btn-primary">🛒 View Cart (<?= $cart_count ?> items)</a>
    <?php endif; ?>
</div>

<!-- SEARCH + FILTER -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
    <form method="GET" style="flex:1;min-width:200px;display:flex;gap:8px">
        <?php if ($active_cat): ?><input type="hidden" name="category" value="<?= htmlspecialchars($active_cat) ?>"><?php endif; ?>
        <input type="text" name="search" class="form-input" placeholder="🔍 Search menu items…"
               value="<?= htmlspecialchars($search) ?>" style="flex:1">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?><a href="menu.php" class="btn btn-ghost btn-sm">✕ Clear</a><?php endif; ?>
    </form>
</div>

<!-- CATEGORY PILLS -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px">
    <a href="menu.php<?= $search ? '?search='.urlencode($search) : '' ?>"
       style="padding:8px 18px;border-radius:20px;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s;
              background:<?= !$active_cat ? '#e8622a' : '#fff' ?>;
              color:<?= !$active_cat ? '#fff' : 'var(--text-2)' ?>;
              border:1.5px solid <?= !$active_cat ? '#e8622a' : 'var(--border)' ?>">
        All
    </a>
    <?php foreach ($categories as $cat): ?>
    <a href="menu.php?category=<?= urlencode($cat) ?><?= $search ? '&search='.urlencode($search) : '' ?>"
       style="padding:8px 18px;border-radius:20px;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s;
              background:<?= $active_cat === $cat ? '#e8622a' : '#fff' ?>;
              color:<?= $active_cat === $cat ? '#fff' : 'var(--text-2)' ?>;
              border:1.5px solid <?= $active_cat === $cat ? '#e8622a' : 'var(--border)' ?>">
        <?= htmlspecialchars($cat) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- MENU ITEMS -->
<?php if (empty($items)): ?>
<div style="text-align:center;padding:60px;color:var(--text-3)">
    <div style="font-size:3rem;margin-bottom:12px">🍽️</div>
    <p>No menu items found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.</p>
</div>
<?php else: ?>

<?php foreach ($grouped as $cat_name => $cat_items): ?>
<div style="margin-bottom:36px">
    <h2 style="font-family:'DM Serif Display',serif;font-size:1.3rem;font-weight:400;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--border);display:flex;align-items:center;gap:10px">
        <span style="width:4px;height:24px;background:var(--accent);border-radius:2px;display:inline-block"></span>
        <?= htmlspecialchars($cat_name) ?>
        <span style="font-family:'DM Sans',sans-serif;font-size:.78rem;color:var(--text-3);font-weight:400"><?= count($cat_items) ?> items</span>
    </h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
        <?php foreach ($cat_items as $item):
            $in_cart = !empty($_SESSION['cart'][$item['id']]) ? $_SESSION['cart'][$item['id']]['qty'] : 0;
        ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:transform .2s,box-shadow .2s"
             onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 24px rgba(0,0,0,.1)'"
             onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 10px rgba(0,0,0,.05)'">
            <?php if ($item['image']): ?>
            <div style="height:150px;overflow:hidden">
                <img src="<?= $img_base_url . htmlspecialchars($item['image']) ?>"
                     alt="<?= htmlspecialchars($item['name']) ?>"
                     style="width:100%;height:100%;object-fit:cover">
            </div>
            <?php else: ?>
            <div style="height:150px;background:linear-gradient(135deg,#f4f0ea,#ede5d8);display:flex;align-items:center;justify-content:center;font-size:2.5rem">🍽️</div>
            <?php endif; ?>

            <div style="padding:14px">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:8px;margin-bottom:4px">
                    <strong style="font-size:.92rem;line-height:1.3">
                        <?= htmlspecialchars($item['name']) ?>
                        <?php if ($item['status'] === 'Seasonal'): ?>
                        <span style="background:rgba(245,200,66,.2);color:#8a6e00;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:10px;margin-left:4px">🌿 Seasonal</span>
                        <?php endif; ?>
                    </strong>
                    <span style="color:var(--accent);font-weight:700;font-size:.92rem;white-space:nowrap">Rs. <?= number_format($item['price'], 0) ?></span>
                </div>
                <?php if ($item['description']): ?>
                <p style="color:var(--text-3);font-size:.78rem;line-height:1.5;margin-bottom:10px">
                    <?= htmlspecialchars(mb_strimwidth($item['description'], 0, 70, '…')) ?>
                </p>
                <?php endif; ?>
                <?php if ($item['calories']): ?>
                <div style="font-size:.72rem;color:var(--text-3);margin-bottom:10px">🔥 <?= htmlspecialchars($item['calories']) ?> cal</div>
                <?php endif; ?>

                <?php if ($is_logged_in): ?>
                    <?php if ($in_cart > 0): ?>
                    <div style="display:flex;align-items:center;gap:8px">
                        <a href="cart.php?remove_one=<?= urlencode($item['id']) ?>&from=menu&category=<?= urlencode($active_cat) ?>"
                           class="btn btn-ghost btn-sm" style="flex:0 0 auto">−</a>
                        <span style="flex:1;text-align:center;font-weight:700;font-size:.88rem;color:var(--accent)"><?= $in_cart ?> in cart</span>
                        <a href="menu.php?add=<?= urlencode($item['id']) ?>&category=<?= urlencode($active_cat) ?>"
                           class="btn btn-primary btn-sm" style="flex:0 0 auto">＋</a>
                    </div>
                    <?php else: ?>
                    <a href="menu.php?add=<?= urlencode($item['id']) ?>&category=<?= urlencode($active_cat) ?>"
                       class="btn btn-primary btn-sm" style="width:100%;justify-content:center">＋ Add to Cart</a>
                    <?php endif; ?>
                <?php else: ?>
                <a href="login.php?redirect=menu.php" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center">Login to Order</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
const f = document.getElementById('addedFlash');
if (f) setTimeout(() => { f.style.transition = 'opacity .5s'; f.style.opacity = '0'; }, 3000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>