<?php

function current_theme(): string
{
    return $_SESSION['theme'] ?? 'default';
}

function theme_path(string $path = ''): string
{
    $base = __DIR__ . '/../themes/' . current_theme();
    return rtrim($base . '/' . ltrim($path, '/'), '/');
}

function theme_url(string $path = ''): string
{
    return rtrim(base_url('themes/' . current_theme()), '/') . '/' . ltrim($path, '/');
}
