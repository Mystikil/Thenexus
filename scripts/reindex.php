#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be executed from the command line." . PHP_EOL;
    exit(1);
}

require __DIR__ . '/../Site/config.php';
require __DIR__ . '/../Site/db.php';
require __DIR__ . '/../Site/lib/server_paths.php';
require __DIR__ . '/../Site/lib/items_indexer.php';
require __DIR__ . '/../Site/lib/monster_indexer.php';
require __DIR__ . '/../Site/lib/spell_indexer.php';

$pdo = db();
$exitCode = 0;

echo "Re-indexing items, monsters, and spells..." . PHP_EOL;

try {
    $items = nx_index_items($pdo);
    echo sprintf("Items indexed: %d (source: %s)%s", $items['count'] ?? 0, $items['source'] ?? 'unknown', PHP_EOL);
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, 'Item indexer error: ' . $exception->getMessage() . PHP_EOL);
}

try {
    $monsters = nx_index_monsters($pdo);
    echo sprintf(
        "Monsters indexed: %d, loot entries: %d (source: %s)%s",
        $monsters['monsters'] ?? 0,
        $monsters['loot'] ?? 0,
        $monsters['source'] ?? 'unknown',
        PHP_EOL
    );
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, 'Monster indexer error: ' . $exception->getMessage() . PHP_EOL);
}

try {
    $spells = nx_index_spells($pdo);
    echo sprintf(
        "Spells indexed: %d (source: %s)%s",
        $spells['count'] ?? 0,
        $spells['source'] ?? 'unknown',
        PHP_EOL
    );
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, 'Spell indexer error: ' . $exception->getMessage() . PHP_EOL);
}

echo "Done." . PHP_EOL;
exit($exitCode);
