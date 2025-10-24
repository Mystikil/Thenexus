<?php

require __DIR__ . '/../config.php';
require __DIR__ . '/../functions.php';

if (($_GET['secret'] ?? '') !== BRIDGE_SECRET) {
    json_out(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

json_out([
    'status' => 'ok',
    'jobs' => [],
]);
