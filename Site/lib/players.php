<?php

declare(strict_types=1);

function nx_players_required_defaults(PDO $pdo): array
{
    $need = [];
    $cols = $pdo->query('SHOW FULL COLUMNS FROM players')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cols as $column) {
        $field = (string) ($column['Field'] ?? '');
        $nullable = strtoupper((string) ($column['Null'] ?? '')) === 'YES';
        $hasDefault = array_key_exists('Default', $column) && $column['Default'] !== null;

        if (in_array($field, ['id', 'name', 'account_id'], true) || $nullable || $hasDefault) {
            continue;
        }

        $type = strtolower((string) ($column['Type'] ?? ''));
        $value = 0;

        if (preg_match('/char|varchar|text/', $type) === 1) {
            $value = '';
        }

        if (in_array($field, ['sex'], true)) {
            $value = 0;
        }

        if (in_array($field, ['looktype'], true)) {
            $value = 128;
        }

        if (in_array($field, ['level'], true)) {
            $value = 8;
        }

        if (in_array($field, ['health', 'healthmax'], true)) {
            $value = 150;
        }

        if (in_array($field, ['mana', 'manamax'], true)) {
            $value = 0;
        }

        if (in_array($field, ['soul'], true)) {
            $value = 0;
        }

        if (in_array($field, ['maglevel'], true)) {
            $value = 0;
        }

        if (in_array($field, ['town_id'], true)) {
            $value = 1;
        }

        if (in_array($field, ['posx', 'posy', 'posz'], true)) {
            $value = 0;
        }

        if (in_array($field, ['lastlogin', 'lastday', 'deletion'], true)) {
            $value = 0;
        }

        $need[$field] = $value;
    }

    return $need;
}

function nx_insert_player(PDO $pdo, array $data): int
{
    $columns = ['account_id' => (int) $data['account_id'], 'name' => (string) $data['name']] + nx_players_required_defaults($pdo);

    foreach (['vocation', 'sex', 'town_id', 'looktype', 'lookaddons', 'lookbody', 'looklegs', 'lookfeet', 'lookhead'] as $key) {
        if (array_key_exists($key, $data)) {
            $columns[$key] = $data[$key];
        }
    }

    $fields = '`' . implode('`,`', array_keys($columns)) . '`';
    $placeholders = rtrim(str_repeat('?,', count($columns)), ',');
    $stmt = $pdo->prepare("INSERT INTO players ($fields) VALUES ($placeholders)");
    $stmt->execute(array_values($columns));

    return (int) $pdo->lastInsertId();
}

function nx_name_is_taken(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM players WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([$name]);

    return (bool) $stmt->fetchColumn();
}
