<?php

declare(strict_types=1);

$adminPageTitle = 'Users';
$adminNavActive = 'users';

require __DIR__ . '/partials/header.php';

admin_render_placeholder('Users');

require __DIR__ . '/partials/footer.php';
