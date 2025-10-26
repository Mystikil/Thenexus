<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
require_once $root . '/Site/config.php';
require_once $root . '/Site/functions.php';
require_once $root . '/Site/includes/security.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$currentGroupId = $_SESSION['account']['group_id'] ?? 1;
if ((int)$currentGroupId < (int)ADMIN_GROUP_ID) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid payload']);
    exit;
}

$token = $data['csrfToken'] ?? ($_POST['csrf_token'] ?? '');
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

function clean_string($value): string
{
    return trim((string) $value);
}

function clean_survey_tags(array $tags): array
{
    $clean = [];
    foreach ($tags as $tag) {
        if (is_string($tag)) {
            $name = clean_string($tag);
            if ($name !== '') {
                $clean[] = ['tag' => $name, 'weight' => 1.0];
            }
            continue;
        }
        if (is_array($tag)) {
            $name = clean_string($tag['tag'] ?? '');
            if ($name === '') {
                continue;
            }
            $weight = $tag['weight'] ?? 1;
            if (is_string($weight)) {
                $weight = (float) $weight;
            }
            if (!is_float($weight) && !is_int($weight)) {
                $weight = 1.0;
            }
            $clean[] = ['tag' => $name, 'weight' => max(0, (float) $weight)];
        }
    }
    return $clean;
}

function clean_string_array($value): array
{
    if (!is_array($value)) {
        return [];
    }
    $result = [];
    foreach ($value as $entry) {
        $sanitised = clean_string($entry);
        if ($sanitised !== '') {
            $result[] = $sanitised;
        }
    }
    return $result;
}

$items = $data['items'] ?? null;
if (!is_array($items)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Items payload missing']);
    exit;
}

$allowedPhases = ['I', 'II', 'III', 'IV', 'V'];
$cleanItems = [];
foreach ($items as $index => $item) {
    if (!is_array($item)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => "Invalid item at index {$index}"]);
        exit;
    }
    $id = clean_string($item['id'] ?? '');
    $title = clean_string($item['title'] ?? '');
    $status = clean_string($item['status'] ?? '');
    $description = clean_string($item['description'] ?? '');
    $phase = strtoupper(clean_string($item['phase'] ?? 'I'));
    $progress = (int) ($item['progress'] ?? 0);
    $progress = max(0, min(100, $progress));
    $shipped = !empty($item['shipped']);
    $eta = clean_string($item['eta'] ?? '');
    $owner = clean_string($item['owner'] ?? '');

    if ($id === '' || $title === '' || $status === '' || $description === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => "Missing required fields for item {$index}"]);
        exit;
    }
    if (!in_array($phase, $allowedPhases, true)) {
        $phase = 'I';
    }

    $categories = clean_string_array($item['category'] ?? []);
    $dependencies = clean_string_array($item['dependencies'] ?? []);
    $surveyTags = clean_survey_tags($item['surveyTags'] ?? []);

    $cleanItems[] = [
        'id' => $id,
        'title' => $title,
        'category' => $categories,
        'status' => $status,
        'progress' => $progress,
        'phase' => $phase,
        'shipped' => $shipped,
        'description' => $description,
        'eta' => $eta,
        'owner' => $owner,
        'surveyTags' => $surveyTags,
        'dependencies' => $dependencies
    ];
}

$metadataInput = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
$defaultPhases = [
    'I' => 'Discovery',
    'II' => 'Validation',
    'III' => 'Production',
    'IV' => 'Launch',
    'V' => 'Post-launch'
];
$metadata = [
    'updated' => clean_string($metadataInput['updated'] ?? ''),
    'description' => clean_string($metadataInput['description'] ?? ''),
    'owner' => clean_string($metadataInput['owner'] ?? ''),
    'surveyWeights' => [],
    'phases' => $defaultPhases
];

if (isset($metadataInput['surveyWeights']) && is_array($metadataInput['surveyWeights'])) {
    foreach ($metadataInput['surveyWeights'] as $key => $value) {
        $label = clean_string($key);
        if ($label === '') {
            continue;
        }
        if (is_string($value)) {
            $value = (float) $value;
        }
        if (!is_float($value) && !is_int($value)) {
            continue;
        }
        $metadata['surveyWeights'][$label] = (float) $value;
    }
}
if ($metadata['surveyWeights'] === [] && isset($metadataInput['surveyWeights']) && $metadataInput['surveyWeights'] === []) {
    $metadata['surveyWeights'] = [];
}
if (isset($metadataInput['phases']) && is_array($metadataInput['phases'])) {
    $phases = [];
    foreach ($metadataInput['phases'] as $key => $label) {
        $phaseKey = strtoupper(clean_string($key));
        if (!in_array($phaseKey, $allowedPhases, true)) {
            continue;
        }
        $phases[$phaseKey] = clean_string($label);
    }
    if ($phases !== []) {
        $metadata['phases'] = $phases;
    }
}

$result = [
    'metadata' => $metadata,
    'items' => $cleanItems
];

$target = $root . '/Site/roadmap/roadmap.json';
$tmp = $target . '.tmp';
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Encoding failure']);
    exit;
}

$fp = fopen($tmp, 'wb');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to open temporary file']);
    exit;
}

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Lock failed']);
    exit;
}

fwrite($fp, $json);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

if (!rename($tmp, $target)) {
    @unlink($tmp);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to write roadmap file']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Roadmap saved']);
