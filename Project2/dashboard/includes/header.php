<?php
// Detect if we're in pages/ subdirectory
$is_subpage = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
$assets_path = $is_subpage ? '../assets' : 'assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> — Ember Kitchen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="<?= $assets_path ?>/css/style.css">
    <?php if (!empty($extra_css)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($extra_css) ?>">
    <?php endif; ?>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-wrap">
    <?php include __DIR__ . '/topbar.php'; ?>
    <main class="page-content">
