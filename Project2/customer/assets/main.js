/* ============================================================
   Minmi Restaurent — Customer Website
   Place in: customer/assets/main.js
   ============================================================ */

// ── Smart Auto-Refresh every 5 seconds ──
// Skips pages where user is actively interacting
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

    // Pages that should NOT auto-refresh
    const skipPages = ['cart.php', 'login.php', 'register.php', 'profile.php', 'index.php'];
    const page = window.location.pathname.split('/').pop();
    if (skipPages.includes(page)) return;

    setInterval(function () {
        if (!userActive) {
            sessionStorage.setItem('scrollPos', window.scrollY);
            window.location.reload();
        }
    }, 5000);

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