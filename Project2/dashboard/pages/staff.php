<?php
// ============================================================
//  STAFF MANAGEMENT — Minmi Restaurent Admin
//  Place in: dashboard/pages/staff.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$action   = $_POST['action']  ?? '';
$item_id  = $_POST['item_id'] ?? '';
$msg      = '';
$msg_type = 'success';

if ($action === 'add') {
    $new_id = 'STF-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    $parts  = explode(' ', trim($_POST['name']));
    $avatar = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
    $colors = ['#e8622a','#f5c842','#3ecf8e','#4e9cf7','#a855f7','#f472b6'];
    $color  = $colors[array_rand($colors)];
    $pdo->prepare("
        INSERT INTO staff (id,name,role,dept,email,phone,joined,schedule,status,salary,rating,avatar,color)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $new_id, trim($_POST['name']), trim($_POST['role']), $_POST['dept'],
        trim($_POST['email']), trim($_POST['phone'] ?? ''),
        $_POST['joined'] ?: date('Y-m-d'), $_POST['schedule'], $_POST['status'],
        (int)$_POST['salary'], (float)($_POST['rating'] ?: 0), $avatar, $color,
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" added to staff!';
}

if ($action === 'edit' && $item_id) {
    $pdo->prepare("
        UPDATE staff SET name=?,role=?,dept=?,email=?,phone=?,joined=?,schedule=?,status=?,salary=?,rating=?
        WHERE id=?
    ")->execute([
        trim($_POST['name']), trim($_POST['role']), $_POST['dept'],
        trim($_POST['email']), trim($_POST['phone'] ?? ''),
        $_POST['joined'], $_POST['schedule'], $_POST['status'],
        (int)$_POST['salary'], (float)($_POST['rating'] ?: 0), $item_id,
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" updated!';
}

if ($action === 'delete' && $item_id) {
    $row = $pdo->prepare("SELECT name FROM staff WHERE id=?");
    $row->execute([$item_id]);
    $del_name = $row->fetchColumn() ?: $item_id;
    $pdo->prepare("DELETE FROM staff WHERE id=?")->execute([$item_id]);
    $msg      = '🗑️ "' . htmlspecialchars($del_name) . '" removed.';
    $msg_type = 'danger';
}

$staff = $pdo->query("SELECT * FROM staff ORDER BY dept, name")->fetchAll();

$page_title    = 'Staff Management';
$on_duty       = count(array_filter($staff, fn($s) => $s['status'] === 'On Duty'));
$off_duty      = count(array_filter($staff, fn($s) => $s['status'] === 'Off Duty'));
$on_leave      = count(array_filter($staff, fn($s) => $s['status'] === 'Leave'));
$total_payroll = array_sum(array_column($staff, 'salary'));
$avg_rating    = count($staff) ? round(array_sum(array_column($staff, 'rating')) / count($staff), 1) : 0;
$dept_counts   = count($staff) ? array_count_values(array_column($staff, 'dept')) : [];
$dept_labels   = json_encode(array_keys($dept_counts));
$dept_vals     = json_encode(array_values($dept_counts));
$salary_names  = json_encode(array_column(array_slice($staff, 0, 8), 'name'));
$salary_vals   = json_encode(array_column(array_slice($staff, 0, 8), 'salary'));
$staff_json    = json_encode($staff);

$page_scripts = "
if(typeof buildDoughnutChart==='function') buildDoughnutChart('deptChart', {$dept_labels}, {$dept_vals}, ['#e8622a','#4e9cf7','#3ecf8e','#a855f7']);
if(typeof buildBarChart==='function') buildBarChart('salaryChart', {$salary_names}, {$salary_vals}, '#f5c842');
";

function statusBadge(string $s): string {
    return match($s) { 'On Duty'=>'badge-green', 'Off Duty'=>'badge-gray', 'Leave'=>'badge-yellow', default=>'badge-gray' };
}
function deptBadge(string $d): string {
    return match($d) { 'Kitchen'=>'badge-orange', 'Front'=>'badge-blue', 'Bar'=>'badge-purple', 'Management'=>'badge-pink', default=>'badge-gray' };
}
function starStr(float $r): string {
    return str_repeat('★', (int)floor($r)) . str_repeat('☆', 5-(int)floor($r));
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
        <h1 style="font-family:'DM Serif Display',serif;font-size:1.9rem;font-weight:400;letter-spacing:-.03em">Staff Management</h1>
        <p style="color:var(--text-2);font-size:.85rem;margin-top:4px">Manage your team — roles, schedules, performance &amp; payroll.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <div style="display:flex;border:1px solid var(--border-l);border-radius:var(--radius);overflow:hidden">
            <button class="view-btn active" id="viewTableBtn" onclick="switchView('table')"
                    style="padding:7px 14px;background:var(--accent);color:#fff;border:none;cursor:pointer;font-size:.82rem">☰ Table</button>
            <button class="view-btn" id="viewCardBtn" onclick="switchView('card')"
                    style="padding:7px 14px;background:transparent;color:var(--text-2);border:none;cursor:pointer;font-size:.82rem">⊞ Cards</button>
        </div>
        <button class="btn btn-primary" onclick="openModal('addModal')">＋ Add Staff Member</button>
    </div>
</div>

<!-- KPI STATS -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon">👨‍🍳</div>
        <div class="stat-label">Total Staff</div>
        <div class="stat-value"><?= count($staff) ?></div>
        <div class="stat-sub">Across <?= count($dept_counts) ?> departments</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-label">On Duty Today</div>
        <div class="stat-value"><?= $on_duty ?></div>
        <div class="stat-sub"><?= $off_duty ?> off shift</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon">🏖️</div>
        <div class="stat-label">On Leave</div>
        <div class="stat-value"><?= $on_leave ?></div>
        <div class="stat-sub">Currently absent</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">💵</div>
        <div class="stat-label">Monthly Payroll</div>
        <div class="stat-value">Rs. <?= number_format($total_payroll/1000, 1) ?>k</div>
        <div class="stat-sub">Rs. <?= number_format($total_payroll) ?> total</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon">⭐</div>
        <div class="stat-label">Avg Performance</div>
        <div class="stat-value"><?= $avg_rating ?></div>
        <div class="stat-sub">Out of 5.0</div>
    </div>
</div>

<?php if (!empty($staff)): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Staff by Department</div>
            <span class="badge badge-blue"><?= count($staff) ?> total</span>
        </div>
        <div class="chart-box"><canvas id="deptChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">Monthly Salary Distribution</div>
            <span class="badge badge-orange">Top 8</span>
        </div>
        <div class="chart-box"><canvas id="salaryChart"></canvas></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">Department Performance Rating</div></div>
    <?php
    $dept_ratings = [];
    foreach ($staff as $s) $dept_ratings[$s['dept']][] = $s['rating'];
    foreach ($dept_ratings as $dept => $ratings):
        $avg = round(array_sum($ratings)/count($ratings),1);
        $pct = round(($avg/5)*100);
        $col = $avg>=4.7?'var(--green)':($avg>=4.3?'var(--yellow)':'var(--accent)');
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
        <span style="min-width:100px;font-size:.84rem;font-weight:500"><?= htmlspecialchars($dept) ?></span>
        <div style="flex:1;height:8px;background:var(--bg-3);border-radius:4px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:4px"></div>
        </div>
        <span style="font-size:.82rem;font-weight:700;min-width:40px;text-align:right"><?= $avg ?>/5</span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- FILTER BAR -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
    <input type="text" class="search-input" id="staffSearch"
           placeholder="🔍  Search by name, role, email…" oninput="filterStaff()" style="flex:1;min-width:200px">
    <select class="search-input" id="deptFilter" style="min-width:140px" onchange="filterStaff()">
        <option value="">All Departments</option>
        <option>Kitchen</option><option>Front</option><option>Bar</option><option>Management</option>
    </select>
    <select class="search-input" id="statusFilter" style="min-width:140px" onchange="filterStaff()">
        <option value="">All Statuses</option>
        <option>On Duty</option><option>Off Duty</option><option>Leave</option>
    </select>
</div>

<!-- TABLE VIEW -->
<div id="tableView">
    <div class="card">
        <div class="card-header">
            <div class="card-title">All Staff Members</div>
            <span class="badge badge-gray" id="staffCount"><?= count($staff) ?> members</span>
        </div>
        <?php if (empty($staff)): ?>
        <div style="text-align:center;padding:48px;color:var(--text-3)">
            <div style="font-size:2.5rem;margin-bottom:10px">👨‍🍳</div>
            <p>No staff yet. Click <strong>＋ Add Staff Member</strong> to get started.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table id="staffTable">
                <thead>
                    <tr>
                        <th>Staff Member</th><th>Role</th><th>Department</th>
                        <th>Schedule</th><th>Status</th><th>Performance</th>
                        <th>Salary/mo</th><th>Joined</th>
                        <th style="text-align:center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staff as $s): ?>
                <tr data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>"
                    data-role="<?= strtolower(htmlspecialchars($s['role'])) ?>"
                    data-email="<?= strtolower(htmlspecialchars($s['email'])) ?>"
                    data-dept="<?= htmlspecialchars($s['dept']) ?>"
                    data-status="<?= htmlspecialchars($s['status']) ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:36px;height:36px;border-radius:50%;background:<?= htmlspecialchars($s['color']) ?>;
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:.8rem;font-weight:700;color:#fff;flex-shrink:0">
                                <?= htmlspecialchars($s['avatar']) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($s['name']) ?></div>
                                <div style="font-size:.72rem;color:var(--text-3)"><?= htmlspecialchars($s['id']) ?> · <?= htmlspecialchars($s['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:.83rem"><?= htmlspecialchars($s['role']) ?></td>
                    <td><span class="badge <?= deptBadge($s['dept']) ?>"><?= $s['dept'] ?></span></td>
                    <td style="color:var(--text-2);font-size:.8rem"><?= $s['schedule'] ?></td>
                    <td><span class="badge <?= statusBadge($s['status']) ?>"><?= $s['status'] ?></span></td>
                    <td>
                        <span style="color:#f5c842;letter-spacing:1px;font-size:.85rem"><?= starStr((float)$s['rating']) ?></span>
                        <span style="font-size:.75rem;color:var(--text-3);margin-left:4px"><?= $s['rating'] ?></span>
                    </td>
                    <td><strong>Rs. <?= number_format($s['salary']) ?></strong></td>
                    <td style="color:var(--text-3);font-size:.8rem"><?= $s['joined'] ?></td>
                    <td>
                        <div style="display:flex;gap:6px;justify-content:center">
                            <button class="btn btn-ghost btn-sm btn-icon btn-edit"
                                    data-id="<?= htmlspecialchars($s['id']) ?>" title="Edit">✏️</button>
                            <button class="btn btn-ghost btn-sm btn-icon btn-profile"
                                    data-id="<?= htmlspecialchars($s['id']) ?>" title="Profile">👤</button>
                            <button class="btn btn-danger btn-sm btn-icon btn-delete"
                                    data-id="<?= htmlspecialchars($s['id']) ?>"
                                    data-name="<?= htmlspecialchars($s['name']) ?>" title="Delete">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- CARD VIEW -->
<div id="cardView" style="display:none">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px" id="staffCardsGrid">
    <?php foreach ($staff as $s): ?>
    <div class="card staff-card"
         style="border-top:3px solid <?= htmlspecialchars($s['color']) ?>"
         data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>"
         data-role="<?= strtolower(htmlspecialchars($s['role'])) ?>"
         data-email="<?= strtolower(htmlspecialchars($s['email'])) ?>"
         data-dept="<?= htmlspecialchars($s['dept']) ?>"
         data-status="<?= htmlspecialchars($s['status']) ?>">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
            <div style="width:48px;height:48px;border-radius:50%;background:<?= htmlspecialchars($s['color']) ?>;
                        display:flex;align-items:center;justify-content:center;
                        font-size:1rem;font-weight:700;color:#fff;flex-shrink:0">
                <?= htmlspecialchars($s['avatar']) ?>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size:.75rem;color:var(--text-3)"><?= htmlspecialchars($s['role']) ?></div>
            </div>
            <span class="badge <?= statusBadge($s['status']) ?>"><?= $s['status'] ?></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;font-size:.78rem">
            <div><span style="color:var(--text-3)">Dept</span><br><strong><?= $s['dept'] ?></strong></div>
            <div><span style="color:var(--text-3)">Schedule</span><br><strong><?= $s['schedule'] ?></strong></div>
            <div><span style="color:var(--text-3)">Salary/mo</span><br><strong>Rs. <?= number_format($s['salary']) ?></strong></div>
            <div><span style="color:var(--text-3)">Joined</span><br><strong><?= $s['joined'] ?></strong></div>
        </div>
        <div style="margin-bottom:14px;font-size:.82rem">
            <span style="color:#f5c842;letter-spacing:1px"><?= starStr((float)$s['rating']) ?></span>
            <span style="color:var(--text-3);margin-left:4px"><?= $s['rating'] ?>/5</span>
        </div>
        <div style="display:flex;gap:6px">
            <button class="btn btn-ghost btn-sm btn-edit" data-id="<?= htmlspecialchars($s['id']) ?>" style="flex:1">✏️ Edit</button>
            <button class="btn btn-ghost btn-sm btn-profile" data-id="<?= htmlspecialchars($s['id']) ?>" style="flex:1">👤 Profile</button>
            <button class="btn btn-danger btn-sm btn-icon btn-delete"
                    data-id="<?= htmlspecialchars($s['id']) ?>"
                    data-name="<?= htmlspecialchars($s['name']) ?>">🗑️</button>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- PAYROLL SUMMARY -->
<?php if (!empty($staff)): ?>
<div class="card" style="margin-top:16px">
    <div class="card-header">
        <div class="card-title">Monthly Payroll Summary</div>
        <span class="badge badge-orange">Auto-calculated</span>
    </div>
    <?php
    $dept_pay = [];
    foreach ($staff as $s) $dept_pay[$s['dept']] = ($dept_pay[$s['dept']] ?? 0) + $s['salary'];
    arsort($dept_pay);
    foreach ($dept_pay as $dept => $total):
        $cnt = count(array_filter($staff, fn($s) => $s['dept'] === $dept));
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:.84rem">
        <span style="color:var(--text-2)"><?= htmlspecialchars($dept) ?> Department (<?= $cnt ?> staff)</span>
        <strong>Rs. <?= number_format($total) ?></strong>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0 0;font-size:.9rem;font-weight:700">
        <span>Total Monthly Payroll</span>
        <span style="color:var(--accent);font-size:1.1rem">Rs. <?= number_format($total_payroll) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- ADD MODAL -->
<div class="modal-backdrop" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">＋ Add Staff Member</div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="staff.php">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" type="text" name="name" placeholder="e.g. John Smith" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role / Position *</label>
                        <input class="form-input" type="text" name="role" placeholder="e.g. Sous Chef" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select class="form-input" name="dept" required>
                            <option value="">— Select —</option>
                            <option>Kitchen</option><option>Front</option>
                            <option>Bar</option><option>Management</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input class="form-input" type="email" name="email" placeholder="name@minmi.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-input" type="tel" name="phone" placeholder="+94 77 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Salary (Rs.) *</label>
                        <input class="form-input" type="number" name="salary" min="0" placeholder="25000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Schedule</label>
                        <select class="form-input" name="schedule">
                            <option>Mon–Fri</option><option>Tue–Sat</option>
                            <option>Wed–Sun</option><option>Thu–Mon</option>
                            <option>Fri–Tue</option><option>Weekends</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status">
                            <option>On Duty</option><option>Off Duty</option><option>Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rating (1–5)</label>
                        <input class="form-input" type="number" name="rating" min="0" max="5" step="0.1" placeholder="4.5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Joined</label>
                        <input class="form-input" type="date" name="joined">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">＋ Add Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Staff Member</div>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="staff.php">
                <input type="hidden" name="action"  value="edit">
                <input type="hidden" name="item_id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input class="form-input" type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role / Position *</label>
                        <input class="form-input" type="text" name="role" id="edit_role" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select class="form-input" name="dept" id="edit_dept" required>
                            <option value="">— Select —</option>
                            <option>Kitchen</option><option>Front</option>
                            <option>Bar</option><option>Management</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input class="form-input" type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input class="form-input" type="tel" name="phone" id="edit_phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Salary (Rs.) *</label>
                        <input class="form-input" type="number" name="salary" id="edit_salary" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Schedule</label>
                        <select class="form-input" name="schedule" id="edit_schedule">
                            <option>Mon–Fri</option><option>Tue–Sat</option>
                            <option>Wed–Sun</option><option>Thu–Mon</option>
                            <option>Fri–Tue</option><option>Weekends</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status" id="edit_status">
                            <option>On Duty</option><option>Off Duty</option><option>Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rating (1–5)</label>
                        <input class="form-input" type="number" name="rating" id="edit_rating" min="0" max="5" step="0.1">
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
            <div class="modal-title">👤 Staff Profile</div>
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
            <h3 style="font-family:'DM Serif Display',serif;font-size:1.2rem;margin-bottom:8px">Remove Staff Member?</h3>
            <p id="deleteMsg" style="color:var(--text-2);font-size:.85rem;line-height:1.6;margin-bottom:20px"></p>
            <form method="POST" action="staff.php">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="item_id" id="delete_id">
                <div style="display:flex;gap:10px;justify-content:center">
                    <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Remove</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:rgba(62,207,142,.1);color:#3ecf8e;border:1px solid #3ecf8e}
.flash-danger{background:rgba(232,66,66,.1);color:#e84242;border:1px solid #e84242}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);width:100%;max-width:580px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;padding:4px;border-radius:6px}
.modal-close:hover{color:var(--text)}
.modal-body{padding:18px 24px 24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-3)}
.form-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:9px 13px;font-family:'DM Sans',sans-serif;font-size:.85rem;outline:none;transition:border-color .2s;width:100%}
.form-input:focus{border-color:var(--accent)}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)}
.search-input{background:var(--bg-3);border:1px solid var(--border-l);color:var(--text);border-radius:var(--radius);padding:8px 13px;font-family:'DM Sans',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
</style>

<script>
const STAFF_DATA  = <?= $staff_json ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
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

    if (btn.classList.contains('btn-edit')) {
        const s = STAFF_DATA.find(x => x.id === btn.dataset.id);
        if (!s) return;
        document.getElementById('edit_id').value       = s.id;
        document.getElementById('edit_name').value     = s.name;
        document.getElementById('edit_role').value     = s.role;
        document.getElementById('edit_dept').value     = s.dept;
        document.getElementById('edit_email').value    = s.email;
        document.getElementById('edit_phone').value    = s.phone    || '';
        document.getElementById('edit_salary').value   = s.salary;
        document.getElementById('edit_schedule').value = s.schedule;
        document.getElementById('edit_status').value   = s.status;
        document.getElementById('edit_rating').value   = s.rating;
        document.getElementById('edit_joined').value   = s.joined   || '';
        openModal('editModal');
    }

    if (btn.classList.contains('btn-profile')) {
        const s = STAFF_DATA.find(x => x.id === btn.dataset.id);
        if (!s) return;
        const stars = '★'.repeat(Math.floor(s.rating)) + '☆'.repeat(5 - Math.floor(s.rating));
        const stColor = s.status==='On Duty'?'#3ecf8e':s.status==='Leave'?'#f5c842':'#888';
        document.getElementById('profileContent').innerHTML = `
            <div style="text-align:center;padding:16px 0 24px">
                <div style="width:80px;height:80px;border-radius:50%;background:${s.color};
                            display:flex;align-items:center;justify-content:center;
                            font-size:1.6rem;font-weight:700;color:#fff;margin:0 auto 12px">
                    ${s.avatar}
                </div>
                <div style="font-family:'DM Serif Display',serif;font-size:1.4rem">${s.name}</div>
                <div style="color:var(--text-3);font-size:.85rem;margin-top:4px">${s.role} · ${s.dept}</div>
                <span style="display:inline-block;margin-top:8px;padding:4px 14px;border-radius:20px;
                             font-size:.72rem;font-weight:700;background:rgba(255,255,255,.07);
                             color:${stColor}">${s.status}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding-top:16px;border-top:1px solid var(--border)">
                ${pRow('📧','Email',    s.email)}
                ${pRow('📞','Phone',    s.phone || '—')}
                ${pRow('🗓️','Schedule', s.schedule)}
                ${pRow('📅','Joined',   s.joined || '—')}
                ${pRow('💵','Salary',   'Rs. '+Number(s.salary).toLocaleString()+'/mo')}
                ${pRow('⭐','Rating',   stars + ' ' + s.rating)}
            </div>`;
        openModal('profileModal');
    }

    if (btn.classList.contains('btn-delete')) {
        document.getElementById('delete_id').value = btn.dataset.id;
        document.getElementById('deleteMsg').textContent =
            'This will permanently remove "' + btn.dataset.name + '" from the system. This cannot be undone.';
        openModal('deleteModal');
    }
});

function pRow(icon, label, value) {
    return `<div style="background:var(--bg-3);border-radius:var(--radius);padding:12px">
        <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:4px">${icon} ${label}</div>
        <div style="font-size:.84rem;font-weight:600">${value}</div>
    </div>`;
}

function switchView(view) {
    document.getElementById('tableView').style.display = view==='table' ? 'block' : 'none';
    document.getElementById('cardView').style.display  = view==='card'  ? 'block' : 'none';
    document.getElementById('viewTableBtn').style.background = view==='table' ? 'var(--accent)' : 'transparent';
    document.getElementById('viewTableBtn').style.color      = view==='table' ? '#fff' : 'var(--text-2)';
    document.getElementById('viewCardBtn').style.background  = view==='card'  ? 'var(--accent)' : 'transparent';
    document.getElementById('viewCardBtn').style.color       = view==='card'  ? '#fff' : 'var(--text-2)';
}

function filterStaff() {
    const q    = document.getElementById('staffSearch').value.toLowerCase();
    const dept = document.getElementById('deptFilter').value;
    const stat = document.getElementById('statusFilter').value;
    let n = 0;
    document.querySelectorAll('#staffTable tbody tr').forEach(row => {
        const show =
            (!q    || row.dataset.name.includes(q) || row.dataset.role.includes(q) || row.dataset.email.includes(q)) &&
            (!dept || row.dataset.dept   === dept) &&
            (!stat || row.dataset.status === stat);
        row.style.display = show ? '' : 'none';
        if (show) n++;
    });
    document.querySelectorAll('.staff-card').forEach(card => {
        const show =
            (!q    || card.dataset.name.includes(q) || card.dataset.role.includes(q) || card.dataset.email.includes(q)) &&
            (!dept || card.dataset.dept   === dept) &&
            (!stat || card.dataset.status === stat);
        card.style.display = show ? '' : 'none';
    });
    document.getElementById('staffCount').textContent = n + ' members';
}

const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 4000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>