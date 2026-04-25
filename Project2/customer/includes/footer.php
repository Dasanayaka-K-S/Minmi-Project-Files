<?php
// Build asset base path
$_doc_root    = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']), '/');
$_script_file = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$_web_path    = str_replace($_doc_root, '', $_script_file);
$_project_dir = substr($_web_path, 0, strpos($_web_path, '/customer/'));
$asset_base   = $_project_dir . '/customer/assets/';
?>
</main>

<footer>
    <div style="max-width:1240px;margin:0 auto">
        <div style="margin-bottom:12px">
            <img src="<?= $asset_base ?>logo.png" alt="Minmi Restaurant" style="height:60px;width:auto;object-fit:contain;border-radius:10px;background:#fff;padding:4px 10px">
        </div>
        <div style="margin-bottom:10px;color:rgba(255,255,255,.5);font-style:italic">Fire-crafted flavours, every night</div>
        <div style="display:flex;gap:24px;justify-content:center;flex-wrap:wrap;margin-bottom:16px">
            <a href="index.php" style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.84rem;transition:color .2s" onmouseover="this.style.color='#FFD60A'" onmouseout="this.style.color='rgba(255,255,255,.5)'">Home</a>
            <a href="menu.php" style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.84rem" onmouseover="this.style.color='#FFD60A'" onmouseout="this.style.color='rgba(255,255,255,.5)'">Menu</a>
            <a href="reservations.php" style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.84rem" onmouseover="this.style.color='#FFD60A'" onmouseout="this.style.color='rgba(255,255,255,.5)'">Reservations</a>
            <a href="feedback.php" style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.84rem" onmouseover="this.style.color='#FFD60A'" onmouseout="this.style.color='rgba(255,255,255,.5)'">Feedback</a>
        </div>
        <div style="color:rgba(255,255,255,.3);font-size:.78rem;border-top:1px solid rgba(255,255,255,.08);padding-top:16px">
            📧 minmirestaurant@gmail.com &nbsp;·&nbsp; © <?= date('Y') ?> Minmi Restaurent. All rights reserved.
        </div>
    </div>
</footer>

<?php if (isset($page_script)): ?>
<script><?= $page_script ?></script>
<?php endif; ?>

<script src="<?= $asset_base ?>main.js"></script>
<?php require_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>