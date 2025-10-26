<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../functions.php';

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        $token = csrf_token();

        return '<input type="hidden" name="csrf_token" value="' . sanitize($token) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (!is_string($token) || $token === '') {
            return false;
        }

        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, (string) $token);
    }
}
