<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_admin('admin');

$adminPageTitle = 'Logs';
$adminNavActive = 'logs';

$pdo = db();
if (!$pdo instanceof PDO) {
    require __DIR__ . '/partials/header.php';
    echo '<section class="admin-section"><h2>Logs</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';
    return;
}

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'audit';
$tab = preg_replace('/[^a-z]/', '', $tab);
if ($tab === '') {
    $tab = 'audit';
}

$returnTo = $_POST['return_to'] ?? $_GET['return_to'] ?? null;
if ($returnTo !== null) {
    $returnTo = trim((string) $returnTo);
    if ($returnTo === '' || !preg_match('#^[a-z0-9_./?&=-]+$#i', $returnTo)) {
        $returnTo = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!csrf_validate($token)) {
        flash('error', 'The request could not be validated.');
        redirect($returnTo ?? ('logs.php?tab=' . $tab));
    }

    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'update_event_status':
            $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
            $handledValue = isset($_POST['handled']) && (int) $_POST['handled'] === 1 ? 1 : 0;

            if ($eventId <= 0) {
                flash('error', 'Invalid event selected.');
                redirect($returnTo ?? 'logs.php?tab=events');
            }

            $update = $pdo->prepare('UPDATE server_events SET handled = :handled WHERE id = :id');
            $update->execute([
                'handled' => $handledValue,
                'id' => $eventId,
            ]);

            flash('success', $handledValue === 1 ? 'Event marked as handled.' : 'Event marked as pending.');
            redirect($returnTo ?? 'logs.php?tab=events');
            break;
        default:
            flash('error', 'Unknown admin action requested.');
            redirect($returnTo ?? ('logs.php?tab=' . $tab));
    }
}

require __DIR__ . '/partials/header.php';

$successMessage = take_flash('success');
$errorMessage = take_flash('error');

if ($successMessage) {
    echo '<div class="admin-alert admin-alert--success">' . sanitize($successMessage) . '</div>';
}

if ($errorMessage) {
    echo '<div class="admin-alert admin-alert--error">' . sanitize($errorMessage) . '</div>';
}

$tabs = [
    'audit' => 'Audit Logs',
    'events' => 'Events',
];

echo '<ul class="nav nav-tabs mb-3">';
foreach ($tabs as $slug => $label) {
    $active = $slug === $tab ? ' active' : '';
    echo '<li class="nav-item"><a class="nav-link' . $active . '" href="logs.php?tab=' . urlencode($slug) . '">' . sanitize($label) . '</a></li>';
}
echo '</ul>';

if ($tab === 'events') {
    nx_admin_render_events_log($pdo);
} else {
    admin_render_placeholder('Audit Logs');
}

require __DIR__ . '/partials/footer.php';

function nx_admin_render_events_log(PDO $pdo): void
{
    $eventTypes = [];
    try {
        $typeStmt = $pdo->query('SELECT DISTINCT event_type FROM server_events ORDER BY event_type ASC');
        $eventTypes = $typeStmt !== false ? $typeStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $exception) {
        $eventTypes = [];
    }

    $typeFilter = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
    if ($typeFilter !== '' && !preg_match('/^[a-z0-9_.:-]+$/i', $typeFilter)) {
        $typeFilter = '';
    }

    $dateFilter = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
    if ($dateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
        $dateFilter = '';
    }

    $handledFilter = isset($_GET['handled']) ? trim((string) $_GET['handled']) : '';
    if (!in_array($handledFilter, ['', '0', '1'], true)) {
        $handledFilter = '';
    }

    $perPage = 25;
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    $offset = ($page - 1) * $perPage;

    $conditions = [];
    $params = [];

    if ($typeFilter !== '') {
        $conditions[] = 'event_type = :event_type';
        $params['event_type'] = $typeFilter;
    }

    if ($dateFilter !== '') {
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateFilter) ?: null;
        if ($startDate instanceof DateTimeImmutable) {
            $endDate = $startDate->modify('+1 day');
            $conditions[] = 'occurred_at >= :start_date AND occurred_at < :end_date';
            $params['start_date'] = $startDate->format('Y-m-d 00:00:00');
            $params['end_date'] = $endDate->format('Y-m-d 00:00:00');
        }
    }

    if ($handledFilter !== '') {
        $conditions[] = 'handled = :handled';
        $params['handled'] = (int) $handledFilter;
    }

    $whereSql = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $totalEvents = 0;
    try {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM server_events' . $whereSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $totalEvents = (int) $countStmt->fetchColumn();
    } catch (Throwable $exception) {
        echo '<div class="admin-alert admin-alert--error">Unable to query server events: ' . sanitize($exception->getMessage()) . '</div>';
        return;
    }

    $events = [];
    try {
        $querySql = 'SELECT id, event_type, payload, occurred_at, received_at, signature, handled FROM server_events'
            . $whereSql . ' ORDER BY occurred_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($querySql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        echo '<div class="admin-alert admin-alert--error">Unable to load events: ' . sanitize($exception->getMessage()) . '</div>';
        return;
    }

    $queryParams = [
        'tab' => 'events',
        'type' => $typeFilter,
        'date' => $dateFilter,
        'handled' => $handledFilter,
    ];

    echo '<section class="admin-section">';
    echo '<h2>Server Events</h2>';
    echo '<form class="row g-2 mb-3" method="get" action="logs.php">';
    echo '<input type="hidden" name="tab" value="events">';

    echo '<div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="type">';
    echo '<option value="">All types</option>';
    foreach ($eventTypes as $type) {
        $type = (string) $type;
        $selected = $type === $typeFilter ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    echo '</select></div>';

    echo '<div class="col-md-3"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="' . htmlspecialchars($dateFilter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></div>';

    echo '<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="handled">';
    echo '<option value=""' . ($handledFilter === '' ? ' selected' : '') . '>All</option>';
    echo '<option value="0"' . ($handledFilter === '0' ? ' selected' : '') . '>Pending</option>';
    echo '<option value="1"' . ($handledFilter === '1' ? ' selected' : '') . '>Handled</option>';
    echo '</select></div>';

    echo '<div class="col-md-3 d-flex align-items-end gap-2">';
    echo '<button type="submit" class="btn btn-primary">Filter</button>';
    echo '<a class="btn btn-outline-secondary" href="logs.php?tab=events">Reset</a>';
    echo '</div>';

    echo '</form>';

    if ($events === []) {
        echo '<p class="text-muted mb-0">No events recorded for the selected filters.</p>';
        echo '</section>';
        return;
    }

    echo '<div class="table-responsive">';
    echo '<table class="admin-table">';
    echo '<thead><tr><th>ID</th><th>Type</th><th>Occurred</th><th>Received</th><th>Status</th><th>Payload</th><th>Signature</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    foreach ($events as $event) {
        $handled = (int) ($event['handled'] ?? 0) === 1;
        $payloadRaw = (string) ($event['payload'] ?? '');
        $payloadDecoded = json_decode($payloadRaw, true);
        if (is_array($payloadDecoded)) {
            $summaryParts = [];
            foreach ($payloadDecoded as $key => $value) {
                if (is_scalar($value)) {
                    $summaryParts[] = $key . '=' . (string) $value;
                }
            }
            $summary = $summaryParts !== [] ? implode(', ', array_slice($summaryParts, 0, 5)) : 'JSON payload';
            $payloadDisplay = json_encode($payloadDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $summary = mb_substr($payloadRaw, 0, 120);
            $payloadDisplay = $payloadRaw;
        }

        $signature = (string) ($event['signature'] ?? '');
        $signatureShort = $signature !== '' ? substr($signature, 0, 16) . (strlen($signature) > 16 ? '…' : '') : '';

        $statusBadge = $handled
            ? '<span class="badge bg-success">Handled</span>'
            : '<span class="badge bg-warning text-dark">Pending</span>';

        echo '<tr>';
        echo '<td>' . (int) $event['id'] . '</td>';
        echo '<td>' . sanitize((string) $event['event_type']) . '</td>';
        echo '<td>' . sanitize((string) $event['occurred_at']) . '</td>';
        echo '<td>' . sanitize((string) $event['received_at']) . '</td>';
        echo '<td>' . $statusBadge . '</td>';
        echo '<td>';
        echo '<div>' . sanitize($summary) . '</div>';
        echo '<details class="small"><summary>View payload</summary><pre class="mb-0">' . htmlspecialchars((string) $payloadDisplay, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre></details>';
        echo '</td>';
        echo '<td>' . sanitize($signatureShort) . '</td>';
        echo '<td>';
        echo '<form method="post" action="logs.php?tab=events" class="d-inline">';
        echo '<input type="hidden" name="csrf_token" value="' . sanitize(csrf_token()) . '">';
        echo '<input type="hidden" name="action" value="update_event_status">';
        echo '<input type="hidden" name="event_id" value="' . (int) $event['id'] . '">';
        echo '<input type="hidden" name="handled" value="' . ($handled ? '0' : '1') . '">';
        echo '<input type="hidden" name="return_to" value="' . sanitize(current_request_uri()) . '">';
        echo '<button type="submit" class="btn btn-sm ' . ($handled ? 'btn-outline-secondary' : 'btn-success') . '">' . ($handled ? 'Mark Pending' : 'Mark Handled') . '</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    $totalPages = (int) ceil($totalEvents / $perPage);
    if ($totalPages > 1) {
        echo '<nav class="mt-3">';
        echo '<ul class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $queryParams['page'] = $i;
            $url = 'logs.php?' . http_build_query(array_filter($queryParams, static function ($value) {
                return $value !== '' && $value !== null;
            }));
            $active = $i === $page ? ' active' : '';
            echo '<li class="page-item' . $active . '"><a class="page-link" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $i . '</a></li>';
        }
        echo '</ul>';
        echo '</nav>';
    }

    echo '</section>';
}

function current_request_uri(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? 'logs.php?tab=events';
    if (!is_string($uri) || $uri === '') {
        return 'logs.php?tab=events';
    }
    return $uri;
}
