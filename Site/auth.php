<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_passwords.php';

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $sql = sprintf(
        'SELECT wu.*, a.%s AS account_id, a.%s AS account_name, a.email AS account_email FROM website_users wu '
        . 'LEFT JOIN %s a ON a.email = wu.email WHERE wu.id = :id LIMIT 1',
        TFS_ACCOUNT_ID_COL,
        TFS_NAME_COL,
        TFS_ACCOUNTS_TABLE
    );

    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user === false) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function is_role(string $roleOrAbove): bool
{
    $roles = [
        'user' => 0,
        'mod' => 1,
        'gm' => 2,
        'admin' => 3,
        'owner' => 4,
    ];

    $roleKey = strtolower($roleOrAbove);
    $targetRank = $roles[$roleKey] ?? null;

    if ($targetRank === null) {
        return false;
    }

    $user = current_user();

    if ($user === null) {
        return false;
    }

    $userRole = strtolower((string) ($user['role'] ?? ''));
    $userRank = $roles[$userRole] ?? null;

    if ($userRank === null) {
        return false;
    }

    return $userRank >= $targetRank;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    flash('error', 'You must be logged in to access that page.');
    redirect('?p=account');
}

function register(string $email, string $password): array
{
    $pdo = db();
    $errors = [];

    $email = trim($email);

    if (!nx_password_rate_limit($pdo, 'register', 5, 60)) {
        return [
            'success' => false,
            'errors' => ['Too many registration attempts. Please try again later.'],
        ];
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($errors !== []) {
        return [
            'success' => false,
            'errors' => $errors,
        ];
    }

    $userExists = $pdo->prepare('SELECT id FROM website_users WHERE email = :email LIMIT 1');
    $userExists->execute(['email' => $email]);

    if ($userExists->fetch()) {
        return [
            'success' => false,
            'errors' => ['An account with that email already exists.'],
        ];
    }

    $accountExistsSql = sprintf(
        'SELECT %s FROM %s WHERE email = :email LIMIT 1',
        TFS_ACCOUNT_ID_COL,
        TFS_ACCOUNTS_TABLE
    );

    $accountExists = $pdo->prepare($accountExistsSql);
    $accountExists->execute(['email' => $email]);

    if ($accountExists->fetch()) {
        return [
            'success' => false,
            'errors' => ['An account with that email already exists.'],
        ];
    }

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $webHash = nx_hash_web_secure($password);
        $websiteInsert = $pdo->prepare('INSERT INTO website_users (email, pass_hash, created_at) VALUES (:email, :pass_hash, NOW())');
        $websiteInsert->execute([
            'email' => $email,
            'pass_hash' => $webHash,
        ]);

        $userId = (int) $pdo->lastInsertId();
        $accountName = nx_generate_account_name($pdo, $email);

        $accountFields = [
            TFS_NAME_COL => $accountName,
            TFS_PASS_COL => str_repeat('0', 40),
            'email' => $email,
            'creation' => time(),
        ];

        if (nx_password_supports_salt()) {
            $accountFields[SALT_COL] = '';
        }

        $columns = array_keys($accountFields);
        $placeholders = [];
        $params = [];

        foreach ($accountFields as $column => $value) {
            $placeholders[] = ':' . $column;
            $params[$column] = $value;
        }

        $accountSql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            TFS_ACCOUNTS_TABLE,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $accountStmt = $pdo->prepare($accountSql);
        $accountStmt->execute($params);
        $accountId = (int) $pdo->lastInsertId();

        nx_password_set($pdo, $accountId, $password);

        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'success' => false,
            'errors' => ['Unable to create your account at this time. Please try again.'],
        ];
    }

    $sql = sprintf(
        'SELECT wu.*, a.%s AS account_id, a.%s AS account_name, a.email AS account_email FROM website_users wu '
        . 'LEFT JOIN %s a ON a.email = wu.email WHERE wu.id = :id LIMIT 1',
        TFS_ACCOUNT_ID_COL,
        TFS_NAME_COL,
        TFS_ACCOUNTS_TABLE
    );

    $userStmt = $pdo->prepare($sql);
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch();

    if ($user === false) {
        return [
            'success' => false,
            'errors' => ['There was a problem completing your registration.'],
        ];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    audit_log($userId, 'register', null, [
        'email' => $user['email'],
        'account_id' => $user['account_id'],
    ]);

    return [
        'success' => true,
        'user' => $user,
    ];
}

function login(string $accountNameOrEmail, string $password): array
{
    $pdo = db();
    $identifier = trim($accountNameOrEmail);
    $errors = [];

    if ($identifier === '') {
        $errors[] = 'Account name or email is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if ($errors !== []) {
        return [
            'success' => false,
            'errors' => $errors,
        ];
    }

    if (!nx_password_rate_limit($pdo, 'login', 10, 60)) {
        return [
            'success' => false,
            'errors' => ['Too many login attempts. Please try again later.'],
        ];
    }

    $result = nx_password_verify_account($pdo, $identifier, $password);

    if (!($result['ok'] ?? false) || !is_array($result['userRow'])) {
        return [
            'success' => false,
            'errors' => ['Invalid account credentials.'],
        ];
    }

    $user = $result['userRow'];

    if ((int) ($user['id'] ?? 0) === 0) {
        $accountEmail = (string) ($user['account_email'] ?? '');

        if ($accountEmail === '') {
            return [
                'success' => false,
                'errors' => ['Unable to load your account profile. Please contact support.'],
            ];
        }

        try {
            $passHash = nx_hash_web_secure($password);
            $insert = $pdo->prepare('INSERT INTO website_users (email, pass_hash, created_at) VALUES (:email, :pass_hash, NOW())');
            $insert->execute([
                'email' => $accountEmail,
                'pass_hash' => $passHash,
            ]);

            $newId = (int) $pdo->lastInsertId();

            $sql = sprintf(
                'SELECT wu.*, a.%s AS account_id, a.%s AS account_name, a.email AS account_email FROM website_users wu '
                . 'LEFT JOIN %s a ON a.email = wu.email WHERE wu.id = :id LIMIT 1',
                TFS_ACCOUNT_ID_COL,
                TFS_NAME_COL,
                TFS_ACCOUNTS_TABLE
            );

            $freshStmt = $pdo->prepare($sql);
            $freshStmt->execute(['id' => $newId]);
            $freshUser = $freshStmt->fetch();

            if ($freshUser !== false) {
                $user = $freshUser;
            } else {
                $user['id'] = $newId;
                $user['pass_hash'] = $passHash;
            }
        } catch (PDOException $exception) {
            return [
                'success' => false,
                'errors' => ['Unable to load your account profile. Please contact support.'],
            ];
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    audit_log((int) $user['id'], 'login', null, ['used' => $result['used'] ?? 'tfs']);

    nx_on_successful_login_upgrade($pdo, (int) $user['id'], $password);

    return [
        'success' => true,
        'user' => $user,
    ];
}

function logout(): void
{
    $user = current_user();

    if ($user !== null) {
        audit_log((int) $user['id'], 'logout');
    }

    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function audit_log(?int $userId, string $action, ?array $before = null, ?array $after = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_log (user_id, action, before_json, after_json, ip) VALUES (:user_id, :action, :before_json, :after_json, :ip)');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip' => ip_address(),
        ]);
    } catch (Throwable $exception) {
        // Swallow logging errors so auth flow continues.
    }
}

function ip_address(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!is_string($ip) || $ip === '') {
        return null;
    }

    return substr($ip, 0, 45);
}
