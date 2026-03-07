<?php
// ============================================================
//  CUSTOMERS — Minmi Restaurent Admin
//  Place in: dashboard/pages/customers.php
//  Features: Add, Edit, Delete, View Profile
// ============================================================

require_once __DIR__ . '/../includes/db.php';

$action   = $_POST['action']  ?? '';
$item_id  = $_POST['item_id'] ?? '';
$msg      = '';
$msg_type = 'success';

if ($action === 'add') {
    $new_id = 'C' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    $pdo->prepare("
        INSERT INTO customers (id, name, email, phone, orders_count, total_spent, status, joined)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $new_id,
        trim($_POST['name']),
        trim($_POST['email']),
        trim($_POST['phone']          ?? ''),
        (int)($_POST['orders_count']  ?? 0),
        (float)($_POST['total_spent'] ?? 0),
        $_POST['status'],
        $_POST['joined'] ?: date('Y-m-d'),
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" added successfully!';
}

if ($action === 'edit' && $item_id) {
    $pdo->prepare("
        UPDATE customers
        SET name=?, email=?, phone=?, orders_count=?, total_spent=?, status=?, joined=?
        WHERE id=?
    ")->execute([
        trim($_POST['name']),
        trim($_POST['email']),
        trim($_POST['phone']          ?? ''),
        (int)($_POST['orders_count']  ?? 0),
        (float)($_POST['total_spent'] ?? 0),
        $_POST['status'],
        $_POST['joined'],
        $item_id,
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" updated!';
}

if ($action === 'delete' && $item_id) {
    $row = $pdo->prepare("SELECT name FROM customers WHERE id=?");
    $row->execute([$item_id]);
    $del_name = $row->fetchColumn() ?: $item_id;
    $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$item_id]);
    $msg      = '🗑️ "' . htmlspecialchars($del_name) . '" removed.';
    $msg_type = 'danger';
}

if ($action === 'status' && $item_id) {
    $pdo->prepare("UPDATE customers SET status=? WHERE id=?")
        ->execute([$_POST['new_status'], $item_id]);
    $msg = '✅ Status updated to "' . htmlspecialchars($_POST['new_status']) . '".';
}

$customers = $pdo->query("SELECT * FROM customers ORDER BY total_spent DESC")->fetchAll();
$page_title = 'Customers';

$vip             = count(array_filter($customers, fn($c) => $c['status'] === 'VIP'));
$active          = count(array_filter($customers, fn($c) => $c['status'] === 'Active'));
$inactive        = count(array_filter($customers, fn($c) => $c['status'] === 'Inactive'));
$new_c           = count(array_filter($customers, fn($c) => $c['status'] === 'New'));
$total_spent_all = array_sum(array_column($customers, 'total_spent'));

$top5       = array_slice($customers, 0, 5);
$top5_names = json_encode(array_column($top5, 'name'));
$top5_spent = json_encode(array_column($top5, 'total_spent'));
$customers_json = json_encode($customers);

$page_scripts = count($top5)
    ? "buildBarChart('topCustChart', {$top5_names}, {$top5_spent}, '#f5c842');"
    : '';

function custBadge($s) {
    $map = ['VIP'=>'badge-orange','Active'=>'badge-green','Inactive'=>'badge-gray','New'=>'badge-blue'];
    return '<span class="badge '.($map[$s]??'badge-gray').'">'.$s.'</span>';
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
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Customers</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">View and manage your customer base.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" onclick="openModal('addModal')">＋ Add Customer</button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-icon">⭐</div>
        <div class="stat-label">VIP</div>
        <div class="stat-value"><?= $vip ?></div>
        <div class="stat-sub">Top customers</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Active</div>
        <div class="stat-value"><?= $active ?></div>
        <div class="stat-sub">Regular visitors</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">🆕</div>
        <div class="stat-label">New</div>
        <div class="stat-value"><?= $new_c ?></div>
        <div class="stat-sub">Recently joined</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon">💤</div>
        <div class="stat-label">Inactive</div>
        <div class="stat-value"><?= $inactive ?></div>
        <div class="stat-sub">Need re-engagement</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon">💰</div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value">Rs. <?= number_format($total_spent_all, 0) ?></div>
        <div class="stat-sub">All customers</div>
    </div>
</div>

<?php if (!empty($top5)): ?>
<div class="card">
    <div class="card-header">
        <div class="card-title">Top 5 Customers by Spend</div>
        <span class="badge badge-yellow">Highest spenders</span>
    </div>
    <div class="chart-box"><canvas id="topCustChart"></canvas></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Customer Directory</div>
        <span class="badge badge-gray" id="custCount"><?= count($customers) ?> customers</span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <input type="text" id="searchCustomers" class="search-input"
               placeholder="🔍  Search name, email, phone…"
               oninput="filterCustomers()" style="flex:1;min-width:200px">
        <select id="filterStatus" class="search-input" style="min-width:150px" onchange="filterCustomers()">
            <option value="">All Statuses</option>
            <option>VIP</option><option>Active</option>
            <option>New</option><option>Inactive</option>
        </select>
    </div>
    <?php if (empty($customers)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-3)">
        <div style="font-size:2.5rem;margin-bottom:10px">👥</div>
        <p>No customers yet. Click <strong>＋ Add Customer</strong> to get started.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table id="custTable">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th><th>Phone</th>
                    <th>Joined</th><th>Orders</th><th>Total Spent</th><th>Status</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>"
                data-email="<?= strtolower(htmlspecialchars($c['email'])) ?>"
                data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>"
                data-status="<?= htmlspecialchars($c['status']) ?>">
                <td style="color:var(--text-3);font-size:.78rem"><?= htmlspecialchars($c['id']) ?></td>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td style="color:var(--text-2)"><?= htmlspecialchars($c['email']) ?></td>
                <td style="color:var(--text-2)"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                <td style="color:var(--text-3);font-size:.8rem"><?= $c['joined'] ?></td>
                <td><span class="badge badge-gray"><?= $c['orders_count'] ?></span></td>
                <td><strong>Rs. <?= number_format($c['total_spent'], 2) ?></strong></td>
                <td><?= custBadge($c['status']) ?></td>
                <td>
                    <div style="display:flex;gap:5px;justify-content:center">
                        <button class="btn btn-ghost btn-sm btn-icon btn-profile"
                                data-id="<?= htmlspecialchars($c['id']) ?>"
                                title="View Profile">👤</button>
                        <button class="btn btn-ghost btn-sm btn-icon btn-edit"
                                data-id="<?= htmlspecialchars($c['id']) ?>"
                                title="Edit">✏️</button>
                        <button class="btn btn-danger btn-sm btn-icon btn-delete"
                                data-id="<?= htmlspecialchars($c['id']) ?>"
                                data-name="<?= htmlspecialchars($c['name']) ?>"
                                title="Delete">🗑️</button>
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
            <div class="modal-title">＋ Add Customer</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" type="text" name="name" placeholder="e.g. John Smith" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input class="form-input" type="email" name="email" placeholder="john@email.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input class="form-input" type="tel" name="phone" placeholder="+94 77 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status">
                            <option value="New">New</option>
                            <option value="Active">Active</option>
                            <option value="VIP">VIP</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Orders</label>
                        <input class="form-input" type="number" name="orders_count" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Spent (Rs.)</label>
                        <input class="form-input" type="number" name="total_spent" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Joined</label>
                        <input class="form-input" type="date" name="joined">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">＋ Add Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Customer</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="customers.php">
                <input type="hidden" name="action"  value="edit">
                <input type="hidden" name="item_id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input class="form-input" type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input class="form-input" type="tel" name="phone" id="edit_phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status" id="edit_status">
                            <option value="New">New</option>
                            <option value="Active">Active</option>
                            <option value="VIP">VIP</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Orders</label>
                        <input class="form-input" type="number" name="orders_count" id="edit_orders" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Spent (Rs.)</label>
                        <input class="form-input" type="number" name="total_spent" id="edit_spent" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Joined</label>
                        <input class="form-input" type="date" name="joined" id="edit_joined">
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

<!-- PROFILE MODAL -->
<div class="modal-backdrop" id="profileModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👤 Customer Profile</div>
            <button class="modal-close" onclick="closeModal('profileModal')">✕</button>
        </div>
        <div class="modal-body" id="profileContent"></div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-body" style="text-align:center;padding:32px 28px 24px">
            <div style="font-size:3rem;margin-bottom:12px">🗑️</div>
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">Delete Customer?</h3>
            <p id="deleteMsg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <form method="POST" action="customers.php">
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
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;
    border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:var(--green-dim);color:var(--green);border:1px solid var(--green)}
.flash-danger {background:var(--red-dim);  color:var(--red);  border:1px solid var(--red)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;
    display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);
    width:100%;max-width:560px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;
    padding:4px;border-radius:6px}
.modal-close:hover{color:var(--text)}
.modal-body{padding:18px 24px 24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
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
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);
    border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;
    font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
</style>

<script>
const CUSTOMERS = <?= $customers_json ?>;

function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target===bd) bd.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key==='Escape')
        document.querySelectorAll('.modal-backdrop.open').forEach(bd => bd.classList.remove('open'));
});

document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;
    const id = btn.dataset.id;
    const c  = id ? CUSTOMERS.find(x => x.id === id) : null;

    if (btn.classList.contains('btn-profile') && c) {
        const stColors = {VIP:'var(--accent)',Active:'var(--green)',New:'var(--blue)',Inactive:'var(--text-3)'};
        document.getElementById('profileContent').innerHTML = `
            <div style="text-align:center;padding:16px 0 24px">
                <div style="width:72px;height:72px;border-radius:50%;background:var(--bg-3);
                            border:2px solid var(--border-l);display:flex;align-items:center;
                            justify-content:center;font-size:1.8rem;margin:0 auto 12px">👤</div>
                <div style="font-family:'DM Serif Display',serif;font-size:1.3rem">${c.name}</div>
                <div style="color:var(--text-3);font-size:.82rem;margin-top:4px">${c.email}</div>
                <span style="display:inline-block;margin-top:8px;padding:3px 12px;border-radius:20px;
                             font-size:.72rem;font-weight:700;background:rgba(255,255,255,.07);
                             color:${stColors[c.status]||'var(--text-2)'}">${c.status}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;
                        padding-top:16px;border-top:1px solid var(--border)">
                ${pRow('📞','Phone',       c.phone||'—')}
                ${pRow('📅','Joined',      c.joined||'—')}
                ${pRow('🧾','Orders',      c.orders_count)}
                ${pRow('💰','Total Spent', 'Rs. '+Number(c.total_spent).toLocaleString())}
            </div>
            <div style="margin-top:14px;display:flex;gap:8px">
                <button class="btn btn-ghost btn-sm" style="flex:1"
                        onclick="closeModal('profileModal');
                                 document.querySelector('.btn-edit[data-id=\\'${c.id}\\']').click()">
                    ✏️ Edit
                </button>
            </div>`;
        openModal('profileModal');
    }

    if (btn.classList.contains('btn-edit') && c) {
        document.getElementById('edit_id').value     = c.id;
        document.getElementById('edit_name').value   = c.name;
        document.getElementById('edit_email').value  = c.email;
        document.getElementById('edit_phone').value  = c.phone    || '';
        document.getElementById('edit_status').value = c.status;
        document.getElementById('edit_orders').value = c.orders_count;
        document.getElementById('edit_spent').value  = c.total_spent;
        document.getElementById('edit_joined').value = c.joined   || '';
        openModal('editModal');
    }

    if (btn.classList.contains('btn-delete')) {
        document.getElementById('delete_id').value = btn.dataset.id;
        document.getElementById('deleteMsg').textContent =
            'This will permanently delete "' + btn.dataset.name + '" and all their data. This cannot be undone.';
        openModal('deleteModal');
    }
});

function pRow(icon, label, value) {
    return `<div style="background:var(--bg-3);border-radius:var(--radius);padding:12px">
        <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;
                    color:var(--text-3);margin-bottom:4px">${icon} ${label}</div>
        <div style="font-size:.85rem;font-weight:600">${value}</div>
    </div>`;
}

function filterCustomers() {
    const q    = document.getElementById('searchCustomers').value.toLowerCase();
    const stat = document.getElementById('filterStatus').value;
    let n = 0;
    document.querySelectorAll('#custTable tbody tr').forEach(row => {
        const show =
            (!q    || row.dataset.name.includes(q) || row.dataset.email.includes(q) || row.dataset.phone.includes(q)) &&
            (!stat || row.dataset.status === stat);
        row.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.getElementById('custCount').textContent = n + ' customers';
}

const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 4000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
