<?php

declare(strict_types=1);

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../db.php';
require __DIR__ . '/../../auth.php';
require __DIR__ . '/../../functions.php';
require __DIR__ . '/../../includes/rate_limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_out(['ok' => false, 'error' => 'Only POST is supported'], 405);
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false) {
    $rawBody = '';
}

$pdo = db();
if (!$pdo instanceof PDO) {
    json_out(['ok' => false, 'error' => 'Database unavailable'], 503);
}

try {
    rate_limit_check($pdo, client_rate_limit_key('tfs_webhook'), 60, 60);
} catch (Throwable $exception) {
    json_out(['ok' => false, 'error' => $exception->getMessage()], 429);
}

$signatureHeader = $_SERVER['HTTP_X_TFS_SIGNATURE'] ?? '';
if (!is_string($signatureHeader) || $signatureHeader === '') {
    error_log('tfs_webhook: missing signature header');
    audit_log(null, 'tfs_webhook_rejected', null, ['reason' => 'missing_signature']);
    json_out(['ok' => false, 'error' => 'Missing signature'], 401);
}

$expectedSignature = hash_hmac('sha256', $rawBody, WEBHOOK_SECRET);
if (!hash_equals($expectedSignature, $signatureHeader)) {
    error_log('tfs_webhook: invalid signature from ' . (ip_address() ?? 'unknown'));
    audit_log(null, 'tfs_webhook_rejected', null, [
        'reason' => 'invalid_signature',
        'provided' => substr($signatureHeader, 0, 18),
    ]);
    json_out(['ok' => false, 'error' => 'Invalid signature'], 401);
}

$decoded = json_decode($rawBody, true);
if (!is_array($decoded)) {
    json_out(['ok' => false, 'error' => 'Invalid JSON body'], 400);
}

$eventName = isset($decoded['event']) ? trim((string) $decoded['event']) : '';
if ($eventName === '') {
    json_out(['ok' => false, 'error' => 'event is required'], 422);
}

$data = $decoded['data'] ?? [];
if ($data !== null && !is_array($data)) {
    json_out(['ok' => false, 'error' => 'data must be an object'], 422);
}

$timestamp = $decoded['ts'] ?? null;
if ($timestamp instanceof DateTimeInterface) {
    $timestamp = (int) $timestamp->format('U');
}

if (is_string($timestamp) && ctype_digit($timestamp)) {
    $timestamp = (int) $timestamp;
}

if (!is_int($timestamp) || $timestamp <= 0) {
    json_out(['ok' => false, 'error' => 'ts must be a positive unix timestamp'], 422);
}

$occurred = DateTimeImmutable::createFromFormat('U', (string) $timestamp);
if (!$occurred instanceof DateTimeImmutable) {
    json_out(['ok' => false, 'error' => 'Unable to parse timestamp'], 422);
}

$payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payloadJson === false) {
    json_out(['ok' => false, 'error' => 'Unable to encode payload'], 500);
}

$insert = $pdo->prepare('INSERT INTO server_events (event_type, payload, occurred_at, signature) VALUES (:event_type, :payload, :occurred_at, :signature)');
$insert->execute([
    'event_type' => $eventName,
    'payload' => $payloadJson,
    'occurred_at' => $occurred->format('Y-m-d H:i:s'),
    'signature' => $signatureHeader,
]);

audit_log(null, 'tfs_webhook_accepted', null, [
    'event' => $eventName,
    'event_id' => (int) $pdo->lastInsertId(),
]);

json_out(['ok' => true]);
