<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Only POST is supported.']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Empty request body.']);
    exit;
}

$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (!isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['message' => 'JSON must contain an items array.']);
    exit;
}

$errors = [];
foreach ($data['items'] as $index => $item) {
    $label = 'Item ' . ($index + 1);
    if (!isset($item['id']) || $item['id'] === '') {
        $errors[] = "$label: missing id";
    }
    if (!isset($item['title']) || $item['title'] === '') {
        $errors[] = "$label: missing title";
    }
    if (!isset($item['category']) || !is_array($item['category']) || count($item['category']) === 0) {
        $errors[] = "$label: category must be a non-empty array";
    }
    if (!isset($item['status']) || $item['status'] === '') {
        $errors[] = "$label: missing status";
    }
    if (!isset($item['progress']) || !is_numeric($item['progress']) || $item['progress'] < 0 || $item['progress'] > 100) {
        $errors[] = "$label: progress must be between 0 and 100";
    }
    if (!isset($item['phase']) || $item['phase'] === '') {
        $errors[] = "$label: missing phase";
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['message' => implode('\n', $errors)]);
    exit;
}

$path = __DIR__ . '/roadmap.json';
$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($encoded === false) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to encode JSON.']);
    exit;
}

if (!is_writable($path)) {
    http_response_code(400);
    echo json_encode(['message' => 'roadmap.json is not writable. Check permissions.']);
    exit;
}

if (file_put_contents($path, $encoded . "\n") === false) {
    http_response_code(500);
    echo json_encode(['message' => 'Unable to write roadmap.json.']);
    exit;
}

echo json_encode(['message' => 'roadmap.json saved successfully.']);
