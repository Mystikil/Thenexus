<?php

require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/links.php';

function flash(string $key, string $message): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = $message;
}

function take_flash(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    if ($_SESSION['flash'] === []) {
        unset($_SESSION['flash']);
    }

    return $message;
}

function redirect(string $path): void
{
    nx_redirect($path);
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

function nx_current_page_slug(): string
{
    $slug = $GLOBALS['nx_current_page_slug'] ?? null;

    if (!is_string($slug) || $slug === '') {
        $slug = $_GET['p'] ?? 'home';
    }

    $slug = strtolower(trim((string) $slug));
    $slug = preg_replace('/[^a-z0-9_]/', '', $slug);

    if ($slug === '') {
        return 'home';
    }

    return $slug;
}

function nx_port_is_listening(?string $host, int $port, float $timeout = 0.75): bool
{
    $host = $host !== null && trim($host) !== '' ? trim($host) : '127.0.0.1';

    if ($port <= 0 || $port > 65535) {
        return false;
    }

    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

    if (is_resource($socket)) {
        fclose($socket);

        return true;
    }

    return false;
}

function nx_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $table = trim($table);

    if ($table === '') {
        return false;
    }

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare('SELECT name FROM sqlite_master WHERE type = :type AND name = :name LIMIT 1');
            $stmt->execute([':type' => 'table', ':name' => $table]);
            $cache[$table] = (bool) $stmt->fetchColumn();

            return $cache[$table];
        }

        $sql = 'SELECT 1 FROM information_schema.tables WHERE table_name = :name AND table_schema = DATABASE() LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':name' => $table]);
        $cache[$table] = (bool) $stmt->fetchColumn();

        return $cache[$table];
    } catch (Throwable $exception) {
        $cache[$table] = false;

        return false;
    }
}
