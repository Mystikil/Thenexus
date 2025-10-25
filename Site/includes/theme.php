<?php

declare(strict_types=1);


/**
 * Return a list of all themes discovered in the themes directory.
 *
 * @return array<string, array<string, mixed>>
 */
function nx_all_themes(): array
{
    static $cache;

    if (is_array($cache)) {
        return $cache;
    }

    $themes = [];
    $baseDir = __DIR__ . '/../themes';

    foreach (glob($baseDir . '/*/manifest.json') as $manifestFile) {
        $manifestJson = @file_get_contents($manifestFile);

        if ($manifestJson === false) {
            continue;
        }

        $manifest = json_decode($manifestJson, true);

        if (!is_array($manifest)) {
            continue;
        }

        $directory = dirname($manifestFile);
        $fallbackSlug = basename($directory);
        $slug = isset($manifest['slug']) && is_string($manifest['slug']) && trim($manifest['slug']) !== ''
            ? trim((string) $manifest['slug'])
            : $fallbackSlug;

        $themes[$slug] = [
            'slug' => $slug,
            'name' => is_string($manifest['name'] ?? null) ? (string) $manifest['name'] : ucfirst($slug),
            'type' => is_string($manifest['type'] ?? null) ? (string) $manifest['type'] : '',
            'version' => is_string($manifest['version'] ?? null) ? (string) $manifest['version'] : '',
            'path' => $directory,
            'manifest' => $manifest,
        ];
    }

    ksort($themes);

    $cache = $themes;

    return $themes;
}

function nx_theme_path(string $slug, string $subPath = ''): string
{
    $slug = trim($slug);

    if ($slug === '') {
        $slug = 'default';
    }

    $base = __DIR__ . '/../themes/' . $slug;

    if ($subPath === '') {
        return $base;
    }

    return rtrim($base, '/') . '/' . ltrim($subPath, '/');
}

function nx_current_theme_slug(PDO $pdo): string
{
    $themes = nx_all_themes();

    $availableSlugs = array_keys($themes);
    $defaultSlug = 'default';

    static $settingsDefault;

    if ($settingsDefault === null) {
        try {
            $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :key LIMIT 1');
            $stmt->execute(['key' => 'default_theme']);
            $value = $stmt->fetchColumn();

            if (is_string($value) && $value !== '') {
                $settingsDefault = $value;
            } else {
                $settingsDefault = false;
            }
        } catch (PDOException $exception) {
            $settingsDefault = false;
        }
    }

    if (is_string($settingsDefault) && $settingsDefault !== '') {
        $defaultSlug = $settingsDefault;
    }

    if (!isset($themes[$defaultSlug]) && $availableSlugs !== []) {
        $defaultSlug = (string) reset($availableSlugs);
    }

    $user = current_user();

    if ($user !== null) {
        $preference = trim((string) ($user['theme_preference'] ?? ''));

        if ($preference !== '' && isset($themes[$preference])) {
            return $preference;
        }
    }

    if (isset($themes[$defaultSlug])) {
        return $defaultSlug;
    }

    return 'default';
}

function nx_locate_template(PDO $pdo, string $page): ?string
{
    $page = trim($page);

    if ($page === '') {
        return null;
    }

    $slug = nx_current_theme_slug($pdo);
    $templatePath = nx_theme_path($slug, 'templates/' . $page . '.php');

    if (is_file($templatePath)) {
        return $templatePath;
    }

    if ($slug !== 'default') {
        $fallback = nx_theme_path('default', 'templates/' . $page . '.php');
        if (is_file($fallback)) {
            return $fallback;
        }
    }

    return null;
}

function current_theme(): string
{
    return nx_current_theme_slug(db());
}

function theme_path(string $path = ''): string
{
    return nx_theme_path(current_theme(), $path);
}
