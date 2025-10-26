<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? (defined('SITE_NAME') ? SITE_NAME : 'Site');
$metaDescription = $metaDescription ?? 'Stay up to date with the latest progress from ' . (defined('SITE_NAME') ? SITE_NAME : 'our team') . '.';
$metaOg = $metaOg ?? [];
$additionalStyles = $additionalStyles ?? [];
$additionalScripts = $additionalScripts ?? [];
$bodyClass = $bodyClass ?? '';
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (!empty($metaDescription)): ?>
        <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php foreach ($metaOg as $property => $content): ?>
        <meta property="<?= htmlspecialchars($property, ENT_QUOTES, 'UTF-8'); ?>" content="<?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <?php foreach ($additionalStyles as $styleHref): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($styleHref, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">
<header class="site-header">
    <div class="container">
        <div class="branding">
            <a href="/" class="site-title"><?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'Site', ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <nav class="site-nav" aria-label="Main navigation">
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="/Site/roadmap/">Roadmap</a></li>
            </ul>
        </nav>
    </div>
</header>
<main class="site-main">
