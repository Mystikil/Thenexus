<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
require_once $root . '/config.php';
require_once $root . '/includes/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = (defined('SITE_NAME') ? SITE_NAME : 'Our') . ' Roadmap';
$metaDescription = 'Track phases, shipped releases, and upcoming work on the ' . (defined('SITE_NAME') ? SITE_NAME : 'project') . ' roadmap.';
$metaOg = [
    'og:title' => (defined('SITE_NAME') ? SITE_NAME : 'Our') . ' Product Roadmap',
    'og:description' => 'Explore progress by phase, filter upcoming releases, and review recently shipped milestones.',
    'og:image' => 'https://via.placeholder.com/1200x630.png?text=The+Nexus+Roadmap',
    'twitter:card' => 'summary_large_image'
];
$additionalStyles = ['/Site/site.css'];
$additionalScripts = [];

$isAdmin = isset($_SESSION['account']['group_id']) && ((int)$_SESSION['account']['group_id'] >= (int)ADMIN_GROUP_ID);
$csrf = function_exists('csrf_token') ? csrf_token() : '';

$header = $root . '/includes/header.php';
$footer = $root . '/includes/footer.php';
if (file_exists($header)) include $header;
?>
<section class="roadmap-hero">
    <header>
        <p class="roadmap-updated" id="roadmap-updated" aria-live="polite"></p>
        <h1>Product Roadmap</h1>
        <p class="roadmap-subtitle">Discover what the team is building, what just shipped<span aria-hidden="true">*</span>, and what is coming next.</p>
    </header>
    <div class="roadmap-hero-actions">
        <div class="theme-toggle">
            <button id="theme-toggle" type="button" aria-pressed="false">Toggle dark mode</button>
        </div>
        <div class="roadmap-export">
            <button id="download-json" type="button">Download JSON</button>
            <?php if ($isAdmin): ?>
                <button id="edit-json" type="button">Edit JSON</button>
            <?php endif; ?>
        </div>
    </div>
</section>
<section class="roadmap-controls" aria-label="Roadmap filters">
    <div class="control-group">
        <label for="roadmap-search">Search</label>
        <input id="roadmap-search" type="search" placeholder="Search initiatives or descriptions" autocomplete="off">
    </div>
    <div class="control-group">
        <label for="roadmap-sort">Sort by</label>
        <select id="roadmap-sort">
            <option value="relevance">Relevance</option>
            <option value="progress">Progress</option>
            <option value="phase">Phase</option>
            <option value="title">Title</option>
        </select>
    </div>
    <div class="control-group">
        <fieldset>
            <legend>Status</legend>
            <div id="status-filters" class="chip-group" role="group" aria-label="Status filters"></div>
        </fieldset>
    </div>
    <div class="control-group">
        <fieldset>
            <legend>Categories</legend>
            <div id="category-filters" class="chip-group" role="group" aria-label="Category filters"></div>
        </fieldset>
    </div>
    <div class="control-group">
        <fieldset>
            <legend>Phases</legend>
            <div id="phase-filters" class="chip-group" role="group" aria-label="Phase filters"></div>
        </fieldset>
    </div>
    <div class="control-group control-actions">
        <button id="clear-filters" type="button">Clear filters</button>
    </div>
</section>
<section class="roadmap-summary" aria-live="polite">
    <div class="summary-cards" id="summary-cards"></div>
</section>
<section class="roadmap-phases" id="roadmap-phases" aria-live="polite">
</section>
<section class="roadmap-list" aria-live="polite">
    <div id="roadmap-results" class="roadmap-grid" role="list"></div>
    <p id="roadmap-empty" class="roadmap-empty" hidden>No roadmap items match your filters yet.</p>
</section>
<section class="roadmap-jsonld" aria-hidden="true">
    <script type="application/ld+json" id="roadmap-ld-json"></script>
</section>
<aside class="roadmap-notes">
    <p><span aria-hidden="true">*</span> Shipped initiatives appear with an asterisk.</p>
    <p>Need a snapshot? Use the download button to export the current roadmap view.</p>
</aside>
<div class="modal" id="roadmap-modal" role="dialog" aria-modal="true" aria-labelledby="roadmap-modal-title" hidden>
    <div class="modal-content">
        <header>
            <h2 id="roadmap-modal-title">Edit roadmap JSON</h2>
            <button type="button" class="modal-close" id="modal-close" aria-label="Close editor">&times;</button>
        </header>
        <textarea id="roadmap-modal-textarea" spellcheck="false"></textarea>
        <footer>
            <button type="button" id="modal-save">Save changes</button>
            <button type="button" id="modal-cancel">Cancel</button>
        </footer>
    </div>
</div>
<script type="module" src="/Site/roadmap/roadmap.js" defer></script>
<link rel="stylesheet" href="/Site/roadmap/roadmap.css">
<?php if (file_exists($footer)) include $footer; ?>
