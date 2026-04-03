</main><!-- /page-content -->
</div><!-- /main-wrap -->
<?php
$is_subpage = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
$assets_path = $is_subpage ? '../assets' : 'assets';
?>
<script src="<?= $assets_path ?>/js/main.js"></script>
<?php if (isset($page_scripts)): ?>
<script>
<?= $page_scripts ?>
</script>
<?php endif; ?>

<script>
// ── Admin Auto-Refresh — custom intervals per page ──
(function() {
    let userActive = false;

    document.addEventListener('focusin', function(e) {
        if (e.target.matches('input, textarea, select')) userActive = true;
    });
    document.addEventListener('focusout', function(e) {
        if (e.target.matches('input, textarea, select'))
            setTimeout(function() { userActive = false; }, 3000);
    });

    const page = window.location.pathname.split('/').pop();

    const intervals = {
        'dashboard.php':    20000,
        'orders.php':       10000,
        'reservations.php': 15000,
        'menu.php':         60000,
        'inventory.php':     5000,
        'customers.php':    60000,
        'reports.php':      20000,
        'staff.php':            0,
        'settings.php':         0,
        'login.php':            0,
    };

    const interval = intervals[page] ?? 5000;
    if (interval === 0) return;

    setInterval(function() {
        if (!userActive) {
            sessionStorage.setItem('adminScrollPos', window.scrollY);
            // ── Use GET-only redirect to prevent POST resubmission ──
            // This ensures the refresh never re-triggers a form POST
            // which would cause duplicate emails to be sent
            window.location.href = window.location.pathname + window.location.search;
        }
    }, interval);

    window.addEventListener('load', function() {
        const pos = sessionStorage.getItem('adminScrollPos');
        if (pos) {
            window.scrollTo(0, parseInt(pos));
            sessionStorage.removeItem('adminScrollPos');
        }
    });
})();
</script>

</body>
</html>