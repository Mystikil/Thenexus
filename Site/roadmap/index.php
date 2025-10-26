<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
require_once $root . '/Site/config.php';
require_once $root . '/Site/functions.php';
require_once $root . '/Site/includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = isset($_SESSION['account']['group_id']) && ((int) $_SESSION['account']['group_id'] >= (int) ADMIN_GROUP_ID);
$csrf = function_exists('csrf_token') ? csrf_token() : '';

$header = $root . '/includes/header.php';
$footer = $root . '/includes/footer.php';
if (file_exists($header)) include $header;
?>
<section id="roadmap-app" class="nx-roadmap" data-theme="auto">
    <div class="container">
        <header class="roadmap-hero">
            <div>
                <h1 class="roadmap-title">Devnexus Online Roadmap</h1>
                <p class="roadmap-subtitle">Track our major initiatives across discovery, production, launch, and beyond.</p>
            </div>
            <div class="roadmap-hero-actions">
                <button class="roadmap-theme" id="roadmap-theme-toggle" type="button" aria-pressed="false">
                    <span class="icon" aria-hidden="true">🌗</span>
                    <span class="label">Toggle light/dark</span>
                </button>
                <a class="roadmap-feedback" href="https://forms.gle" target="_blank" rel="noopener">
                    <span aria-hidden="true">🗳️</span>
                    Share feedback
                </a>
                <?php if ($isAdmin): ?>
                    <button class="roadmap-edit" id="edit-json" type="button" data-csrf="<?php echo sanitize($csrf); ?>">
                        <span aria-hidden="true">✏️</span>
                        Edit JSON
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <section class="roadmap-metrics" aria-label="Roadmap at a glance">
            <div class="metric" id="metric-total">
                <span class="metric-label">Total items</span>
                <span class="metric-value">–</span>
            </div>
            <div class="metric" id="metric-shipped">
                <span class="metric-label">Shipped</span>
                <span class="metric-value">–</span>
            </div>
            <div class="metric" id="metric-progress">
                <span class="metric-label">Average progress</span>
                <span class="metric-value">–</span>
            </div>
            <div class="metric" id="metric-next-update">
                <span class="metric-label">Last updated</span>
                <span class="metric-value">–</span>
            </div>
        </section>

        <section class="roadmap-controls" aria-label="Filters and sorting">
            <div class="control search">
                <label for="roadmap-search" class="form-label">Search</label>
                <div class="search-input">
                    <span aria-hidden="true">🔍</span>
                    <input id="roadmap-search" type="search" placeholder="Search by keyword, owner, or tag" aria-describedby="roadmap-search-help">
                </div>
                <p id="roadmap-search-help" class="help-text">Use quoted terms for exact matches, e.g. "Arena".</p>
            </div>
            <div class="control sort">
                <label for="roadmap-sort" class="form-label">Sort by</label>
                <select id="roadmap-sort">
                    <option value="relevance">Relevance</option>
                    <option value="progress-desc">Progress (high → low)</option>
                    <option value="progress-asc">Progress (low → high)</option>
                    <option value="phase">Phase</option>
                    <option value="alpha">Title (A → Z)</option>
                </select>
            </div>
            <div class="control shipped">
                <label for="roadmap-shipped-filter" class="form-label">Show shipped</label>
                <select id="roadmap-shipped-filter">
                    <option value="all">All items</option>
                    <option value="shipped">Only shipped</option>
                    <option value="active">Exclude shipped</option>
                </select>
            </div>
        </section>

        <section class="roadmap-filter-groups" aria-label="Filter by taxonomy">
            <div class="filter-group">
                <h2>Categories</h2>
                <div id="roadmap-category-filters" class="filter-chips" role="group" aria-label="Filter by category"></div>
            </div>
            <div class="filter-group">
                <h2>Status</h2>
                <div id="roadmap-status-filters" class="filter-chips" role="group" aria-label="Filter by status"></div>
            </div>
            <div class="filter-group">
                <h2>Phases</h2>
                <div id="roadmap-phase-filters" class="filter-chips" role="group" aria-label="Filter by phase"></div>
            </div>
            <div class="filter-group">
                <h2>Survey tags</h2>
                <div id="roadmap-tag-filters" class="filter-chips" role="group" aria-label="Filter by survey tag"></div>
            </div>
        </section>

        <section class="roadmap-board" aria-live="polite">
            <div class="roadmap-board-header">
                <h2>Initiatives by phase</h2>
                <p>Hover a card to view dependencies, owners, and survey impact.</p>
            </div>
            <div class="roadmap-lanes" id="roadmap-lanes" data-empty="Loading roadmap…"></div>
        </section>

        <section class="roadmap-updates" aria-label="Roadmap updates">
            <h2>How we prioritise</h2>
            <p>We combine player survey weightings, live telemetry, and strategic goals to shape this roadmap. Phases move from discovery through launch, and progress bars update as teams report milestones.</p>
            <ul class="roadmap-principles">
                <li>Survey influence adjusts the default <em>Relevance</em> sort order.</li>
                <li>Dependencies ensure foundational work ships before dependent features.</li>
                <li>We review community sentiment monthly and update this page after every planning sync.</li>
            </ul>
        </section>
    </div>

    <template id="roadmap-item-template">
        <article class="roadmap-item" tabindex="0">
            <header class="item-header">
                <h3 class="item-title"></h3>
                <span class="item-status"></span>
            </header>
            <p class="item-description"></p>
            <div class="item-meta">
                <div class="meta-block">
                    <span class="meta-label">Progress</span>
                    <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="progress-value" style="width:0%"></div>
                    </div>
                </div>
                <div class="meta-block">
                    <span class="meta-label">Owner</span>
                    <span class="meta-value item-owner"></span>
                </div>
                <div class="meta-block">
                    <span class="meta-label">ETA</span>
                    <span class="meta-value item-eta"></span>
                </div>
            </div>
            <div class="item-taxonomy">
                <div class="taxonomy-group">
                    <span class="meta-label">Categories</span>
                    <ul class="chip-list item-categories"></ul>
                </div>
                <div class="taxonomy-group">
                    <span class="meta-label">Survey tags</span>
                    <ul class="chip-list item-tags"></ul>
                </div>
                <div class="taxonomy-group">
                    <span class="meta-label">Dependencies</span>
                    <ul class="chip-list item-dependencies"></ul>
                </div>
            </div>
        </article>
    </template>

    <div class="roadmap-modal" id="roadmap-json-modal" hidden>
        <div class="roadmap-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="roadmap-json-modal-title">
            <header class="roadmap-modal__header">
                <h2 id="roadmap-json-modal-title">Edit roadmap JSON</h2>
                <button type="button" class="roadmap-modal__close" id="roadmap-json-close" aria-label="Close">×</button>
            </header>
            <textarea id="roadmap-json-editor" spellcheck="false"></textarea>
            <footer class="roadmap-modal__footer">
                <button type="button" id="roadmap-json-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="roadmap-json-save" class="btn-primary">Save changes</button>
            </footer>
            <p class="roadmap-modal__hint">Include the <code>csrfToken</code> field when saving. It is injected automatically.</p>
        </div>
    </div>

    <div class="roadmap-toast" id="roadmap-toast" role="status" aria-live="polite" hidden></div>

    <script type="application/ld+json" id="roadmap-jsonld"></script>
</section>
<script type="module" src="/Site/roadmap/roadmap.js" defer></script>
<link rel="stylesheet" href="/Site/roadmap/roadmap.css">
<?php if (file_exists($footer)) include $footer; ?>
