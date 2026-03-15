</main>

<footer>
    <div style="margin-bottom:8px">
        <strong>🔥 Minmi Restaurent</strong>
    </div>
    <div style="margin-bottom:6px">Fire-crafted flavours, every night</div>
    <div>📧 minmirestaurant@gmail.com &nbsp;|&nbsp; © <?= date('Y') ?> Minmi Restaurent. All rights reserved.</div>
</footer>

<?php if (isset($page_script)): ?>
<script><?= $page_script ?></script>
<?php endif; ?>

<script>
// ── Smart Auto-Refresh every 5 seconds ──
// Pauses if user is typing in a form input
(function() {
    let userActive = false;
    let refreshTimer;

    // Detect if user is interacting with any form field
    document.addEventListener('focusin',  function(e) {
        if (e.target.matches('input, textarea, select')) userActive = true;
    });
    document.addEventListener('focusout', function(e) {
        if (e.target.matches('input, textarea, select')) {
            // Resume refresh after 3 seconds of inactivity
            setTimeout(function() { userActive = false; }, 3000);
        }
    });

    // Don't auto-refresh cart page (user is actively placing order)
    const page = window.location.pathname.split('/').pop();
    const skipPages = ['cart.php', 'login.php', 'register.php', 'profile.php'];
    if (skipPages.includes(page)) return;

    refreshTimer = setInterval(function() {
        if (!userActive) {
            // Preserve scroll position on refresh
            sessionStorage.setItem('scrollPos', window.scrollY);
            window.location.reload();
        }
    }, 5000);

    // Restore scroll position after refresh
    window.addEventListener('load', function() {
        const pos = sessionStorage.getItem('scrollPos');
        if (pos) { window.scrollTo(0, parseInt(pos)); sessionStorage.removeItem('scrollPos'); }
    });
})();
</script>

</body>
</html>