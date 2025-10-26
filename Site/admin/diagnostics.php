<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/server_paths.php';

require_admin('admin');

$adminPageTitle = 'Diagnostics';
$adminNavActive = 'diagnostics';

require __DIR__ . '/partials/header.php';

$checks = [];
$pdo = db();
$pdoOk = $pdo instanceof PDO;

$checks[] = [
    'label' => 'Database connection',
    'status' => $pdoOk,
    'message' => $pdoOk ? 'Connected successfully.' : 'Database connection unavailable.',
];

$requiredTables = ['website_users', 'accounts', 'item_index', 'monster_index', 'rcon_jobs', 'settings'];
$missingTables = [];

if ($pdoOk) {
    foreach ($requiredTables as $table) {
        if (!nx_table_exists($pdo, $table)) {
            $missingTables[] = $table;
        }
    }
}

$checks[] = [
    'label' => 'Required tables present',
    'status' => $pdoOk && $missingTables === [],
    'message' => !$pdoOk
        ? 'Unable to verify tables without a database connection.'
        : ($missingTables === [] ? 'All required tables are available.' : 'Missing tables: ' . implode(', ', $missingTables)),
];

$paths = nx_server_paths();
$serverRoot = $paths['server_root'] ?? '';
$configLua = $paths['config_lua'] ?? '';
$rootOk = $serverRoot !== '' && is_dir($serverRoot);
$configOk = $configLua !== '' && is_file($configLua);

$checks[] = [
    'label' => 'SERVER_PATH & config.lua',
    'status' => $rootOk && $configOk,
    'message' => ($rootOk ? 'Server path resolved.' : 'Server path not found.') . ' ' . ($configOk ? 'config.lua located.' : 'config.lua missing.'),
];

$webhookStatus = false;
$webhookMessage = $pdoOk ? 'server_events table not found.' : 'Database connection unavailable.';

if ($pdoOk && nx_table_exists($pdo, 'server_events')) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO server_events (event_name, payload) VALUES (:event_name, :payload)');
        $stmt->execute([
            'event_name' => 'diagnostics_check',
            'payload' => json_encode(['ts' => time()]),
        ]);
        $webhookStatus = true;
        $webhookMessage = 'Insert permitted.';
        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $webhookStatus = false;
        $webhookMessage = 'Insert failed: ' . $exception->getMessage();
    }
}

$checks[] = [
    'label' => 'Webhook event log writable',
    'status' => $webhookStatus,
    'message' => $webhookMessage,
];

$queueThreshold = 100;
$queueStatus = false;
$queueMessage = $pdoOk ? 'rcon_jobs table not found.' : 'Database connection unavailable.';

if ($pdoOk && nx_table_exists($pdo, 'rcon_jobs')) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM rcon_jobs WHERE status = 'queued'");
        $queuedCount = (int) $stmt->fetchColumn();
        $queueStatus = $queuedCount < $queueThreshold;
        $queueMessage = sprintf('%d job(s) queued (threshold < %d).', $queuedCount, $queueThreshold);
    } catch (Throwable $exception) {
        $queueStatus = false;
        $queueMessage = 'Unable to read queue: ' . $exception->getMessage();
    }
}

$checks[] = [
    'label' => 'RCON queue within limits',
    'status' => $queueStatus,
    'message' => $queueMessage,
];

$requiredExtensions = ['pdo_mysql', 'json', 'mbstring'];
$missingExtensions = [];
foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $missingExtensions[] = $extension;
    }
}

$checks[] = [
    'label' => 'PHP extensions',
    'status' => $missingExtensions === [],
    'message' => $missingExtensions === [] ? 'All required extensions are loaded.' : 'Missing extensions: ' . implode(', ', $missingExtensions),
];
?>
<section class="admin-section">
    <h2>Diagnostics</h2>
    <p>Quick self-check to verify that Nexus AAC has everything it needs to operate.</p>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Check</th>
                <th>Status</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><?php echo sanitize($check['label']); ?></td>
                    <td>
                        <span class="admin-status <?php echo $check['status'] ? 'admin-status--ok' : 'admin-status--error'; ?>">
                            <?php echo $check['status'] ? 'OK' : 'FAIL'; ?>
                        </span>
                    </td>
                    <td><?php echo sanitize($check['message']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
require __DIR__ . '/partials/footer.php';
