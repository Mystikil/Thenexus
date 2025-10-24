<?php

require __DIR__ . '/../config.php';
require __DIR__ . '/../functions.php';

$signature = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';

if (!hash_equals(WEBHOOK_SECRET, $signature)) {
    json_out(['status' => 'error', 'message' => 'Invalid signature'], 403);
}

$json = file_get_contents('php://input');

json_out([
    'status' => 'ok',
    'message' => 'Webhook received.',
    'payload' => json_decode($json, true),
]);
