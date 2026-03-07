<?php
require_once __DIR__ . '/../includes/db.php';

$action   = $_POST['action']  ?? '';
$item_id  = $_POST['item_id'] ?? '';
$msg      = '';
$msg_type = 'success';

if ($action === 'add') {
    $new_id = 'INV-' . strtoupper(substr(uniqid(), -5));
    $pdo->prepare("INSERT INTO inventory (id, name, category, stock, min_stock, unit, cost_per_unit, supplier, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$new_id, trim($_POST['name']), trim($_POST['category']), (int)$_POST['stock'], (int)$_POST['min_stock'], trim($_POST['unit']), (float)$_POST['cost_per_unit'], trim($_POST['supplier']), $_POST['last_updated'] ?: date('Y-m-d')]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" added to inventory!';
}

if ($action === 'edit' && $item_id) {
    $pdo->prepare("UPDATE inventory SET name=?, category=?, stock=?, min_stock=?, unit=?, cost_per_unit=?, supplier=?, last_updated=? WHERE id=?")
        ->execute([trim($_POST['name']), trim($_POST['category']), (int)$_POST['stock'], (int)$_POST['min_stock'], trim($_POST['unit']), (float)$_POST['cost_per_unit'], trim($_POST['supplier']), $_POST['last_updated'] ?: date('Y-m-d'), $item_id]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" updated!';
}

if ($action === 'delete' && $item_id) {
    $row = $pdo->prepare("SELECT name FROM inventory WHERE id=?");
    $row->execute([$item_id]);
    $del_name = $row->fetchColumn() ?: $item_id;
    $pdo->prepare("DELETE FROM inventory WHERE id=?")->execute([$item_id]);
    $msg = '🗑️ "' . htmlspecialchars($del_name) . '" removed.';
    $msg_type = 'danger';
}

if ($action === 'restock' && $item_id) {
    $add_qty = (int)($_POST['add_qty'] ?? 0);
    $pdo->prepare("UPDATE inventory SET stock = stock + ?, last_updated = ? WHERE id=?")
        ->execute([$add_qty, date('Y-m-d'), $item_id]);
    $msg = '✅ Stock updated successfully!';
}

$inventory = $pdo->query("SELECT * FROM inventory ORDER BY category, name")->fetchAll();

$low_stock_items = array_filter($inventory, fn($i) => $i['stock'] < $i['min_stock']);
$ok_items        = count(array_filter($inventory, fn($i) => $i['stock'] >= $i['min_stock'] * 1.5));
$monitor_items   = count(array_filter($inventory, fn($i) => $i['stock'] >= $i['min_stock'] && $i['stock'] < $i['min_stock'] * 1.5));
$stock_value     = array_sum(array_map(fn($i) => $i['stock'] * $i['cost_per_unit'], $inventory));

$cats = [];
foreach ($inventory as $i) { $cats[$i['category']] = ($cats[$i['category']] ?? 0) + 1; }
$cat_labels = json_encode(array_keys($cats));
$cat_vals   = json_encode(array_values($cats));
$inventory_json = json_encode($inventory);
$page_title = 'Inventory';

$page_scripts = <<<JS
buildDoughnutChart('invCatChart', {$cat_labels}, {$cat_vals},
    ['#e8622a','#f5c842','#3ecf8e','#4e9cf7','#a855f7','#f472b6']);
JS;

function stockStatus(array $i): array {
    if ($i['stock'] == 0)                     return ['badge-red',    'Out of Stock'];
    if ($i['stock'] < $i['min_stock'])        return ['badge-red',    'Low Stock'];
    if ($i['stock'] < $i['min_stock'] * 1.5) return ['badge-yellow', 'Monitor'];
    return ['badge-green', 'OK'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
<div class="flash-msg flash-<?= $msg_type ?>" id="flashMsg">
    <?= $msg ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:12px">✕</button>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Inventory</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Track stock levels, costs and suppliers.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">＋ Add Item</button>
</div>

<?php if (count($low_stock_items) > 0): ?>
<div style="background:rgba(239,68,68,.1);border:1px solid var(--red);border-radius:var(--radius);
            padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:var(--red)">
    ⚠️ <strong><?= count($low_stock_items) ?> item<?= count($low_stock_items) > 1 ? 's' : '' ?> below minimum stock:</strong>
    <?= implode(', ', array_column(array_values($low_stock_items), 'name')) ?>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div class="stat-label">Low Stock</div>
        <div class="stat-value"><?= count($low_stock_items) ?></div>
        <div class="stat-sub">Needs restocking</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">👁️</div>
        <div class="stat-label">Monitor</div>
        <div class="stat-value"><?= $monitor_items ?></div>
        <div class="stat-sub">Getting low</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Well Stocked</div>
        <div class="stat-value"><?= $ok_items ?></div>
        <div class="stat-sub">Good levels</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">📦</div>
        <div class="stat-label">Total Items</div>
        <div class="stat-value"><?= count($inventory) ?></div>
        <div class="stat-sub">In inventory</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">💲</div>
        <div class="stat-label">Stock Value</div>
        <div class="stat-value">Rs. <?= number_format($stock_value, 0) ?></div>
        <div class="stat-sub">Estimated total</div>
    </div>
</div>

<div class="inv-grid-2">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Inventory by Category</div>
            <span class="badge badge-gray"><?= count($cats) ?> categories</span>
        </div>
        <div class="chart-box"><canvas id="invCatChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">Critical Low Stock</div>
            <span class="badge badge-red"><?= count($low_stock_items) ?> items</span>
        </div>
        <div style="padding:4px 0">
        <?php if (count($low_stock_items) === 0): ?>
            <p style="color:var(--green);text-align:center;padding:32px;font-size:.85rem">✅ All items adequately stocked!</p>
        <?php else: foreach ($low_stock_items as $i):
            $pct = $i['min_stock'] > 0 ? min(100, round(($i['stock'] / $i['min_stock']) * 100)) : 0; ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                <span style="font-size:.8rem;min-width:130px;color:var(--text-2)" title="<?= htmlspecialchars($i['name']) ?>">
                    <?= htmlspecialchars(mb_strimwidth($i['name'], 0, 18, '…')) ?>
                </span>
                <div style="flex:1;height:6px;background:var(--bg-3);border-radius:3px;overflow:hidden">
                    <div style="width:<?= $pct ?>%;height:100%;background:var(--red);border-radius:3px"></div>
                </div>
                <span style="font-size:.75rem;color:var(--red);min-width:60px;text-align:right">
                    <?= $i['stock'] ?>/<?= $i['min_stock'] ?> <?= htmlspecialchars($i['unit']) ?>
                </span>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">All Inventory Items</div>
        <span class="badge badge-gray" id="invCount"><?= count($inventory) ?> items</span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="text" id="searchInventory" class="search-input" placeholder="🔍  Search name, category, supplier…" oninput="filterInventory()" style="flex:1;min-width:200px">
        <select id="filterCategory" class="search-input" style="min-width:160px" onchange="filterInventory()">
            <option value="">All Categories</option>
            <?php foreach (array_keys($cats) as $cat): ?>
            <option><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterStockStatus" class="search-input" style="min-width:150px" onchange="filterInventory()">
            <option value="">All Statuses</option>
            <option value="low">Low Stock</option>
            <option value="monitor">Monitor</option>
            <option value="ok">OK</option>
        </select>
    </div>
    <?php if (empty($inventory)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-3)">
        <div style="font-size:2.5rem;margin-bottom:10px">📦</div>
        <p>No inventory items yet. Click <strong>＋ Add Item</strong> to get started.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table id="invTable">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Category</th><th>Stock</th>
                    <th>Min Stock</th><th>Cost/Unit</th><th>Supplier</th>
                    <th>Last Updated</th><th>Status</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inventory as $i):
                [$cls, $lbl] = stockStatus($i);
                $pct = $i['min_stock'] > 0 ? min(100, round(($i['stock'] / $i['min_stock']) * 100)) : 100;
                $bar_color = $i['stock'] < $i['min_stock'] ? 'var(--red)' : ($i['stock'] < $i['min_stock'] * 1.5 ? 'var(--yellow)' : 'var(--green)');
                $stock_level = $i['stock'] < $i['min_stock'] ? 'low' : ($i['stock'] < $i['min_stock'] * 1.5 ? 'monitor' : 'ok');
            ?>
            <tr data-name="<?= strtolower(htmlspecialchars($i['name'])) ?>"
                data-category="<?= htmlspecialchars($i['category']) ?>"
                data-supplier="<?= strtolower(htmlspecialchars($i['supplier'] ?? '')) ?>"
                data-stocklevel="<?= $stock_level ?>">
                <td style="color:var(--text-3);font-size:.78rem"><?= htmlspecialchars($i['id']) ?></td>
                <td><strong><?= htmlspecialchars($i['name']) ?></strong></td>
                <td><span class="badge badge-gray"><?= htmlspecialchars($i['category']) ?></span></td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:4px">
                        <span><?= $i['stock'] ?> <span style="color:var(--text-3);font-size:.75rem"><?= htmlspecialchars($i['unit']) ?></span></span>
                        <div style="width:70px;height:5px;background:var(--bg-3);border-radius:3px;overflow:hidden">
                            <div style="width:<?= $pct ?>%;height:100%;background:<?= $bar_color ?>;border-radius:3px"></div>
                        </div>
                    </div>
                </td>
                <td style="color:var(--text-2)"><?= $i['min_stock'] ?> <?= htmlspecialchars($i['unit']) ?></td>
                <td>Rs. <?= number_format($i['cost_per_unit'], 2) ?></td>
                <td style="color:var(--text-2)"><?= htmlspecialchars($i['supplier'] ?? '—') ?></td>
                <td style="color:var(--text-3);font-size:.78rem"><?= htmlspecialchars($i['last_updated'] ?? '—') ?></td>
                <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                <td>
                    <div style="display:flex;gap:4px;justify-content:center">
                        <button class="btn btn-ghost btn-sm btn-icon btn-restock" data-id="<?= htmlspecialchars($i['id']) ?>" title="Restock">📥</button>
                        <button class="btn btn-ghost btn-sm btn-icon btn-edit"    data-id="<?= htmlspecialchars($i['id']) ?>" title="Edit">✏️</button>
                        <button class="btn btn-danger btn-sm btn-icon btn-delete" data-id="<?= htmlspecialchars($i['id']) ?>" data-name="<?= htmlspecialchars($i['name']) ?>" title="Delete">🗑️</button>
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
            <div class="modal-title">＋ Add Inventory Item</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="inventory.php">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group span2">
                        <label class="form-label">Item Name *</label>
                        <input class="form-input" type="text" name="name" placeholder="e.g. Coconut Oil" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <input class="form-input" type="text" name="category" placeholder="e.g. Oils & Fats" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <input class="form-input" type="text" name="unit" placeholder="e.g. L, kg, pcs">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Stock *</label>
                        <input class="form-input" type="number" name="stock" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Stock *</label>
                        <input class="form-input" type="number" name="min_stock" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost per Unit (Rs.)</label>
                        <input class="form-input" type="number" name="cost_per_unit" step="0.01" min="0" value="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <input class="form-input" type="text" name="supplier" placeholder="Supplier name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Updated</label>
                        <input class="form-input" type="date" name="last_updated" value="<?= date('Y-m-d') ?>">
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
            <div class="modal-title">✏️ Edit Item</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="inventory.php">
                <input type="hidden" name="action"  value="edit">
                <input type="hidden" name="item_id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group span2">
                        <label class="form-label">Item Name *</label>
                        <input class="form-input" type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <input class="form-input" type="text" name="category" id="edit_category" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <input class="form-input" type="text" name="unit" id="edit_unit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Stock *</label>
                        <input class="form-input" type="number" name="stock" id="edit_stock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Stock *</label>
                        <input class="form-input" type="number" name="min_stock" id="edit_min_stock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost per Unit (Rs.)</label>
                        <input class="form-input" type="number" name="cost_per_unit" id="edit_cost" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <input class="form-input" type="text" name="supplier" id="edit_supplier">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Updated</label>
                        <input class="form-input" type="date" name="last_updated" id="edit_updated">
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

<!-- RESTOCK MODAL -->
<div class="modal-backdrop" id="restockModal">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <div class="modal-title">📥 Restock Item</div>
            <button class="modal-close" onclick="closeModal('restockModal')">✕</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--text-2);font-size:.85rem;margin-bottom:4px">Item: <strong id="restock_name"></strong></p>
            <p style="color:var(--text-3);font-size:.8rem;margin-bottom:16px">Current stock: <strong id="restock_current"></strong></p>
            <form method="POST" action="inventory.php">
                <input type="hidden" name="action"  value="restock">
                <input type="hidden" name="item_id" id="restock_id">
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Quantity to Add *</label>
                    <input class="form-input" type="number" name="add_qty" id="restock_qty" min="1" value="10" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('restockModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">📥 Add Stock</button>
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
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">Delete Item?</h3>
            <p id="deleteMsg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <form method="POST" action="inventory.php">
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

<div class="toast" id="toast"></div>

<style>
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:var(--green-dim);color:var(--green);border:1px solid var(--green)}
.flash-danger{background:var(--red-dim);color:var(--red);border:1px solid var(--red)}
.inv-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;padding:4px;border-radius:6px}
.modal-close:hover{color:var(--text)}
.modal-body{padding:18px 24px 24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.span2{grid-column:span 2}
.form-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-3)}
.form-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:9px 13px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;width:100%;box-sizing:border-box}
.form-input:focus{border-color:var(--accent)}
select.form-input{cursor:pointer}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)}
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
@media(max-width:768px){.inv-grid-2{grid-template-columns:1fr}}
</style>

<script>
const INVENTORY = <?= $inventory_json ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-backdrop.open').forEach(bd => bd.classList.remove('open'));
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const id   = btn.dataset.id;
    const item = id ? INVENTORY.find(x => x.id === id) : null;

    if (btn.classList.contains('btn-restock') && item) {
        document.getElementById('restock_id').value            = item.id;
        document.getElementById('restock_name').textContent    = item.name;
        document.getElementById('restock_current').textContent = item.stock + ' ' + item.unit;
        document.getElementById('restock_qty').value           = 10;
        openModal('restockModal');
    }
    if (btn.classList.contains('btn-edit') && item) {
        document.getElementById('edit_id').value        = item.id;
        document.getElementById('edit_name').value      = item.name;
        document.getElementById('edit_category').value  = item.category;
        document.getElementById('edit_unit').value      = item.unit;
        document.getElementById('edit_stock').value     = item.stock;
        document.getElementById('edit_min_stock').value = item.min_stock;
        document.getElementById('edit_cost').value      = item.cost_per_unit;
        document.getElementById('edit_supplier').value  = item.supplier || '';
        document.getElementById('edit_updated').value   = item.last_updated || '';
        openModal('editModal');
    }
    if (btn.classList.contains('btn-delete')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteMsg').textContent =
            'This will permanently delete "' + btn.dataset.name + '" from inventory. This cannot be undone.';
        openModal('deleteModal');
    }
});

function filterInventory() {
    const q    = document.getElementById('searchInventory').value.toLowerCase();
    const cat  = document.getElementById('filterCategory').value;
    const stat = document.getElementById('filterStockStatus').value;
    let n = 0;
    document.querySelectorAll('#invTable tbody tr').forEach(row => {
        const show =
            (!q    || row.dataset.name.includes(q) || row.dataset.supplier.includes(q)) &&
            (!cat  || row.dataset.category === cat) &&
            (!stat || row.dataset.stocklevel === stat);
        row.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.getElementById('invCount').textContent = n + ' items';
}

const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 4000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
