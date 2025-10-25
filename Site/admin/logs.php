<?php

declare(strict_types=1);

$adminPageTitle = 'Logs';
$adminNavActive = 'logs';

require __DIR__ . '/partials/header.php';

admin_render_placeholder('Audit Logs');

require __DIR__ . '/partials/footer.php';
