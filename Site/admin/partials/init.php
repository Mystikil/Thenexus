<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../csrf.php';
require_once __DIR__ . '/../../auth.php';

if (!is_role('admin')) {
    flash('error', 'You do not have permission to access the admin area.');
    redirect('../?p=account');
}

function admin_nav_items(): array
{
    return [
        ['href' => 'index.php', 'label' => 'Dashboard', 'slug' => 'index'],
        ['href' => 'users.php', 'label' => 'Users', 'slug' => 'users'],
        ['href' => 'characters.php', 'label' => 'Characters', 'slug' => 'characters'],
        ['href' => 'guilds.php', 'label' => 'Guilds', 'slug' => 'guilds'],
        ['href' => 'houses.php', 'label' => 'Houses', 'slug' => 'houses'],
        ['href' => 'market.php', 'label' => 'Market', 'slug' => 'market'],
        ['href' => 'news.php', 'label' => 'News', 'slug' => 'news'],
        ['href' => 'changelog.php', 'label' => 'Changelog', 'slug' => 'changelog'],
        ['href' => 'cms.php', 'label' => 'CMS', 'slug' => 'cms'],
        ['href' => 'widgets.php', 'label' => 'Widgets', 'slug' => 'widgets'],
        ['href' => 'shop.php', 'label' => 'Shop', 'slug' => 'shop'],
        ['href' => 'merge_accounts.php', 'label' => 'Merge Accounts', 'slug' => 'merge_accounts'],
        ['href' => 'logs.php', 'label' => 'Logs', 'slug' => 'logs'],
        ['href' => 'settings.php', 'label' => 'Settings', 'slug' => 'settings'],
        ['href' => 'server.php', 'label' => 'Server', 'slug' => 'server'],
    ];
}

function admin_render_placeholder(string $title): void
{
    echo '<section class="admin-section">';
    echo '<h2>' . sanitize($title) . '</h2>';
    echo '<table class="admin-table">';
    echo '<thead><tr><th>Status</th></tr></thead>';
    echo '<tbody><tr><td>TODO</td></tr></tbody>';
    echo '</table>';
    echo '</section>';
}
