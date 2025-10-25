<?php

declare(strict_types=1);

$adminPageTitle = 'News';
$adminNavActive = 'news';

require __DIR__ . '/partials/header.php';

admin_render_placeholder('News');

require __DIR__ . '/partials/footer.php';
