<?php
// ============================================================
//  STAFF MANAGEMENT — Minmi Restaurent Admin
//  Place in: dashboard/pages/staff.php
// ============================================================

require_once __DIR__ . '/../includes/db.php';

// ════════════════════════════════════════
//  HANDLE POST ACTIONS
// ════════════════════════════════════════
$action   = $_POST['action']  ?? '';
$item_id  = $_POST['item_id'] ?? '';
$msg      = '';
$msg_type = 'success';

// ── ADD ───────────────────────────────────────────────────
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
        $new_id,
        trim($_POST['name']),
        trim($_POST['role']),
        $_POST['dept'],
        trim($_POST['email']),
        trim($_POST['phone'] ?? ''),
        $_POST['joined'] ?: date('Y-m-d'),
        $_POST['schedule'],
        $_POST['status'],
        (int)$_POST['salary'],
        (float)($_POST['rating'] ?: 0),
        $avatar,
        $color,
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" added to staff!';
}

// ── EDIT ──────────────────────────────────────────────────
if ($action === 'edit' && $item_id) {
    $pdo->prepare("
        UPDATE staff SET name=?,role=?,dept=?,email=?,phone=?,joined=?,schedule=?,status=?,salary=?,rating=?
        WHERE id=?
    ")->execute([
        trim($_POST['name']),
        trim($_POST['role']),
        $_POST['dept'],
        trim($_POST['email']),
        trim($_POST['phone'] ?? ''),
        $_POST['joined'],
        $_POST['schedule'],
        $_POST['status'],
        (int)$_POST['salary'],
        (float)($_POST['rating'] ?: 0),
        $item_id,
    ]);
    $msg = '✅ "' . htmlspecialchars($_POST['name']) . '" updated!';
}

// ── DELETE ────────────────────────────────────────────────
if ($action === 'delete' && $item_id) {
    $row = $pdo->prepare("SELECT name FROM staff WHERE id=?");
    $row->execute([$item_id]);
    $del_name = $row->fetchColumn() ?: $item_id;
    $pdo->prepare("DELETE FROM staff WHERE id=?")->execute([$item_id]);
    $msg      = '🗑️ "' . htmlspecialchars($del_name) . '" removed.';
    $msg_type = 'danger';
}

// ════════════════════════════════════════
//  FETCH FRESH DATA
// ════════════════════════════════════════
$staff = $pdo->query("SELECT * FROM staff ORDER BY dept, name")->fetchAll();

$page_title = 'Staff Management';
$extra_css  = '../assets/css/staff.css';

$on_duty       = count(array_filter($staff, fn($s) => $s['status'] === 'On Duty'));
$off_duty      = count(array_filter($staff, fn($s) => $s['status'] === 'Off Duty'));
$on_leave      = count(array_filter($staff, fn($s) => $s['status'] === 'Leave'));
$total_payroll = array_sum(array_column($staff, 'salary'));
$avg_rating    = count($staff) ? round(array_sum(array_column($staff, 'rating')) / count($staff), 1) : 0;
$dept_counts   = count($staff) ? array_count_values(array_column($staff, 'dept')) : [];

$dept_labels  = json_encode(array_keys($dept_counts));
$dept_vals    = json_encode(array_values($dept_counts));
$salary_names = json_encode(array_column(array_slice($staff, 0, 8), 'name'));
$salary_vals  = json_encode(array_column(array_slice($staff, 0, 8), 'salary'));
// Pass full staff data as JSON — buttons will use this instead of inline params
$staff_json   = json_encode($staff);

function statusBadge(string $s): string {
    return match($s) {
        'On Duty'  => 'badge-green',
        'Off Duty' => 'badge-gray',
        'Leave'    => 'badge-yellow',
        default    => 'badge-gray'
    };
}
function deptBadge(string $d): string {
    return match($d) {
        'Kitchen'    => 'badge-orange',
        'Front'      => 'badge-blue',
        'Bar'        => 'badge-purple',
        'Management' => 'badge-pink',
        default      => 'badge-gray'
    };
}
function starStr(float $r): string {
    return str_repeat('★', (int)floor($r)) . str_repeat('☆', 5-(int)floor($r));
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ═══════════════════════════════════════
     FLASH MESSAGE
     ═══════════════════════════════════════ -->
<?php if ($msg): ?>
<div class="flash-msg flash-<?= $msg_type ?>" id="flashMsg">
    <?= $msg ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:12px">✕</button>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     PAGE HEADER
     ═══════════════════════════════════════ -->
<div class="page-header anim-1">
    <div class="page-header-text">
        <h1>Staff Management</h1>
        <p>Manage your team — roles, schedules, performance &amp; payroll.</p>
    </div>
    <div class="page-header-actions">
        <div class="view-toggle">
            <button class="view-btn active" id="viewTableBtn" onclick="switchView('table')">☰ Table</button>
            <button class="view-btn"        id="viewCardBtn"  onclick="switchView('card')">⊞ Cards</button>
        </div>
        <button class="btn btn-primary" onclick="openModal('addModal')">＋ Add Staff Member</button>
    </div>
</div>

<!-- ═══════════════════════════════════════
     KPI STATS
     ═══════════════════════════════════════ -->
<div class="stats-grid anim-2">
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
        <div class="stat-value">$<?= number_format($total_payroll/1000,1) ?>k</div>
        <div class="stat-sub">$<?= number_format($total_payroll) ?> total</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon">⭐</div>
        <div class="stat-label">Avg Performance</div>
        <div class="stat-value"><?= $avg_rating ?></div>
        <div class="stat-sub">Out of 5.0</div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     CHARTS
     ═══════════════════════════════════════ -->
<?php if (!empty($staff)): ?>
<div class="grid-2 anim-3">
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

<!-- DEPARTMENT PERFORMANCE -->
<div class="card anim-4">
    <div class="card-header"><div class="card-title">Department Performance Rating</div></div>
    <?php
    $dept_ratings = [];
    foreach ($staff as $s) $dept_ratings[$s['dept']][] = $s['rating'];
    foreach ($dept_ratings as $dept => $ratings):
        $avg = round(array_sum($ratings)/count($ratings),1);
        $pct = round(($avg/5)*100);
        $col = $avg>=4.7?'var(--green)':($avg>=4.3?'var(--yellow)':'var(--accent)');
    ?>
    <div class="progress-row">
        <span class="progress-label"><?= htmlspecialchars($dept) ?></span>
        <div class="progress-bar-wrap">
            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
        </div>
        <span class="progress-val"><?= $avg ?>/5</span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     FILTER BAR
     ═══════════════════════════════════════ -->
<div class="filter-bar anim-5">
    <input type="text" class="search-input" id="staffSearch"
           placeholder="🔍  Search by name, role, email…" oninput="filterStaff()">
    <select class="search-input" id="deptFilter" style="min-width:140px" onchange="filterStaff()">
        <option value="">All Departments</option>
        <option>Kitchen</option><option>Front</option>
        <option>Bar</option><option>Management</option>
    </select>
    <select class="search-input" id="statusFilter" style="min-width:140px" onchange="filterStaff()">
        <option value="">All Statuses</option>
        <option>On Duty</option><option>Off Duty</option><option>Leave</option>
    </select>
</div>

<!-- ═══════════════════════════════════════
     TABLE VIEW
     ═══════════════════════════════════════ -->
<div id="tableView" class="anim-6">
    <div class="card" style="margin-bottom:0">
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
                        <div class="staff-cell">
                            <div class="staff-avatar" style="background:<?= htmlspecialchars($s['color']) ?>"><?= htmlspecialchars($s['avatar']) ?></div>
                            <div>
                                <div class="staff-name"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="staff-id"><?= htmlspecialchars($s['id']) ?> · <?= htmlspecialchars($s['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($s['role']) ?></td>
                    <td><span class="badge <?= deptBadge($s['dept']) ?>"><?= $s['dept'] ?></span></td>
                    <td style="color:var(--text-2);font-size:.8rem"><?= $s['schedule'] ?></td>
                    <td><span class="badge <?= statusBadge($s['status']) ?>"><?= $s['status'] ?></span></td>
                    <td>
                        <span class="star-rating"><?= starStr((float)$s['rating']) ?></span>
                        <span class="star-val"><?= $s['rating'] ?></span>
                    </td>
                    <td><strong>$<?= number_format($s['salary']) ?></strong></td>
                    <td style="color:var(--text-3);font-size:.8rem"><?= $s['joined'] ?></td>
                    <td>
                        <div style="display:flex;gap:6px;justify-content:center">
                            <!-- KEY FIX: use data-id attribute, not onclick params -->
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

<!-- ═══════════════════════════════════════
     CARD VIEW
     ═══════════════════════════════════════ -->
<div id="cardView" style="display:none">
    <div class="staff-cards" id="staffCardsGrid">
    <?php foreach ($staff as $s): ?>
    <div class="staff-card"
         style="border-top:2px solid <?= htmlspecialchars($s['color']) ?>"
         data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>"
         data-role="<?= strtolower(htmlspecialchars($s['role'])) ?>"
         data-email="<?= strtolower(htmlspecialchars($s['email'])) ?>"
         data-dept="<?= htmlspecialchars($s['dept']) ?>"
         data-status="<?= htmlspecialchars($s['status']) ?>">
        <div class="card-top">
            <div class="avatar-lg" style="background:<?= htmlspecialchars($s['color']) ?>"><?= htmlspecialchars($s['avatar']) ?></div>
            <div>
                <div class="name"><?= htmlspecialchars($s['name']) ?></div>
                <div class="role"><?= htmlspecialchars($s['role']) ?></div>
            </div>
            <div style="margin-left:auto">
                <span class="badge <?= statusBadge($s['status']) ?>"><?= $s['status'] ?></span>
            </div>
        </div>
        <div class="card-meta">
            <div class="meta-item"><label>Department</label><span><?= $s['dept'] ?></span></div>
            <div class="meta-item"><label>Schedule</label><span><?= $s['schedule'] ?></span></div>
            <div class="meta-item"><label>Salary/mo</label><span><strong>$<?= number_format($s['salary']) ?></strong></span></div>
            <div class="meta-item"><label>Joined</label><span><?= $s['joined'] ?></span></div>
            <div class="meta-item" style="grid-column:1/-1">
                <label>Performance</label>
                <span class="star-rating"><?= starStr((float)$s['rating']) ?></span>
                <span class="star-val"><?= $s['rating'] ?></span>
            </div>
        </div>
        <div class="card-actions">
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

<!-- ═══════════════════════════════════════
     PAYROLL SUMMARY
     ═══════════════════════════════════════ -->
<?php if (!empty($staff)): ?>
<div class="card" style="margin-top:20px">
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
    <div class="payroll-row">
        <span><?= htmlspecialchars($dept) ?> Department (<?= $cnt ?> staff)</span>
        <strong>$<?= number_format($total) ?></strong>
    </div>
    <?php endforeach; ?>
    <div class="payroll-row payroll-total">
        <span>Total Monthly Payroll</span>
        <span class="payroll-grand">$<?= number_format($total_payroll) ?></span>
    </div>
</div>
<?php endif; ?>


<!-- ═══════════════════════════════════════
     ADD MODAL
     ═══════════════════════════════════════ -->
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
                        <input class="form-input" type="tel" name="phone" placeholder="+1 555-0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Salary ($) *</label>
                        <input class="form-input" type="number" name="salary" min="0" placeholder="2500" required>
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


<!-- ═══════════════════════════════════════
     EDIT MODAL
     ═══════════════════════════════════════ -->
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
                        <label class="form-label">Monthly Salary ($) *</label>
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


<!-- ═══════════════════════════════════════
     PROFILE MODAL
     ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="profileModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">👤 Staff Profile</div>
            <button class="modal-close" onclick="closeModal('profileModal')">✕</button>
        </div>
        <div class="modal-body" id="profileContent"></div>
    </div>
</div>


<!-- ═══════════════════════════════════════
     DELETE MODAL
     ═══════════════════════════════════════ -->
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

<div class="toast" id="toast"></div>

<!-- ═══════════════════════════════════════
     STYLES
     ═══════════════════════════════════════ -->
<style>
.flash-msg{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;
    border-radius:var(--radius);margin-bottom:20px;font-size:.85rem;font-weight:600}
.flash-success{background:var(--green-dim);color:var(--green);border:1px solid var(--green)}
.flash-danger{background:var(--red-dim);color:var(--red);border:1px solid var(--red)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;
    display:none;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
.modal-backdrop.open{display:flex}
.modal{background:var(--bg-2);border:1px solid var(--border-l);border-radius:var(--radius-lg);
    width:100%;max-width:580px;max-height:90vh;overflow-y:auto;animation:modalIn .25s ease}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(12px)}to{opacity:1;transform:none}}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0}
.modal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;font-weight:400}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;font-size:1.2rem;
    padding:4px;border-radius:6px;transition:color .2s}
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
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;
    padding-top:16px;border-top:1px solid var(--border)}
.btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
</style>


<!-- ═══════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════ -->
<script>
// ── Staff data from PHP (safe JSON, no quote issues) ──────
const STAFF_DATA   = <?= $staff_json ?>;
const DEPT_LABELS  = <?= $dept_labels ?>;
const DEPT_VALS    = <?= $dept_vals ?>;
const SALARY_NAMES = <?= $salary_names ?>;
const SALARY_VALS  = <?= $salary_vals ?>;

// ── Modal helpers ─────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop').forEach(bd =>
    bd.addEventListener('click', e => { if (e.target===bd) bd.classList.remove('open'); })
);
document.addEventListener('keydown', e => {
    if (e.key==='Escape')
        document.querySelectorAll('.modal-backdrop.open').forEach(bd => bd.classList.remove('open'));
});

// ════════════════════════════════════════
//  BUTTON EVENT LISTENERS
//  Using data-id to look up staff from STAFF_DATA
//  This avoids ALL quote-breaking issues
// ════════════════════════════════════════
document.addEventListener('click', function(e) {
    const btn = e.target.closest('button');
    if (!btn) return;

    // ── EDIT button ───────────────────────────────────────
    if (btn.classList.contains('btn-edit')) {
        const id = btn.dataset.id;
        const s  = STAFF_DATA.find(x => x.id === id);
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

    // ── PROFILE button ────────────────────────────────────
    if (btn.classList.contains('btn-profile')) {
        const id = btn.dataset.id;
        const s  = STAFF_DATA.find(x => x.id === id);
        if (!s) return;
        const stars = '★'.repeat(Math.floor(s.rating)) + '☆'.repeat(5 - Math.floor(s.rating));
        const stColor = s.status==='On Duty'?'var(--green)':s.status==='Leave'?'var(--yellow)':'var(--text-3)';
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
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;
                        padding-top:16px;border-top:1px solid var(--border)">
                ${pRow('📧','Email',    s.email)}
                ${pRow('📞','Phone',    s.phone || '—')}
                ${pRow('🗓️','Schedule', s.schedule)}
                ${pRow('📅','Joined',   s.joined || '—')}
                ${pRow('💵','Salary',   '$'+Number(s.salary).toLocaleString()+'/mo')}
                ${pRow('⭐','Rating',   stars + ' ' + s.rating)}
            </div>`;
        openModal('profileModal');
    }

    // ── DELETE button ─────────────────────────────────────
    if (btn.classList.contains('btn-delete')) {
        const id   = btn.dataset.id;
        const name = btn.dataset.name;
        document.getElementById('delete_id').value    = id;
        document.getElementById('deleteMsg').textContent =
            'This will permanently remove "' + name + '" from the system. This cannot be undone.';
        openModal('deleteModal');
    }
});

function pRow(icon, label, value) {
    return `<div style="background:var(--bg-3);border-radius:var(--radius);padding:12px">
        <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;
                    color:var(--text-3);margin-bottom:4px">${icon} ${label}</div>
        <div style="font-size:.84rem;font-weight:600">${value}</div>
    </div>`;
}

// ── View toggle ───────────────────────────────────────────
function switchView(view) {
    document.getElementById('tableView').style.display = view==='table' ? 'block' : 'none';
    document.getElementById('cardView').style.display  = view==='card'  ? 'block' : 'none';
    document.getElementById('viewTableBtn').classList.toggle('active', view==='table');
    document.getElementById('viewCardBtn').classList.toggle('active',  view==='card');
}

// ── Live filter ───────────────────────────────────────────
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

// Auto dismiss flash
const flash = document.getElementById('flashMsg');
if (flash) setTimeout(() => flash.style.opacity = '0', 4000);
</script>

<script src="../assets/js/staff.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
