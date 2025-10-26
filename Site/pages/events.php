<?php
$pdo = db();

if (!$pdo instanceof PDO) {
    echo '<section class="page page--events"><h2>Server Events</h2><p class="text-muted">Event history is currently unavailable.</p></section>';
    return;
}

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'recent';
$tab = preg_replace('/[^a-z]/', '', $tab);
if ($tab === '') {
    $tab = 'recent';
}

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

$perPage = 20;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $perPage;

?>
<section class="page page--events">
    <h2>Server Events</h2>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link<?php echo $tab === 'recent' ? ' active' : ''; ?>" href="?p=events&amp;tab=recent">Recent (Live)</a></li>
        <li class="nav-item"><a class="nav-link<?php echo $tab === 'upcoming' ? ' active' : ''; ?>" href="?p=events&amp;tab=upcoming">Upcoming (Scheduled)</a></li>
    </ul>

    <?php if ($tab === 'upcoming'): ?>
        <?php
        $upcoming = [];
        try {
            $stmt = $pdo->prepare('SELECT name, source, file_path, cron, params, next_run FROM server_schedule ORDER BY (next_run IS NULL) ASC, next_run ASC LIMIT 50');
            $stmt->execute();
            $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            echo '<div class="alert alert-danger">Unable to load schedule: ' . sanitize($exception->getMessage()) . '</div>';
        }
        ?>
        <?php if ($upcoming === []): ?>
            <p class="text-muted">No scheduled events were found. Run the schedule indexer from the admin panel to populate this list.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Source</th>
                            <th scope="col">Next Run</th>
                            <th scope="col">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $row): ?>
                            <?php
                            $params = [];
                            if (isset($row['params'])) {
                                $decoded = json_decode((string) $row['params'], true);
                                if (is_array($decoded)) {
                                    $params = $decoded;
                                }
                            }
                            $attributes = isset($params['attributes']) && is_array($params['attributes']) ? $params['attributes'] : [];
                            $nextRun = isset($row['next_run']) && $row['next_run'] !== null ? sanitize((string) $row['next_run']) : 'TBD';
                            $cron = isset($row['cron']) && $row['cron'] !== null ? (string) $row['cron'] : '';
                            $detailsParts = [];
                            foreach ($attributes as $key => $value) {
                                if ($value === '' || is_array($value) || is_object($value)) {
                                    continue;
                                }
                                $detailsParts[] = sanitize((string) $key) . '=' . sanitize((string) $value);
                            }
                            $details = $detailsParts !== [] ? implode(', ', array_slice($detailsParts, 0, 6)) : ($cron !== '' ? sanitize($cron) : '—');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo sanitize((string) ($row['name'] ?? 'Unnamed event')); ?></div>
                                    <div class="small text-muted"><?php echo sanitize((string) ($row['file_path'] ?? '')); ?></div>
                                </td>
                                <td><?php echo ucfirst(sanitize((string) ($row['source'] ?? ''))); ?></td>
                                <td><?php echo $nextRun; ?></td>
                                <td><?php echo $details; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php
        $conditions = [];
        $params = [];

        if ($typeFilter !== '') {
            $conditions[] = 'event_type = :event_type';
            $params['event_type'] = $typeFilter;
        }

        if ($dateFilter !== '') {
            $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $dateFilter) ?: null;
            if ($dateObj instanceof DateTimeImmutable) {
                $endDate = $dateObj->modify('+1 day');
                $conditions[] = 'occurred_at >= :start_date AND occurred_at < :end_date';
                $params['start_date'] = $dateObj->format('Y-m-d 00:00:00');
                $params['end_date'] = $endDate->format('Y-m-d 00:00:00');
            }
        }

        $whereSql = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $total = 0;
        try {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM server_events' . $whereSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue(':' . $key, $value);
            }
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();
        } catch (Throwable $exception) {
            echo '<div class="alert alert-danger">Unable to count events: ' . sanitize($exception->getMessage()) . '</div>';
            $total = 0;
        }

        $events = [];
        try {
            $sql = 'SELECT event_type, payload, occurred_at FROM server_events' . $whereSql . ' ORDER BY occurred_at DESC LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            echo '<div class="alert alert-danger">Unable to load events: ' . sanitize($exception->getMessage()) . '</div>';
        }

        $query = [
            'p' => 'events',
            'tab' => 'recent',
            'type' => $typeFilter,
            'date' => $dateFilter,
        ];
        ?>
        <form class="row g-2 mb-3" method="get" action="">
            <input type="hidden" name="p" value="events">
            <input type="hidden" name="tab" value="recent">
            <div class="col-md-4">
                <label class="form-label" for="event-type">Event type</label>
                <select class="form-select" name="type" id="event-type">
                    <option value="">All types</option>
                    <?php foreach ($eventTypes as $eventType): ?>
                        <?php $eventType = (string) $eventType; ?>
                        <option value="<?php echo htmlspecialchars($eventType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"<?php echo $eventType === $typeFilter ? ' selected' : ''; ?>><?php echo htmlspecialchars($eventType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="event-date">Date</label>
                <input type="date" class="form-control" name="date" id="event-date" value="<?php echo htmlspecialchars($dateFilter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a class="btn btn-outline-secondary" href="?p=events&amp;tab=recent">Reset</a>
            </div>
        </form>

        <?php if ($events === []): ?>
            <p class="text-muted">No events found for the selected filters.</p>
        <?php else: ?>
            <div class="list-group mb-3">
                <?php foreach ($events as $event): ?>
                    <?php
                    $payloadText = '';
                    $payload = [];
                    if (isset($event['payload'])) {
                        $decoded = json_decode((string) $event['payload'], true);
                        if (is_array($decoded)) {
                            $payload = $decoded;
                        }
                    }
                    $summaryParts = [];
                    foreach ($payload as $key => $value) {
                        if (is_scalar($value)) {
                            $summaryParts[] = $key . ': ' . (string) $value;
                        }
                    }
                    $payloadText = $summaryParts !== [] ? implode(', ', array_slice($summaryParts, 0, 6)) : 'Payload available';
                    $payloadPretty = $payload !== []
                        ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string) ($event['payload'] ?? '');
                    ?>
                    <div class="list-group-item bg-dark text-light">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold"><?php echo sanitize((string) $event['event_type']); ?></div>
                                <div class="small text-muted">Occurred at <?php echo sanitize((string) $event['occurred_at']); ?></div>
                            </div>
                        </div>
                        <div class="mt-2 small">
                            <?php echo sanitize($payloadText); ?>
                        </div>
                        <?php if ($payloadPretty !== ''): ?>
                            <details class="small mt-2">
                                <summary>View payload</summary>
                                <pre class="mb-0 text-light bg-transparent border rounded p-2"><?php echo htmlspecialchars($payloadPretty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php $totalPages = (int) ceil($total / $perPage); ?>
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Event pagination">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $query['page'] = $i; ?>
                        <?php $url = '?' . http_build_query(array_filter($query, static function ($value) {
                            return $value !== '' && $value !== null;
                        })); ?>
                        <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
