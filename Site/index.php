<?php
session_start();

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/functions.php';
$pageFile = require __DIR__ . '/routes.php';

if (!is_string($pageFile) || $pageFile === '') {
    return;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/layout.php';
include __DIR__ . '/includes/footer.php';
