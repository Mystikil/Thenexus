<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

function nx_table_exists(PDO $pdo, string $table): bool {
    $sql = "SELECT 1
            FROM information_schema.tables
           WHERE table_schema = DATABASE() AND table_name = :t
           LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table]);

    return (bool) $st->fetchColumn();
}

function nx_columns(PDO $pdo, string $table): array {
    $sql = "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
            FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = :t
           ORDER BY ORDINAL_POSITION";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function nx_show_create(PDO $pdo, string $table): ?string {
    // SHOW CREATE TABLE can’t be bound either; quote the identifier safely
    $safe = '`' . str_replace('`', '``', $table) . '`';
    $row = $pdo->query("SHOW CREATE TABLE $safe")->fetch(PDO::FETCH_NUM);

    return $row[1] ?? null;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!is_master() && !is_role('admin')) {
    http_response_code(403);
    echo '<pre>Forbidden.</pre>';
    exit;
}

$pdo = db();
$text = '';

$addLine = function (string $line = '') use (&$text): void {
    $text .= $line . "\n";
};

$formatException = function (PDOException $exception): string {
    $info = $exception->errorInfo;
    $sqlState = $info[0] ?? $exception->getCode() ?? 'N/A';
    $driverCode = isset($info[1]) ? (string) $info[1] : 'N/A';
    $driverMessage = $info[2] ?? $exception->getMessage();

    return sprintf(
        'PDOException: SQLSTATE[%s] Driver Code %s: %s',
        $sqlState,
        $driverCode,
        $driverMessage
    );
};

if (!$pdo instanceof PDO) {
    $addLine('Database connection unavailable.');
    echo '<pre>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';

    return;
}

$runQuery = function (string $sql, ?callable $onSuccess = null) use ($pdo, $addLine, $formatException): void {
    $addLine('> ' . $sql);

    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($onSuccess) {
            $onSuccess($rows);
        }

        if ($rows === []) {
            $addLine('(no rows)');
        } else {
            $addLine(rtrim(print_r($rows, true)));
        }
    } catch (PDOException $exception) {
        $addLine($formatException($exception));
    }

    $addLine();
};

$accountsColumns = [];

$databaseName = null;
$runQuery('SELECT DATABASE()', function (array $rows) use (&$databaseName): void {
    $databaseName = $rows[0]['DATABASE()'] ?? null;
});

$showTablesLike = static function (string $table) use ($pdo, $addLine, &$databaseName): void {
    $addLine("> SHOW TABLES LIKE '$table'");

    if (!nx_table_exists($pdo, $table)) {
        $addLine('(no rows)');
        $addLine();

        return;
    }

    $columnName = $databaseName !== null
        ? sprintf('Tables_in_%s (%s)', $databaseName, $table)
        : sprintf('Tables_in_database (%s)', $table);
    $addLine(rtrim(print_r([[
        $columnName => $table,
    ]], true)));
    $addLine();
};

$normalizeColumns = static function (array $columns): array {
    return array_map(static function (array $column): array {
        return [
            'Field' => $column['COLUMN_NAME'] ?? null,
            'Type' => $column['COLUMN_TYPE'] ?? null,
            'Null' => $column['IS_NULLABLE'] ?? null,
            'Default' => $column['COLUMN_DEFAULT'] ?? null,
            'Extra' => $column['EXTRA'] ?? null,
        ];
    }, $columns);
};

$showColumns = static function (string $table) use ($pdo, $addLine, $normalizeColumns): array {
    $addLine("> SHOW COLUMNS FROM $table");

    if (!nx_table_exists($pdo, $table)) {
        $addLine('(table not found)');
        $addLine();

        return [];
    }

    $columns = $normalizeColumns(nx_columns($pdo, $table));

    if ($columns === []) {
        $addLine('(no rows)');
    } else {
        $addLine(rtrim(print_r($columns, true)));
    }

    $addLine();

    return $columns;
};

$showTablesLike('accounts');
$accountsColumns = $showColumns('accounts');

if (nx_table_exists($pdo, 'website_users')) {
    $showColumns('website_users');
} else {
    $addLine('> SHOW COLUMNS FROM website_users');
    $addLine('(table not found)');
    $addLine();
}

$showTablesLike('web_accounts');

$addLine('> SHOW CREATE TABLE accounts');
$createSql = nx_show_create($pdo, 'accounts');

if ($createSql === null) {
    $addLine('(table not found)');
} else {
    $addLine($createSql);
}

$addLine();

$attemptSimpleInsert = static function () use ($pdo, $addLine, $formatException): bool {
    $sql = "INSERT INTO accounts (name, password) VALUES ('_probe_', 'sha1')";
    $addLine($sql);
    $success = false;

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $addLine('Insert succeeded; rolling back.');
        $success = true;
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $addLine($formatException($exception));

        return false;
    }

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $addLine();

    return $success;
};

$buildDynamicValues = static function (array $columns): array {
    $values = [];
    $now = time();

    foreach ($columns as $column) {
        $field = $column['Field'] ?? '';
        $fieldLower = strtolower($field);
        $extra = strtolower($column['Extra'] ?? '');

        if ($field === '' || str_contains($extra, 'auto_increment')) {
            continue;
        }

        if ($fieldLower === 'name') {
            $values[$field] = '_probe_dynamic_';
            continue;
        }

        if ($fieldLower === 'password') {
            $values[$field] = 'sha1';
            continue;
        }

        $default = $column['Default'] ?? null;

        if ($default !== null) {
            $values[$field] = $default;
            continue;
        }

        $isNullable = strtoupper((string) ($column['Null'] ?? '')) === 'YES';

        if ($isNullable) {
            $values[$field] = null;
            continue;
        }

        $type = strtolower($column['Type'] ?? '');

        if (in_array($fieldLower, ['creation', 'created', 'created_at', 'updated', 'updated_at', 'lastlogin', 'lastday'], true)) {
            if (str_contains($type, 'int')) {
                $values[$field] = $now;
            } elseif (str_contains($type, 'date') || str_contains($type, 'time')) {
                $values[$field] = date('Y-m-d H:i:s', $now);
            } else {
                $values[$field] = $now;
            }

            continue;
        }

        if (in_array($fieldLower, ['type', 'group_id', 'groupid', 'premdays', 'coins', 'points', 'balance', 'level', 'experience', 'access'], true)) {
            $values[$field] = 0;
            continue;
        }

        if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
            $values[$field] = 0;
            continue;
        }

        if (str_contains($type, 'enum(')) {
            $enum = substr($type, strpos($type, '(') + 1, -1);
            $options = str_getcsv($enum, ',', "'", '\\');
            $values[$field] = $options[0] ?? '';
            continue;
        }

        if (str_contains($type, 'date')) {
            if (str_contains($type, 'time')) {
                $values[$field] = date('Y-m-d H:i:s', $now);
            } else {
                $values[$field] = date('Y-m-d', $now);
            }

            continue;
        }

        if (str_contains($type, 'char') || str_contains($type, 'text')) {
            $values[$field] = '';
            continue;
        }

        $values[$field] = '';
    }

    if (!array_key_exists('name', $values)) {
        $values['name'] = '_probe_dynamic_';
    }

    if (!array_key_exists('password', $values)) {
        $values['password'] = 'sha1';
    }

    return $values;
};

$attemptDynamicInsert = static function (array $columns) use ($pdo, $addLine, $formatException, $buildDynamicValues): void {
    if ($columns === []) {
        $addLine('Cannot build dynamic insert: no column metadata available.');
        $addLine();

        return;
    }

    $values = $buildDynamicValues($columns);
    $columnNames = array_keys($values);
    $placeholders = [];
    $params = [];

    foreach ($columnNames as $index => $column) {
        $placeholder = ':v' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $values[$column];
        $columnNames[$index] = '`' . str_replace('`', '``', $column) . '`';
    }

    $sql = 'INSERT INTO accounts (' . implode(', ', $columnNames) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $addLine($sql);
    $addLine('Values: ' . rtrim(print_r($values, true)));

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $addLine('Insert succeeded; rolling back.');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $addLine($formatException($exception));
        $addLine();

        return;
    }

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $addLine();
};

if (!$attemptSimpleInsert()) {
    $attemptDynamicInsert($accountsColumns);
}

echo '<pre>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
