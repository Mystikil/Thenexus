<?php

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login(string $identifier, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM accounts WHERE name = :identifier OR email = :identifier LIMIT 1');
    $stmt->execute(['identifier' => $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        return false;
    }

    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'] ?? null,
    ];

    return true;
}

function logout(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

function register(string $name, string $email, string $password): bool
{
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = db()->prepare('INSERT INTO accounts (name, email, password, created_at) VALUES (:name, :email, :password, NOW())');

    try {
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hash,
        ]);
    } catch (PDOException $e) {
        return false;
    }

    return login($name, $password);
}
