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
</body>
</html>
