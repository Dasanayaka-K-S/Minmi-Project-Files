// =====================================================
//  EMBER KITCHEN — MAIN.JS
// =====================================================

// Sidebar toggle (mobile)
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

// Live search / filter utility
function liveFilter(inputId, tableId, colIndexes) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
        rows.forEach(row => {
            const text = colIndexes.map(i => (row.cells[i]?.textContent || '')).join(' ').toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    });
}

// Status filter select
function statusFilter(selectId, tableId, colIndex) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.addEventListener('change', function () {
        const val = this.value.toLowerCase();
        const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
        rows.forEach(row => {
            const cell = (row.cells[colIndex]?.textContent || '').toLowerCase();
            row.style.display = (!val || cell.includes(val)) ? '' : 'none';
        });
    });
}

// Shared Chart.js defaults
Chart.defaults.color = '#6b5f52';
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size = 11;

// Helper: build a line chart
function buildLineChart(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#2e2925' }, border: { color: '#2e2925' } },
                y: { grid: { color: '#2e2925' }, border: { color: '#2e2925' },
                     ticks: { callback: v => '$' + v.toLocaleString() } }
            }
        }
    });
}

// Helper: doughnut chart
function buildDoughnutChart(canvasId, labels, data, colors) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 6 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { boxWidth: 10, padding: 12 }
                }
            }
        }
    });
}

// Helper: bar chart
function buildBarChart(canvasId, labels, data, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: color || '#e8622a',
                borderRadius: 5,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, border: { color: '#2e2925' } },
                y: { grid: { color: '#2e2925' }, border: { color: '#2e2925' } }
            }
        }
    });
}

// Auto-init search on pages
document.addEventListener('DOMContentLoaded', () => {
    liveFilter('searchOrders', 'ordersTable', [0,1,2]);
    statusFilter('filterOrderStatus', 'ordersTable', 3);
    liveFilter('searchMenu', 'menuTable', [0,1,2]);
    liveFilter('searchCustomers', 'custTable', [0,1,2]);
    liveFilter('searchInventory', 'invTable', [0,1,2]);
});
