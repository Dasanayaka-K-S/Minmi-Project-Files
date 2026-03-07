// ============================================================
//  STAFF MANAGEMENT — staff.js
//  Place in: dashboard/assets/js/staff.js
//  Depends on: Chart.js (loaded in header), STAFF_DATA /
//  DEPT_LABELS / DEPT_VALS / SALARY_NAMES / SALARY_VALS
//  (injected as inline <script> vars at bottom of staff.php)
// ============================================================

// ── Working copy of staff array (mutated by add/edit/delete) ──
let staffData = STAFF_DATA.map(s => Object.assign({}, s));
let deleteTargetIndex = null;

// ── Avatar colour palette (cycles for new entries) ────────
const AVATAR_COLORS = ['#e8622a', '#f5c842', '#3ecf8e', '#4e9cf7', '#a855f7', '#f472b6'];

// ════════════════════════════════════════
//  CHART.JS SETUP
// ════════════════════════════════════════
Chart.defaults.color       = '#6b5f52';
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size   = 11;

// Department doughnut
new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: {
        labels: DEPT_LABELS,
        datasets: [{
            data:            DEPT_VALS,
            backgroundColor: ['#e8622a', '#4e9cf7', '#a855f7', '#f472b6'],
            borderWidth:     0,
            hoverOffset:     6
        }]
    },
    options: {
        responsive:        true,
        maintainAspectRatio: false,
        cutout:            '68%',
        plugins: {
            legend: {
                position: 'right',
                labels:   { boxWidth: 10, padding: 12 }
            }
        }
    }
});

// Salary bar chart
new Chart(document.getElementById('salaryChart'), {
    type: 'bar',
    data: {
        labels: SALARY_NAMES.map(n => n.split(' ')[0]),   // first name only
        datasets: [{
            data:            SALARY_VALS,
            backgroundColor: '#f5c842',
            borderRadius:    5,
            borderSkipped:   false
        }]
    },
    options: {
        responsive:        true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, border: { color: '#2e2925' } },
            y: {
                grid:   { color: '#2e2925' },
                border: { color: '#2e2925' },
                ticks:  { callback: v => '$' + v.toLocaleString() }
            }
        }
    }
});


// ════════════════════════════════════════
//  VIEW TOGGLE  (Table ↔ Cards)
// ════════════════════════════════════════
function switchView(mode) {
    document.getElementById('tableView').style.display  = mode === 'table' ? 'block' : 'none';
    document.getElementById('cardView').style.display   = mode === 'card'  ? 'block' : 'none';
    document.getElementById('viewTableBtn').classList.toggle('active', mode === 'table');
    document.getElementById('viewCardBtn').classList.toggle('active',  mode === 'card');
}


// ════════════════════════════════════════
//  LIVE SEARCH + FILTER
// ════════════════════════════════════════
function filterStaff() {
    const q    = document.getElementById('staffSearch').value.toLowerCase();
    const dept = document.getElementById('deptFilter').value;
    const stat = document.getElementById('statusFilter').value;

    let visible = 0;

    // Table rows
    document.querySelectorAll('#staffTable tbody tr').forEach(row => {
        const match =
            (!q    || row.dataset.name.includes(q)  ||
                      row.dataset.role.includes(q)  ||
                      row.dataset.email.includes(q)) &&
            (!dept || row.dataset.dept   === dept) &&
            (!stat || row.dataset.status === stat);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    // Staff cards
    document.querySelectorAll('.staff-card').forEach(card => {
        const match =
            (!q    || card.dataset.name.includes(q)  ||
                      card.dataset.role.includes(q)  ||
                      card.dataset.email.includes(q)) &&
            (!dept || card.dataset.dept   === dept) &&
            (!stat || card.dataset.status === stat);
        card.style.display = match ? '' : 'none';
    });

    document.getElementById('staffCount').textContent = visible + ' members';
}


// ════════════════════════════════════════
//  MODAL HELPERS
// ════════════════════════════════════════
function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => {
        if (e.target === bd) bd.classList.remove('open');
    });
});

// Close on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-backdrop.open')
                .forEach(bd => bd.classList.remove('open'));
});


// ════════════════════════════════════════
//  ADD MODAL
// ════════════════════════════════════════
function openAddModal() {
    document.getElementById('modalTitle').textContent    = 'Add Staff Member';
    document.getElementById('formSubmitBtn').textContent = 'Add Staff Member';
    document.getElementById('editIndex').value           = '';
    document.getElementById('staffForm').reset();

    // Auto-generate next ID
    const next = staffData.length + 1;
    document.getElementById('f_id').value = 'STF-' + String(next).padStart(3, '0');

    openModal('staffModal');
}


// ════════════════════════════════════════
//  EDIT MODAL
// ════════════════════════════════════════
function openEditModal(i) {
    const s = staffData[i];
    document.getElementById('modalTitle').textContent    = 'Edit — ' + s.name;
    document.getElementById('formSubmitBtn').textContent = 'Save Changes';
    document.getElementById('editIndex').value           = i;

    document.getElementById('f_name').value     = s.name;
    document.getElementById('f_id').value       = s.id;
    document.getElementById('f_role').value     = s.role;
    document.getElementById('f_dept').value     = s.dept;
    document.getElementById('f_email').value    = s.email;
    document.getElementById('f_phone').value    = s.phone;
    document.getElementById('f_salary').value   = s.salary;
    document.getElementById('f_schedule').value = s.schedule;
    document.getElementById('f_status').value   = s.status;
    document.getElementById('f_rating').value   = s.rating;
    document.getElementById('f_joined').value   = s.joined;
    document.getElementById('f_avatar').value   = s.avatar;

    openModal('staffModal');
}


// ════════════════════════════════════════
//  FORM SUBMIT  (Add or Edit)
// ════════════════════════════════════════
function submitStaffForm(e) {
    e.preventDefault();

    const idx    = document.getElementById('editIndex').value;
    const rawName = document.getElementById('f_name').value.trim();

    // Auto-generate initials if avatar field is blank
    const avatar = document.getElementById('f_avatar').value.trim() ||
        rawName.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();

    const entry = {
        id:       document.getElementById('f_id').value.trim(),
        name:     rawName,
        role:     document.getElementById('f_role').value.trim(),
        dept:     document.getElementById('f_dept').value,
        email:    document.getElementById('f_email').value.trim(),
        phone:    document.getElementById('f_phone').value.trim(),
        salary:   parseInt(document.getElementById('f_salary').value) || 0,
        schedule: document.getElementById('f_schedule').value,
        status:   document.getElementById('f_status').value,
        rating:   parseFloat(document.getElementById('f_rating').value) || 4.0,
        joined:   document.getElementById('f_joined').value || new Date().toISOString().slice(0, 10),
        avatar,
        color:    idx !== '' ? staffData[parseInt(idx)].color
                             : AVATAR_COLORS[staffData.length % AVATAR_COLORS.length],
    };

    if (idx !== '') {
        staffData[parseInt(idx)] = entry;
        showToast('✅ ' + entry.name + ' updated successfully!', 'success');
    } else {
        staffData.push(entry);
        showToast('✅ ' + entry.name + ' added to staff!', 'success');
    }

    closeModal('staffModal');
    rebuildDOM();
}


// ════════════════════════════════════════
//  PROFILE MODAL
// ════════════════════════════════════════
function openProfileModal(i) {
    const s     = staffData[i];
    const stars = '★'.repeat(Math.floor(s.rating)) + '☆'.repeat(5 - Math.floor(s.rating));

    document.getElementById('profileContent').innerHTML = `
        <div style="display:flex;align-items:center;gap:16px;padding:8px 0 20px;border-bottom:1px solid var(--border)">
            <div style="width:64px;height:64px;border-radius:16px;background:${s.color};
                        display:flex;align-items:center;justify-content:center;
                        font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0">
                ${s.avatar}
            </div>
            <div>
                <div style="font-family:'DM Serif Display',serif;font-size:1.3rem">${s.name}</div>
                <div style="color:var(--text-2);font-size:.85rem;margin-top:3px">${s.role} · ${s.dept}</div>
                <div style="margin-top:6px">
                    <span class="badge ${statusBadgeJS(s.status)}">${s.status}</span>
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px">
            ${pRow('🪪', 'Staff ID',       s.id)}
            ${pRow('📧', 'Email',          s.email)}
            ${pRow('📱', 'Phone',          s.phone || '—')}
            ${pRow('🗓️', 'Schedule',       s.schedule)}
            ${pRow('💵', 'Monthly Salary', '$' + Number(s.salary).toLocaleString())}
            ${pRow('📅', 'Date Joined',    s.joined)}
            ${pRow('⭐', 'Performance',
                `<span style="color:var(--yellow)">${stars}</span>
                 <span style="color:var(--text-2)">&nbsp;${s.rating}/5</span>`)}
            ${pRow('🏢', 'Department',     s.dept)}
        </div>
        <div style="display:flex;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
            <button class="btn btn-primary" style="flex:1"
                onclick="closeModal('profileModal');openEditModal(${i})">✏️ Edit Profile</button>
            <button class="btn btn-danger btn-sm"
                onclick="closeModal('profileModal');openDeleteModal(${i})">🗑️ Remove</button>
        </div>
    `;
    openModal('profileModal');
}

function pRow(icon, label, val) {
    return `
        <div style="background:var(--bg-3);border-radius:var(--radius);padding:12px">
            <div style="font-size:.67rem;font-weight:700;letter-spacing:.07em;
                        text-transform:uppercase;color:var(--text-3);margin-bottom:4px">
                ${icon} ${label}
            </div>
            <div style="font-size:.85rem">${val}</div>
        </div>`;
}


// ════════════════════════════════════════
//  DELETE MODAL
// ════════════════════════════════════════
function openDeleteModal(i) {
    deleteTargetIndex = i;
    document.getElementById('deleteMsg').textContent =
        `This will permanently remove ${staffData[i].name} (${staffData[i].id}) ` +
        `from the system. This action cannot be undone.`;
    openModal('deleteModal');
}

function confirmDelete() {
    if (deleteTargetIndex === null) return;
    const name = staffData[deleteTargetIndex].name;
    staffData.splice(deleteTargetIndex, 1);
    deleteTargetIndex = null;
    closeModal('deleteModal');
    rebuildDOM();
    showToast('🗑️ ' + name + ' removed from staff.', 'danger');
}


// ════════════════════════════════════════
//  DOM REBUILD  (after add / edit / delete)
// ════════════════════════════════════════
function rebuildDOM() {
    rebuildTable();
    rebuildCards();
    document.getElementById('staffCount').textContent = staffData.length + ' members';
    filterStaff();   // re-apply active filters
}

function rebuildTable() {
    document.querySelector('#staffTable tbody').innerHTML =
        staffData.map((s, i) => `
            <tr data-name="${s.name.toLowerCase()}"
                data-role="${s.role.toLowerCase()}"
                data-email="${s.email.toLowerCase()}"
                data-dept="${s.dept}"
                data-status="${s.status}">
                <td>
                    <div class="staff-cell">
                        <div class="staff-avatar"
                             style="background:${s.color || AVATAR_COLORS[i % AVATAR_COLORS.length]}">
                            ${s.avatar}
                        </div>
                        <div>
                            <div class="staff-name">${s.name}</div>
                            <div class="staff-id">${s.id} · ${s.email}</div>
                        </div>
                    </div>
                </td>
                <td>${s.role}</td>
                <td><span class="badge ${deptBadgeJS(s.dept)}">${s.dept}</span></td>
                <td><span style="color:var(--text-2);font-size:.8rem">${s.schedule}</span></td>
                <td><span class="badge ${statusBadgeJS(s.status)}">${s.status}</span></td>
                <td>
                    <span class="star-rating">${makeStars(s.rating)}</span>
                    <span class="star-val">${s.rating}</span>
                </td>
                <td><strong>$${Number(s.salary).toLocaleString()}</strong></td>
                <td style="color:var(--text-3);font-size:.8rem">${s.joined}</td>
                <td>
                    <div style="display:flex;gap:6px;justify-content:center">
                        <button class="btn btn-ghost  btn-sm btn-icon" title="Edit"    onclick="openEditModal(${i})">✏️</button>
                        <button class="btn btn-ghost  btn-sm btn-icon" title="Profile" onclick="openProfileModal(${i})">👤</button>
                        <button class="btn btn-danger btn-sm btn-icon" title="Delete"  onclick="openDeleteModal(${i})">🗑️</button>
                    </div>
                </td>
            </tr>`
        ).join('');
}

function rebuildCards() {
    document.getElementById('staffCardsGrid').innerHTML =
        staffData.map((s, i) => `
            <div class="staff-card"
                 style="border-top:2px solid ${s.color || AVATAR_COLORS[i % AVATAR_COLORS.length]}"
                 data-name="${s.name.toLowerCase()}"
                 data-role="${s.role.toLowerCase()}"
                 data-email="${s.email.toLowerCase()}"
                 data-dept="${s.dept}"
                 data-status="${s.status}">
                <div class="card-top">
                    <div class="avatar-lg"
                         style="background:${s.color || AVATAR_COLORS[i % AVATAR_COLORS.length]}">
                        ${s.avatar}
                    </div>
                    <div>
                        <div class="name">${s.name}</div>
                        <div class="role">${s.role}</div>
                    </div>
                    <div style="margin-left:auto">
                        <span class="badge ${statusBadgeJS(s.status)}">${s.status}</span>
                    </div>
                </div>
                <div class="card-meta">
                    <div class="meta-item"><label>Department</label><span>${s.dept}</span></div>
                    <div class="meta-item"><label>Schedule</label><span>${s.schedule}</span></div>
                    <div class="meta-item"><label>Salary/mo</label><span><strong>$${Number(s.salary).toLocaleString()}</strong></span></div>
                    <div class="meta-item"><label>Joined</label><span>${s.joined}</span></div>
                    <div class="meta-item" style="grid-column:1/-1">
                        <label>Performance</label>
                        <span class="star-rating">${makeStars(s.rating)}</span>
                        <span class="star-val">${s.rating}</span>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="btn btn-ghost  btn-sm" onclick="openEditModal(${i})"    style="flex:1">✏️ Edit</button>
                    <button class="btn btn-ghost  btn-sm" onclick="openProfileModal(${i})" style="flex:1">👤 Profile</button>
                    <button class="btn btn-danger btn-sm btn-icon" onclick="openDeleteModal(${i})">🗑️</button>
                </div>
            </div>`
        ).join('');
}


// ════════════════════════════════════════
//  TOAST NOTIFICATION
// ════════════════════════════════════════
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = `toast toast-${type} show`;
    setTimeout(() => t.classList.remove('show'), 3200);
}


// ════════════════════════════════════════
//  BADGE HELPERS  (JS mirror of PHP fns)
// ════════════════════════════════════════
function statusBadgeJS(s) {
    return s === 'On Duty'  ? 'badge-green'  :
           s === 'Off Duty' ? 'badge-gray'   :
           s === 'Leave'    ? 'badge-yellow' : 'badge-gray';
}

function deptBadgeJS(d) {
    return d === 'Kitchen'    ? 'badge-orange' :
           d === 'Front'      ? 'badge-blue'   :
           d === 'Bar'        ? 'badge-purple' :
           d === 'Management' ? 'badge-pink'   : 'badge-gray';
}

function makeStars(r) {
    return '★'.repeat(Math.floor(r)) + '☆'.repeat(5 - Math.floor(r));
}