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

    // Per-page refresh intervals (in milliseconds)
    // 0 = no refresh
    const intervals = {
        'dashboard.php':    20000,  // 20 seconds
        'orders.php':        10000,  // 10 seconds
        'reservations.php':  20000,  // 20 seconds
        'menu.php':          5000,  // 5 seconds
        'inventory.php':     5000,  // 5 seconds
        'customers.php':    60000,  // 60 seconds
        'reports.php':      20000,  // 20 seconds
        'staff.php':            0,  // no refresh
        'settings.php':         0,  // no refresh
        'login.php':            0,  // no refresh
    };

    const interval = intervals[page] ?? 5000; // default 5s for any unlisted page
    if (interval === 0) return; // skip pages with no refresh

    setInterval(function() {
        if (!userActive) {
            sessionStorage.setItem('adminScrollPos', window.scrollY);
            window.location.reload();
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