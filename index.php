<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
require_once $root . '/config.php';
require_once $root . '/includes/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = (defined('SITE_NAME') ? SITE_NAME : 'The Nexus') . ' | Home';
$metaDescription = 'Step into The Nexus and see what we shipped recently along with the next initiatives we are tackling.';
$metaOg = [
    'og:title' => (defined('SITE_NAME') ? SITE_NAME : 'The Nexus') . ' Homepage',
    'og:description' => 'Catch the latest highlights and explore the roadmap to understand what is coming next.',
    'og:image' => 'https://via.placeholder.com/1200x630.png?text=The+Nexus'
];
$additionalStyles = ['/Site/site.css'];
$additionalScripts = [];

$header = $root . '/includes/header.php';
$footer = $root . '/includes/footer.php';
if (file_exists($header)) include $header;
?>
<section class="home-hero">
    <h1>Welcome to <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'The Nexus', ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>Follow our progress, learn what just shipped, and dive into the roadmap to see what is landing next.</p>
</section>
<section class="home-roadmap">
    <h2>Roadmap snapshot</h2>
    <p>Here are the top initiatives based on community survey weighting and phase priority.</p>
    <link rel="stylesheet" href="/Site/roadmap/widget.css">
    <div id="nx-roadmap-widget" aria-live="polite"></div>
    <script src="/Site/roadmap/widget.js" defer></script>
</section>
<style>
    .home-hero {
        display: grid;
        gap: 1rem;
        padding-bottom: 2rem;
    }
    .home-roadmap {
        display: grid;
        gap: 0.75rem;
        margin-top: 2rem;
    }
</style>
<?php if (file_exists($footer)) include $footer; ?>
