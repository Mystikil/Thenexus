<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script may only be executed from the command line." . PHP_EOL;
    exit(1);
}

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../functions.php';
require_once __DIR__ . '/../widgets/_registry.php';

$pdo = db();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection unavailable." . PHP_EOL);
    exit(1);
}

$onlineCount = 0;
try {
    if (widget_table_has_column($pdo, 'players', 'online')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM players WHERE online = 1');
        $onlineCount = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
    } elseif (widget_table_exists($pdo, 'players_online')) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM players_online');
        $onlineCount = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Warning: unable to determine online player count: ' . $exception->getMessage() . PHP_EOL);
}

$uptimeSeconds = null;
$tps = null;
$worldTime = null;

if (widget_table_exists($pdo, 'server_config')) {
    try {
        $configStmt = $pdo->prepare('SELECT config, value FROM server_config WHERE config IN (:uptime1, :uptime2, :tps, :world, :world_alt)');
        $configStmt->execute([
            ':uptime1' => 'uptime',
            ':uptime2' => 'uptime_seconds',
            ':tps' => 'tps',
            ':world' => 'world_time',
            ':world_alt' => 'worldTime',
        ]);
        while ($row = $configStmt->fetch(PDO::FETCH_ASSOC)) {
            $key = strtolower((string) ($row['config'] ?? ''));
            $value = $row['value'] ?? null;
            if ($value === null) {
                continue;
            }

            if (in_array($key, ['uptime', 'uptime_seconds'], true)) {
                $uptimeSeconds = (int) $value;
            } elseif ($key === 'tps') {
                $tps = is_numeric($value) ? (float) $value : null;
            } elseif (in_array($key, ['world_time', 'worldtime'], true)) {
                $worldTime = (string) $value;
            }
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Warning: unable to read server_config: ' . $exception->getMessage() . PHP_EOL);
    }
}

try {
    $insert = $pdo->prepare('INSERT INTO server_status_snapshot (online_count, uptime_seconds, tps, world_time) VALUES (:online_count, :uptime_seconds, :tps, :world_time)');
    $insert->execute([
        'online_count' => $onlineCount,
        'uptime_seconds' => $uptimeSeconds,
        'tps' => $tps,
        'world_time' => $worldTime,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: unable to store server status snapshot: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Status snapshot recorded at ' . date('Y-m-d H:i:s') . PHP_EOL;
exit(0);
