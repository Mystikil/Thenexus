<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
require_once $root . '/config.php';
require_once $root . '/includes/security.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$currentGroupId = $_SESSION['account']['group_id'] ?? 1;
if ((int)$currentGroupId < (int)ADMIN_GROUP_ID) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$token = $data['csrfToken'] ?? ($_POST['csrf_token'] ?? '');
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Items payload missing']);
    exit;
}

$cleanItems = [];
$idSet = [];

foreach ($data['items'] as $item) {
    if (!is_array($item)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid item format']);
        exit;
    }

    $id = isset($item['id']) ? trim((string)$item['id']) : '';
    $title = isset($item['title']) ? trim((string)$item['title']) : '';
    $status = isset($item['status']) ? trim((string)$item['status']) : '';
    $description = isset($item['description']) ? trim((string)$item['description']) : '';
    $phase = isset($item['phase']) ? trim((string)$item['phase']) : '';
    $progress = isset($item['progress']) ? (int)$item['progress'] : 0;

    if ($id === '' || $title === '' || $status === '' || $description === '' || $phase === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Each item must include id, title, status, phase, and description']);
        exit;
    }

    if (isset($idSet[$id])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Duplicate item id detected: ' . $id]);
        exit;
    }

    $idSet[$id] = true;

    if ($progress < 0 || $progress > 100) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Progress must be between 0 and 100']);
        exit;
    }

    $category = [];
    if (isset($item['category'])) {
        if (!is_array($item['category'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Category must be an array']);
            exit;
        }
        foreach ($item['category'] as $cat) {
            $cat = trim((string)$cat);
            if ($cat !== '') {
                $category[] = filter_var($cat, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            }
        }
    }

    $surveyTags = [];
    if (isset($item['surveyTags'])) {
        if (!is_array($item['surveyTags'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'surveyTags must be an array']);
            exit;
        }
        foreach ($item['surveyTags'] as $tag) {
            $tag = trim((string)$tag);
            if ($tag !== '') {
                $surveyTags[] = filter_var($tag, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            }
        }
    }

    $dependencies = [];
    if (isset($item['dependencies'])) {
        if (!is_array($item['dependencies'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'dependencies must be an array']);
            exit;
        }
        foreach ($item['dependencies'] as $dep) {
            $dep = trim((string)$dep);
            if ($dep !== '') {
                $dependencies[] = filter_var($dep, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            }
        }
    }

    $cleanItems[] = [
        'id' => $id,
        'title' => filter_var($title, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
        'category' => $category,
        'status' => filter_var($status, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
        'progress' => $progress,
        'phase' => $phase,
        'shipped' => !empty($item['shipped']),
        'description' => filter_var($description, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
        'surveyTags' => $surveyTags,
        'dependencies' => $dependencies
    ];
}

$data['items'] = $cleanItems;
$data['updated'] = isset($data['updated']) ? trim((string)$data['updated']) : gmdate('c');
unset($data['csrfToken']);

if (isset($data['phases']) && is_array($data['phases'])) {
    $cleanPhases = [];
    foreach ($data['phases'] as $phase) {
        if (!is_array($phase)) {
            continue;
        }
        $id = isset($phase['id']) ? trim((string)$phase['id']) : '';
        $name = isset($phase['name']) ? trim((string)$phase['name']) : '';
        $description = isset($phase['description']) ? trim((string)$phase['description']) : '';
        if ($id === '' || $name === '') {
            continue;
        }
        $cleanPhases[] = [
            'id' => $id,
            'name' => filter_var($name, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
            'description' => filter_var($description, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)
        ];
    }
    $data['phases'] = $cleanPhases;
}

if (isset($data['surveyWeights']) && is_array($data['surveyWeights'])) {
    $cleanWeights = [];
    foreach ($data['surveyWeights'] as $key => $value) {
        $key = trim((string)$key);
        if ($key === '') {
            continue;
        }
        $cleanWeights[$key] = is_numeric($value) ? (float)$value : 1.0;
    }
    $data['surveyWeights'] = $cleanWeights;
}

$target = $root . '/Site/roadmap/roadmap.json';
$tmp = $target . '.tmp';
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to encode JSON']);
    exit;
}

$fp = fopen($tmp, 'wb');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to open temporary file']);
    exit;
}

if (flock($fp, LOCK_EX)) {
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if (!rename($tmp, $target)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to finalize save']);
        exit;
    }
    echo json_encode(['ok' => true, 'message' => 'Roadmap saved']);
} else {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Lock failed']);
}
