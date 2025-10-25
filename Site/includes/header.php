<?php require_once __DIR__ . '/theme.php';

$pdo = db();
$themeSlug = nx_current_theme_slug($pdo);
$themeCssFiles = [];
$csrfMetaToken = csrf_token();

$variablesPath = nx_theme_path($themeSlug, 'css/variables.css');
if (is_file($variablesPath)) {
    $themeCssFiles[] = base_url('themes/' . rawurlencode($themeSlug) . '/css/variables.css');
}

$themeStylesPath = nx_theme_path($themeSlug, 'css/theme.css');
if (is_file($themeStylesPath)) {
    $themeCssFiles[] = base_url('themes/' . rawurlencode($themeSlug) . '/css/theme.css');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo sanitize($csrfMetaToken); ?>">
    <title><?php echo sanitize(SITE_TITLE); ?></title>
    <link rel="stylesheet" href="<?php echo sanitize(base_url('assets/css/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo sanitize(base_url('assets/css/layout.css')); ?>">
    <?php foreach ($themeCssFiles as $cssHref): ?>
        <link rel="stylesheet" href="<?php echo sanitize($cssHref); ?>">
    <?php endforeach; ?>
</head>
<body data-theme="<?php echo sanitize($themeSlug); ?>">
<header class="site-header">
    <h1 class="site-header__title"><?php echo sanitize(SITE_TITLE); ?></h1>
    <?php include __DIR__ . '/nav.php'; ?>
</header>
<main class="site-content">
