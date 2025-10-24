<?php require_once __DIR__ . '/theme.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize(SITE_TITLE); ?></title>
    <link rel="stylesheet" href="<?php echo sanitize(base_url('assets/css/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo sanitize(theme_url('css/variables.css')); ?>">
    <link rel="stylesheet" href="<?php echo sanitize(theme_url('css/theme.css')); ?>">
</head>
<body>
<header class="site-header">
    <h1><?php echo sanitize(SITE_TITLE); ?></h1>
    <?php include __DIR__ . '/nav.php'; ?>
</header>
<main class="site-content">
