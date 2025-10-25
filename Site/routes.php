<?php

require_once __DIR__ . '/includes/theme.php';

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
    'characters' => __DIR__ . '/pages/characters.php',
    'shop' => __DIR__ . '/pages/shop.php',
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

$pdo = db();

if (array_key_exists($page, $routes) && file_exists($routes[$page])) {
    $override = nx_locate_template($pdo, $page);

    include __DIR__ . '/includes/header.php';

    if ($override !== null) {
        include $override;
    } else {
        include $routes[$page];
    }

    include __DIR__ . '/includes/footer.php';
    return;
}

http_response_code(404);
$notFoundTemplate = nx_locate_template($pdo, '404');

include __DIR__ . '/includes/header.php';

if ($notFoundTemplate !== null) {
    include $notFoundTemplate;
} elseif (file_exists(__DIR__ . '/pages/404.php')) {
    include __DIR__ . '/pages/404.php';
} else {
    ?>
    <section class="page page--404">
        <h2>Page not found</h2>
        <p>The page you requested could not be located.</p>
    </section>
    <?php
}

include __DIR__ . '/includes/footer.php';
