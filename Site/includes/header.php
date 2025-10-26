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
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Our theme -->
    <link rel="stylesheet" href="/assets/css/theme.css">
    <?php foreach ($themeCssFiles as $cssHref): ?>
        <link rel="stylesheet" href="<?php echo sanitize($cssHref); ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="/assets/css/overrides.css">
</head>
<body data-theme="<?php echo sanitize($themeSlug); ?>" class="<?php echo htmlspecialchars(get_setting('theme_preset') ?? 'preset-violet', ENT_QUOTES, 'UTF-8'); ?>">
<?php include __DIR__ . '/nav.php'; ?>
<main class="py-3 py-lg-4">
