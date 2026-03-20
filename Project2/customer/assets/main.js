/* ============================================================
   Minmi Restaurent — Customer Website
   Place in: customer/assets/main.js
   ============================================================ */

// ── Smart Auto-Refresh — custom intervals per page ──
(function () {
    let userActive = false;

    document.addEventListener('focusin', function (e) {
        if (e.target.matches('input, textarea, select')) userActive = true;
    });

    document.addEventListener('focusout', function (e) {
        if (e.target.matches('input, textarea, select')) {
            setTimeout(function () { userActive = false; }, 3000);
        }
    });

    const page = window.location.pathname.split('/').pop();

    // Per-page intervals in milliseconds (0 = no refresh)
    const intervals = {
        'menu.php':          60000,  // 60 seconds
        'orders.php':         5000,  // 5 seconds
        'reservations.php':  25000,  // 25 seconds
        'cart.php':              0,  // no refresh
        'login.php':             0,  // no refresh
        'register.php':          0,  // no refresh
        'profile.php':           0,  // no refresh
        'index.php':             0,  // no refresh
        'feedback.php':          0,  // no refresh
    };

    const interval = intervals[page] ?? 5000; // default 5s
    if (interval === 0) return;

    setInterval(function () {
        if (!userActive) {
            sessionStorage.setItem('scrollPos', window.scrollY);
            window.location.reload();
        }
    }, interval);

    // Restore scroll position after refresh
    window.addEventListener('load', function () {
        const pos = sessionStorage.getItem('scrollPos');
        if (pos) {
            window.scrollTo(0, parseInt(pos));
            sessionStorage.removeItem('scrollPos');
        }
    });
})();

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close modal on backdrop click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('open');
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach(function (m) {
            m.classList.remove('open');
        });
    }
});

// ── Flash message auto-hide after 5 seconds ──
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.flash').forEach(function (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity .6s';
            flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 600);
        }, 5000);
    });
});