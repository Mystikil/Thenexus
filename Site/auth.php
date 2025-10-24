<?php

declare(strict_types=1);

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM website_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user === false) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
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

    $stmt = $pdo->prepare('SELECT id FROM website_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);

    if ($stmt->fetch()) {
        return [
            'success' => false,
            'errors' => ['An account with that email already exists.'],
        ];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('INSERT INTO website_users (email, pass_hash, created_at) VALUES (:email, :pass_hash, NOW())');
        $stmt->execute([
            'email' => $email,
            'pass_hash' => $hash,
        ]);
    } catch (PDOException $exception) {
        return [
            'success' => false,
            'errors' => ['Unable to create your account at this time. Please try again.'],
        ];
    }

    $userId = (int) $pdo->lastInsertId();

    $userStmt = $pdo->prepare('SELECT * FROM website_users WHERE id = :id LIMIT 1');
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

    audit_log($userId, 'register', null, ['email' => $user['email']]);

    return [
        'success' => true,
        'user' => $user,
    ];
}

function login(string $email, string $password): array
{
    $email = trim($email);
    $errors = [];

    if ($email === '') {
        $errors[] = 'Email is required.';
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

    $stmt = db()->prepare('SELECT * FROM website_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['pass_hash'])) {
        return [
            'success' => false,
            'errors' => ['Invalid email or password.'],
        ];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    audit_log((int) $user['id'], 'login');

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
