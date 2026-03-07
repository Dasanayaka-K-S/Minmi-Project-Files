<?php
// ============================================================
//  MENU MANAGEMENT — Minmi Restaurent Admin
//  Place in: dashboard/pages/menu.php
// ============================================================

require_once __DIR__ . '/../includes/db.php';

$upload_dir = __DIR__ . '/../uploads/menu/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

function handleImageUpload($file_input, $upload_dir, $old_image = null) {
    if (empty($_FILES[$file_input]['name'])) return $old_image;
    $file    = $_FILES[$file_input];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed))        return $old_image;
    if ($file['size'] > 3 * 1024 * 1024) return $old_image;
    $filename = 'menu_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $dest     = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        if ($old_image && file_exists($upload_dir . basename($old_image)))
            @unlink($upload_dir . basename($old_image));
        return 'uploads/menu/' . $filename;
    }
    return $old_image;
}

$action   = $_POST['action']  ?? $_GET['action']  ?? '';
$item_id  = $_POST['item_id'] ?? $_GET['item_id'] ?? '';
$msg      = '';
$msg_type = 'success';

if ($action === 'add') {
    $image_path = handleImageUpload('image', $upload_dir);
    $new_id     = 'M' . strtoupper(substr(uniqid(), -6));
    $pdo->prepare("
        INSERT INTO menu_items (id, name, category, price, cost, status, description, calories, image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $new_id, trim($_POST['name']), trim($_POST['category']),
        (float)$_POST['price'], (float)$_POST['cost'], $_POST['status'],
        trim($_POST['description']), (int)($_POST['calories'] ?? 0), $image_path,
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" added to menu!';
}

if ($action === 'edit' && $item_id) {
    $old = $pdo->prepare("SELECT image FROM menu_items WHERE id=?");
    $old->execute([$item_id]);
    $old_image  = $old->fetchColumn();
    $image_path = handleImageUpload('image', $upload_dir, $old_image);
    $pdo->prepare("
        UPDATE menu_items
        SET name=?, category=?, price=?, cost=?, status=?, description=?, calories=?, image=?
        WHERE id=?
    ")->execute([
        trim($_POST['name']), trim($_POST['category']),
        (float)$_POST['price'], (float)$_POST['cost'], $_POST['status'],
        trim($_POST['description']), (int)($_POST['calories'] ?? 0),
        $image_path, $item_id,
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" updated successfully!';
}

if ($action === 'delete' && $item_id) {
    $row = $pdo->prepare("SELECT name, image FROM menu_items WHERE id=?");
    $row->execute([$item_id]);
    $del = $row->fetch();
    if (!empty($del['image'])) {
        $img_file = __DIR__ . '/../' . $del['image'];
        if (file_exists($img_file)) @unlink($img_file);
    }
    $pdo->prepare("DELETE FROM menu_items WHERE id=?")->execute([$item_id]);
    $msg      = '🗑️ "' . htmlspecialchars($del['name']) . '" removed from menu.';
    $msg_type = 'danger';
}

if ($action === 'status' && $item_id) {
    $pdo->prepare("UPDATE menu_items SET status=? WHERE id=?")
        ->execute([$_POST['new_status'], $item_id]);
    $msg = '✅ Status updated to "' . htmlspecialchars($_POST['new_status']) . '".';
}

$menu_items  = $pdo->query("SELECT * FROM menu_items ORDER BY category, name")->fetchAll();
$page_title  = 'Menu Management';
$available   = count(array_filter($menu_items, fn($m) => $m['status'] === 'Available'));
$seasonal    = count(array_filter($menu_items, fn($m) => $m['status'] === 'Seasonal'));
$unavailable = count(array_filter($menu_items, fn($m) => $m['status'] === 'Unavailable'));
$categories  = array_unique(array_column($menu_items, 'category'));
sort($categories);

$top8 = array_slice(array_filter($menu_items, fn($m) => $m['price'] > 0), 0, 8);
$chart_names   = json_encode(array_column($top8, 'name'));
$chart_margins = json_encode(array_map(
    fn($m) => round((($m['price'] - $m['cost']) / $m['price']) * 100, 1), $top8
));

function menuBadge($s) {
    if ($s === 'Available')  return '<span class="badge badge-green">Available</span>';
    if ($s === 'Seasonal')   return '<span class="badge badge-yellow">Seasonal</span>';
    return '<span class="badge badge-red">Unavailable</span>';
}

$page_scripts = count($top8) ? "buildBarChart('marginChart', {$chart_names}, {$chart_margins}, '#3ecf8e');" : '';

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-msg flash-<?= $msg_type ?>" id="flashMsg">
    <?= $msg ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:12px;font-size:1rem">✕</button>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Menu Management</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Manage dishes, pricing, images &amp; availability.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">＋ Add Menu Item</button>
</div>

<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-icon">🟢</div>
        <div class="stat-label">Available</div>
        <div class="stat-value"><?= $available ?></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">🌿</div>
        <div class="stat-label">Seasonal</div>
        <div class="stat-value"><?= $seasonal ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">🔴</div>
        <div class="stat-label">Unavailable</div>
        <div class="stat-value"><?= $unavailable ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🍽️</div>
        <div class="stat-label">Total Items</div>
        <div class="stat-value"><?= count($menu_items) ?></div>
    </div>
</div>

<?php if (!empty($top8)): ?>
<div class="card">
    <div class="card-header">
        <div class="card-title">Profit Margin by Item (Top 8)</div>
        <span class="badge badge-green">% margin</span>
    </div>
    <div class="chart-box"><canvas id="marginChart"></canvas></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">All Menu Items</div>
        <span class="badge badge-gray" id="menuCount"><?= count($menu_items) ?> items</span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="text" id="searchMenu" class="search-input"
               placeholder="🔍  Search name or category…" oninput="filterMenu()"
               style="flex:1;min-width:200px">
        <select id="filterCat" class="search-input" style="min-width:150px" onchange="filterMenu()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterStatus" class="search-input" style="min-width:150px" onchange="filterMenu()">
            <option value="">All Statuses</option>
            <option>Available</option><option>Seasonal</option><option>Unavailable</option>
        </select>
    </div>

    <?php if (empty($menu_items)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-3)">
        <div style="font-size:2.5rem;margin-bottom:10px">🍽️</div>
        <p>No menu items yet. Click <strong>＋ Add Menu Item</strong> to get started.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table id="menuTable">
            <thead>
                <tr>
                    <th>Image</th><th>Item Name</th><th>Category</th>
                    <th>Price</th><th>Cost</th><th>Margin</th>
                    <th>Calories</th><th>Status</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($menu_items as $m):
                $margin = $m['price'] > 0
                    ? round((($m['price'] - $m['cost']) / $m['price']) * 100, 1) : 0;
                $mc = $margin > 60 ? 'var(--green)' : ($margin > 45 ? 'var(--yellow)' : 'var(--red)');
                $img_src = !empty($m['image']) ? '../' . htmlspecialchars($m['image']) : null;
            ?>
            <tr data-name="<?= strtolower($m['name']) ?>"
                data-cat="<?= strtolower($m['category']) ?>"
                data-status="<?= $m['status'] ?>">
                <td>
                    <?php if ($img_src): ?>
                    <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($m['name']) ?>"
                         style="width:52px;height:52px;object-fit:cover;border-radius:10px;border:1px solid var(--border)">
                    <?php else: ?>
                    <div style="width:52px;height:52px;background:var(--bg-3);border-radius:10px;
                                border:1px solid var(--border);display:flex;align-items:center;
                                justify-content:center;font-size:1.4rem">🍽️</div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= htmlspecialchars($m['name']) ?></strong><br>
                    <span style="color:var(--text-3);font-size:.75rem"><?= htmlspecialchars($m['description']) ?></span>
                </td>
                <td><span class="badge badge-gray"><?= htmlspecialchars($m['category']) ?></span></td>
                <td><strong>Rs. <?= number_format($m['price'], 2) ?></strong></td>
                <td style="color:var(--text-2)">Rs. <?= number_format($m['cost'], 2) ?></td>
                <td><span style="color:<?= $mc ?>;font-weight:600"><?= $margin ?>%</span></td>
                <td style="color:var(--text-2)"><?= $m['calories'] ?> kcal</td>
                <td><?= menuBadge($m['status']) ?></td>
                <td>
                    <div style="display:flex;gap:6px;justify-content:center">
                        <button class="btn btn-ghost btn-sm btn-icon" title="Edit"
                                onclick="openEditModal(
                                    '<?= htmlspecialchars($m['id'],         ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($m['name'],       ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($m['category'],   ENT_QUOTES) ?>',
                                    '<?= $m['price'] ?>',
                                    '<?= $m['cost'] ?>',
                                    '<?= $m['status'] ?>',
                                    '<?= htmlspecialchars($m['description'],ENT_QUOTES) ?>',
                                    '<?= $m['calories'] ?>',
                                    '<?= $img_src ?? '' ?>'
                                )">✏️</button>
                        <button class="btn btn-ghost btn-sm btn-icon" title="Change Status"
                                onclick="openStatusModal(
                                    '<?= htmlspecialchars($m['id'],  ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($m['name'],ENT_QUOTES) ?>',
                                    '<?= $m['status'] ?>'
                                )">🔄</button>
                        <button class="btn btn-danger btn-sm btn-icon" title="Delete"
                                onclick="openDeleteModal(
                                    '<?= htmlspecialchars($m['id'],  ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($m['name'],ENT_QUOTES) ?>'
                                )">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ADD MODAL -->
<div class="modal-backdrop" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">＋ Add Menu Item</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="menu.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Item Image</label>
                        <div class="img-upload-box" onclick="document.getElementById('add_img_input').click()">
                            <img id="add_img_preview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:10px">
                            <div id="add_img_placeholder">
                                <div style="font-size:2rem">📷</div>
                                <span>Click to upload image</span>
                                <small>JPG, PNG, WEBP · Max 3MB</small>
                            </div>
                        </div>
                        <input type="file" id="add_img_input" name="image" accept="image/*" style="display:none"
                               onchange="previewImg(this,'add_img_preview','add_img_placeholder')">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Item Name *</label>
                        <input class="form-input" type="text" name="name" placeholder="e.g. Rice & Curry" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <input class="form-input" type="text" name="category" placeholder="e.g. Mains" required list="catList">
                        <datalist id="catList">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status">
                            <option value="Available">Available</option>
                            <option value="Seasonal">Seasonal</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (Rs.) *</label>
                        <input class="form-input" type="number" name="price" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost (Rs.) *</label>
                        <input class="form-input" type="number" name="cost" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Calories (kcal)</label>
                        <input class="form-input" type="number" name="calories" min="0" placeholder="e.g. 520">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" name="description" rows="3"
                            placeholder="Brief description…" style="resize:vertical"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">＋ Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Menu Item</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="menu.php" enctype="multipart/form-data">
                <input type="hidden" name="action"  value="edit">
                <input type="hidden" name="item_id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Item Image</label>
                        <div class="img-upload-box" onclick="document.getElementById('edit_img_input').click()">
                            <img id="edit_img_preview" src="" alt=""
                                 style="width:100%;height:100%;object-fit:cover;border-radius:10px">
                            <div id="edit_img_placeholder" style="display:none">
                                <div style="font-size:2rem">📷</div>
                                <span>Click to change image</span>
                                <small>JPG, PNG, WEBP · Max 3MB</small>
                            </div>
                        </div>
                        <input type="file" id="edit_img_input" name="image" accept="image/*" style="display:none"
                               onchange="previewImg(this,'edit_img_preview','edit_img_placeholder')">
                        <span style="font-size:.72rem;color:var(--text-3)">Leave blank to keep existing image</span>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Item Name *</label>
                        <input class="form-input" type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <input class="form-input" type="text" name="category" id="edit_category" required list="catList">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status" id="edit_status">
                            <option value="Available">Available</option>
                            <option value="Seasonal">Seasonal</option>
                            <option value="Unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (Rs.) *</label>
                        <input class="form-input" type="number" name="price" id="edit_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost (Rs.) *</label>
                        <input class="form-input" type="number" name="cost" id="edit_cost" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Calories (kcal)</label>
                        <input class="form-input" type="number" name="calories" id="edit_calories" min="0">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" name="description" id="edit_desc"
                            rows="3" style="resize:vertical"></textarea>
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

<!-- STATUS MODAL -->
<div class="modal-backdrop" id="statusModal">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <div class="modal-title">🔄 Change Status</div>
            <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-2);font-size:.88rem;margin-bottom:16px">
                Changing status for: <strong id="status_name"></strong>
            </p>
            <form method="POST" action="menu.php">
                <input type="hidden" name="action"  value="status">
                <input type="hidden" name="item_id" id="status_id">
                <div class="form-group" style="margin-bottom:20px">
                    <label class="form-label">New Status</label>
                    <select class="form-input" name="new_status" id="status_select">
                        <option value="Available">🟢 Available</option>
                        <option value="Seasonal">🌿 Seasonal</option>
                        <option value="Unavailable">🔴 Unavailable</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">✅ Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-body" style="text-align:center;padding:32px 28px 24px">
            <div style="font-size:3rem;margin-bottom:12px">🗑️</div>
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">Delete Menu Item?</h3>
            <p id="delete_msg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <form method="POST" action="menu.php">
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
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;
    border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:var(--green-dim);color:var(--green);border:1px solid var(--green)}
.flash-danger {background:var(--red-dim);  color:var(--red);  border:1px solid var(--red)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;
    display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);
    width:100%;max-width:580px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;padding:4px;border-radius:6px}
.modal-close:hover{color:var(--text)}
.modal-body{padding:18px 24px 24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:1/-1}
.form-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-3)}
.form-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);
    border-radius:var(--radius);padding:9px 13px;font-family:'DM Sans',sans-serif;
    font-size:.85rem;outline:none;transition:border-color .2s;width:100%}
.form-input:focus{border-color:var(--accent)}
.form-input::placeholder{color:var(--text-3)}
select.form-input{cursor:pointer}
textarea.form-input{resize:vertical}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;
    padding-top:16px;border-top:1px solid var(--border)}
.img-upload-box{width:100%;height:160px;background:var(--bg-3);border:2px dashed var(--border-l);
    border-radius:12px;cursor:pointer;overflow:hidden;position:relative;
    display:flex;align-items:center;justify-content:center;transition:border-color .2s,background .2s}
.img-upload-box:hover{border-color:var(--accent);background:rgba(232,98,42,.04)}
.img-upload-box div[id$="placeholder"]{display:flex;flex-direction:column;align-items:center;
    gap:6px;color:var(--text-3);font-size:.82rem;pointer-events:none}
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);
    border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;
    font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
</style>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-backdrop.open').forEach(bd => bd.classList.remove('open'));
});

function previewImg(input, previewId, placeholderId) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview     = document.getElementById(previewId);
        const placeholder = document.getElementById(placeholderId);
        preview.src       = e.target.result;
        preview.style.display     = 'block';
        placeholder.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

function openEditModal(id, name, category, price, cost, status, desc, calories, imgSrc) {
    document.getElementById('edit_id').value       = id;
    document.getElementById('edit_name').value     = name;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_price').value    = price;
    document.getElementById('edit_cost').value     = cost;
    document.getElementById('edit_status').value   = status;
    document.getElementById('edit_desc').value     = desc;
    document.getElementById('edit_calories').value = calories;
    const preview     = document.getElementById('edit_img_preview');
    const placeholder = document.getElementById('edit_img_placeholder');
    if (imgSrc) {
        preview.src               = imgSrc;
        preview.style.display     = 'block';
        placeholder.style.display = 'none';
    } else {
        preview.style.display     = 'none';
        placeholder.style.display = 'flex';
    }
    openModal('editModal');
}

function openStatusModal(id, name, status) {
    document.getElementById('status_id').value          = id;
    document.getElementById('status_name').textContent  = name;
    document.getElementById('status_select').value      = status;
    openModal('statusModal');
}

function openDeleteModal(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_msg').textContent =
        'This will permanently delete "' + name + '" and its image. This cannot be undone.';
    openModal('deleteModal');
}

function filterMenu() {
    const q   = document.getElementById('searchMenu').value.toLowerCase();
    const cat = document.getElementById('filterCat').value.toLowerCase();
    const st  = document.getElementById('filterStatus').value;
    let   n   = 0;
    document.querySelectorAll('#menuTable tbody tr').forEach(row => {
        const show =
            (!q   || row.dataset.name.includes(q) || row.dataset.cat.includes(q)) &&
            (!cat || row.dataset.cat.includes(cat)) &&
            (!st  || row.dataset.status === st);
        row.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.getElementById('menuCount').textContent = n + ' items';
}

const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 3500);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
