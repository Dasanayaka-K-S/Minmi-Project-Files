// ============================================================
//  SETTINGS PAGE — settings.js
//  Place in: dashboard/assets/js/settings.js
// ============================================================

// ════════════════════════════════════════
//  TAB SWITCHING
// ════════════════════════════════════════
function switchTab(tabId, btn) {
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

    // Show selected
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');
}


// ════════════════════════════════════════
//  SAVE ALL SETTINGS
// ════════════════════════════════════════
function saveAllSettings() {
    // In a real app this would POST to a PHP endpoint.
    // Here we just show a success toast.
    showToast('✅ All settings saved successfully!', 'success');
}


// ════════════════════════════════════════
//  RESET ALL FORMS
// ════════════════════════════════════════
function resetAllForms() {
    showConfirm(
        '↺',
        'Reset all changes?',
        'This will undo any unsaved changes made in this session and restore the last saved values.',
        'Reset',
        () => {
            document.querySelectorAll('.form-input').forEach(el => {
                if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                } else if (el.type !== 'checkbox' && el.type !== 'radio') {
                    // leave default values — in a real app you'd reload from DB
                }
            });
            showToast('↺ Changes reset to last saved state.', 'info');
        }
    );
}


// ════════════════════════════════════════
//  LOGO PREVIEW
// ════════════════════════════════════════
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('logoPreview');
        preview.innerHTML = `<img src="${e.target.result}" alt="Logo preview">`;
    };
    reader.readAsDataURL(input.files[0]);
}


// ════════════════════════════════════════
//  OPERATING HOURS — toggle day open/closed
// ════════════════════════════════════════
function toggleDay(day, isOpen) {
    const timesEl  = document.getElementById('times-' + day);
    const hoursRow = document.getElementById('hours-' + day);
    const dayName  = hoursRow.querySelector('.day-name');
    const badge    = hoursRow.querySelector('.badge');

    if (isOpen) {
        timesEl.classList.remove('disabled');
        timesEl.querySelectorAll('input').forEach(i => i.removeAttribute('disabled'));
        dayName.classList.remove('closed');
        badge.className    = 'badge badge-green';
        badge.textContent  = 'Open';
    } else {
        timesEl.classList.add('disabled');
        timesEl.querySelectorAll('input').forEach(i => i.setAttribute('disabled', true));
        dayName.classList.add('closed');
        badge.className    = 'badge badge-red';
        badge.textContent  = 'Closed';
    }
}


// ════════════════════════════════════════
//  SPECIAL CLOSURES — add / remove
// ════════════════════════════════════════
function addClosure() {
    const list = document.getElementById('closuresList');
    const row  = document.createElement('div');
    row.className = 'closure-row';
    row.innerHTML = `
        <input class="form-input" type="date" style="max-width:180px">
        <input class="form-input" type="text" placeholder="Reason (e.g. Public Holiday)" style="flex:1">
        <button class="btn btn-danger btn-sm btn-icon" onclick="removeClosure(this)">✕</button>
    `;
    // small fade-in
    row.style.opacity = '0';
    list.appendChild(row);
    requestAnimationFrame(() => {
        row.style.transition = 'opacity .25s ease';
        row.style.opacity    = '1';
    });
}

function removeClosure(btn) {
    const row = btn.closest('.closure-row');
    row.style.transition = 'opacity .2s ease';
    row.style.opacity    = '0';
    setTimeout(() => row.remove(), 200);
}


// ════════════════════════════════════════
//  ACCENT COLOUR SWATCHES
// ════════════════════════════════════════
function selectAccent(btn, hex) {
    document.querySelectorAll('.colour-swatch').forEach(s => {
        s.classList.remove('active');
        s.textContent = '';
    });
    btn.classList.add('active');
    btn.textContent = '✓';

    // Live-preview: update --accent CSS variable on :root
    document.documentElement.style.setProperty('--accent',   hex);
    document.documentElement.style.setProperty('--accent-l', lighten(hex, 15));

    showToast('🎨 Accent colour updated — save to apply permanently.', 'info');
}

// Simple hex lightening helper
function lighten(hex, amount) {
    const num = parseInt(hex.replace('#',''), 16);
    const r   = Math.min(255, (num >> 16) + amount);
    const g   = Math.min(255, ((num >> 8) & 0xff) + amount);
    const b   = Math.min(255, (num & 0xff) + amount);
    return '#' + [r,g,b].map(v => v.toString(16).padStart(2,'0')).join('');
}


// ════════════════════════════════════════
//  PASSWORD VISIBILITY TOGGLE
// ════════════════════════════════════════
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type   = 'text';
        btn.style.opacity = '1';
    } else {
        input.type   = 'password';
        btn.style.opacity = '.5';
    }
}


// ════════════════════════════════════════
//  PASSWORD STRENGTH METER
// ════════════════════════════════════════
function checkStrength(pw) {
    const fill  = document.getElementById('pwStrengthFill');
    const label = document.getElementById('pwStrengthLabel');

    let score = 0;
    if (pw.length >= 8)             score++;
    if (pw.length >= 12)            score++;
    if (/[A-Z]/.test(pw))           score++;
    if (/[0-9]/.test(pw))           score++;
    if (/[^A-Za-z0-9]/.test(pw))   score++;

    const levels = [
        { pct: '0%',   color: 'var(--bg-3)',    text: 'Enter a new password' },
        { pct: '25%',  color: 'var(--red)',      text: 'Weak — too short'     },
        { pct: '50%',  color: 'var(--yellow)',   text: 'Fair — add numbers'   },
        { pct: '75%',  color: 'var(--accent)',   text: 'Good — almost there'  },
        { pct: '90%',  color: '#a855f7',         text: 'Strong password'      },
        { pct: '100%', color: 'var(--green)',    text: '✅ Excellent password' },
    ];

    const lvl       = levels[Math.min(score, 5)];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = score >= 4 ? 'var(--green)' : 'var(--text-3)';
}


// ════════════════════════════════════════
//  CHANGE PASSWORD
// ════════════════════════════════════════
function changePassword() {
    const cur     = document.getElementById('s_curpw').value.trim();
    const newPw   = document.getElementById('s_newpw').value.trim();
    const confirm = document.getElementById('s_confirmpw').value.trim();

    if (!cur) {
        showToast('⚠️ Please enter your current password.', 'danger'); return;
    }
    if (newPw.length < 8) {
        showToast('⚠️ New password must be at least 8 characters.', 'danger'); return;
    }
    if (newPw !== confirm) {
        showToast('⚠️ New passwords do not match.', 'danger'); return;
    }

    // In a real app: POST to change-password endpoint
    document.getElementById('s_curpw').value     = '';
    document.getElementById('s_newpw').value     = '';
    document.getElementById('s_confirmpw').value = '';
    document.getElementById('pwStrengthFill').style.width = '0%';
    document.getElementById('pwStrengthLabel').textContent = 'Enter a new password';

    showToast('🔑 Password updated successfully!', 'success');
}


// ════════════════════════════════════════
//  DANGER ZONE CONFIRMS
// ════════════════════════════════════════
function confirmReset() {
    showConfirm(
        '⚠️',
        'Reset all settings to default?',
        'This will permanently wipe all your customisations — profile info, hours, billing settings and preferences — and restore factory defaults. This cannot be undone.',
        'Yes, Reset Everything',
        () => showToast('♻️ Settings reset to defaults.', 'danger')
    );
}

function confirmClearData() {
    showConfirm(
        '🗑️',
        'Clear all data?',
        'This will permanently delete ALL orders, customer records, inventory data and staff entries. This action is irreversible.',
        'Yes, Delete All Data',
        () => showToast('🗑️ All data cleared.', 'danger')
    );
}


// ════════════════════════════════════════
//  GENERIC CONFIRM MODAL
// ════════════════════════════════════════
let confirmCallback = null;

function showConfirm(icon, title, msg, btnLabel, onConfirm) {
    document.getElementById('confirmIcon').textContent  = icon;
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent   = msg;
    document.getElementById('confirmOkBtn').textContent = btnLabel;
    confirmCallback = onConfirm;
    document.getElementById('confirmModal').classList.add('open');
}

function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    confirmCallback = null;
}

document.getElementById('confirmOkBtn').addEventListener('click', () => {
    if (typeof confirmCallback === 'function') confirmCallback();
    closeConfirm();
});

// Close modal on backdrop click
document.getElementById('confirmModal').addEventListener('click', e => {
    if (e.target === document.getElementById('confirmModal')) closeConfirm();
});

// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeConfirm();
});


// ════════════════════════════════════════
//  TOAST
// ════════════════════════════════════════
function showToast(msg, type = 'success') {
    const t       = document.getElementById('toast');
    t.textContent = msg;
    t.className   = `toast toast-${type} show`;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3400);
}