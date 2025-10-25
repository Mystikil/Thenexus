<?php

declare(strict_types=1);

$adminPageTitle = 'CMS';
$adminNavActive = 'cms';

require __DIR__ . '/partials/header.php';

admin_render_placeholder('CMS');

require __DIR__ . '/partials/footer.php';
