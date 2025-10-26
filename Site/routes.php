<?php

require_once __DIR__ . '/includes/theme.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    require_once __DIR__ . '/auth.php';
    $user = current_user();
    if ($user !== null) {
        audit_log((int) $user['id'], 'logout');
    }
    nx_logout();
    nx_redirect('?p=account&loggedout=1');
}

$routes = [
    'home' => __DIR__ . '/pages/home.php',
    'news' => __DIR__ . '/pages/news.php',
    'changelog' => __DIR__ . '/pages/changelog.php',
    'highscores' => __DIR__ . '/pages/highscores.php',
    'whoisonline' => __DIR__ . '/pages/whoisonline.php',
    'deaths' => __DIR__ . '/pages/deaths.php',
    'market' => __DIR__ . '/pages/market.php',
    'guilds' => __DIR__ . '/pages/guilds.php',
    'houses' => __DIR__ . '/pages/houses.php',
    'character' => __DIR__ . '/pages/character.php',
    'account' => __DIR__ . '/pages/account.php',
    'recover' => __DIR__ . '/pages/recover.php',
    'characters' => __DIR__ . '/pages/characters.php',
    'coins' => __DIR__ . '/pages/coins.php',
    'shop' => __DIR__ . '/pages/shop.php',
    'bestiary' => __DIR__ . '/pages/bestiary.php',
    'monster' => __DIR__ . '/pages/monster.php',
    'spells' => __DIR__ . '/pages/spells.php',
    'tickets' => __DIR__ . '/pages/tickets.php',
    'downloads' => __DIR__ . '/pages/downloads.php',
    'rules' => __DIR__ . '/pages/rules.php',
    'about' => __DIR__ . '/pages/about.php',
];

$page = $_GET['p'] ?? 'home';
$page = strtolower(trim((string) $page));
$page = preg_replace('/[^a-z0-9_]/', '', $page);

if ($page === '') {
    $page = 'home';
}

$GLOBALS['nx_current_page_slug'] = $page;

if ($page === 'character') {
    $nameParam = trim((string) ($_GET['name'] ?? ''));
    if ($nameParam !== '') {
        $GLOBALS['nx_meta_title'] = $nameParam . ' – Character';
    } else {
        $GLOBALS['nx_meta_title'] = 'Character Search';
    }
} elseif ($page === 'characters') {
    $GLOBALS['nx_meta_title'] = 'My Characters';
} elseif ($page === 'coins') {
    $GLOBALS['nx_meta_title'] = 'Coins & Premium';
}

$themeSlug = nx_theme_active_slug();

$maintenanceValue = get_setting('maintenance');
$maintenanceEnabled = false;

if ($maintenanceValue !== null) {
    $normalized = strtolower(trim($maintenanceValue));
    $maintenanceEnabled = in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true);
}

if ($maintenanceEnabled && !is_master() && !is_role('admin')) {
    http_response_code(503);
    $GLOBALS['nx_current_page_slug'] = 'maintenance';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container-page"><div class="card nx-glow"><div class="card-body text-center py-5">';
    echo '<h3 class="mb-3">Maintenance Mode</h3>';
    echo '<p class="text-muted mb-0">The site is undergoing scheduled maintenance. Please check back soon.</p>';
    echo '</div></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if (array_key_exists($page, $routes) && file_exists($routes[$page])) {
    $override = nx_theme_locate($themeSlug, $page);

    if ($override !== null) {
        return $override;
    }

    return $routes[$page];
}

http_response_code(404);
$GLOBALS['nx_current_page_slug'] = '404';
include __DIR__ . '/includes/header.php';
echo '<div class="container-page"><div class="card nx-glow"><div class="card-body text-center py-5">';
echo '<h3 class="mb-3">Page Not Found</h3>';
echo '<p class="text-muted">The page you were looking for could not be found.</p>';
echo '<a class="btn btn-primary" href="?p=home">Return to homepage</a>';
echo '</div></div></div>';
include __DIR__ . '/includes/footer.php';
exit;
