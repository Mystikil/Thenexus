<?php

function flash(string $key, ?string $message = null)
{
    if ($message === null) {
        if (!isset($_SESSION['flash'][$key])) {
            return null;
        }

        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);

        return $msg;
    }

    $_SESSION['flash'][$key] = $message;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function sanitize(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function base_url(string $path = ''): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($directory === '.' || $directory === '/') {
        $directory = '';
    }

    $url = $directory;

    if ($path !== '') {
        $url .= '/' . ltrim($path, '/');
    }

    return $url === '' ? './' : $url;
}
