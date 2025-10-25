<?php

declare(strict_types=1);

require_once __DIR__ . '/_cache.php';

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

if (!function_exists('db')) {
    require_once __DIR__ . '/../db.php';
}

if (!function_exists('sanitize')) {
    require_once __DIR__ . '/../functions.php';
}

if (!function_exists('vocation_name_widget')) {
    function vocation_name_widget(int $vocationId): string
    {
        $vocations = [
            0 => 'None',
            1 => 'Sorcerer',
            2 => 'Druid',
            3 => 'Paladin',
            4 => 'Knight',
            5 => 'Master Sorcerer',
            6 => 'Elder Druid',
            7 => 'Royal Paladin',
            8 => 'Elite Knight',
        ];

        return $vocations[$vocationId] ?? 'Unknown';
    }
}

global $WIDGETS;

$WIDGETS = [
    'top_levels' => ['title' => 'Top Players', 'renderer' => 'widget_top_levels', 'ttl' => 60],
    'top_guilds' => ['title' => 'Top Guilds', 'renderer' => 'widget_top_guilds', 'ttl' => 120],
    'online' => ['title' => 'Who’s Online', 'renderer' => 'widget_online', 'ttl' => 20],
    'recent_deaths' => ['title' => 'Recent Deaths', 'renderer' => 'widget_recent_deaths', 'ttl' => 60],
    'server_status' => ['title' => 'Server Status', 'renderer' => 'widget_server_status', 'ttl' => 15],
    'vote_links' => ['title' => 'Vote & Support', 'renderer' => 'widget_vote_links', 'ttl' => 3600],
];

function widget_top_levels(PDO $pdo, int $limit = 5): string
{
    $stmt = $pdo->prepare('SELECT name, level FROM players WHERE deletion = 0 ORDER BY level DESC, experience DESC, name ASC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $players = $stmt->fetchAll();

    if ($players === []) {
        return '<p class="widget-empty">No players found.</p>';
    }

    $html = '<ol class="widget-list widget-list--top-levels">';
    foreach ($players as $player) {
        $html .= '<li>';
        $html .= '<span class="widget-item__name">' . sanitize($player['name']) . '</span>';
        $html .= '<span class="widget-item__meta">Lvl ' . (int) $player['level'] . '</span>';
        $html .= '</li>';
    }
    $html .= '</ol>';

    return $html;
}

function widget_top_guilds(PDO $pdo, int $limit = 5): string
{
    $stmt = $pdo->prepare('SELECT g.name, COUNT(gm.player_id) AS member_count
        FROM guilds g
        LEFT JOIN guild_membership gm ON gm.guild_id = g.id
        GROUP BY g.id, g.name
        ORDER BY member_count DESC, g.name ASC
        LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $guilds = $stmt->fetchAll();

    if ($guilds === []) {
        return '<p class="widget-empty">No guilds found.</p>';
    }

    $html = '<ol class="widget-list widget-list--top-guilds">';
    foreach ($guilds as $guild) {
        $html .= '<li>';
        $html .= '<span class="widget-item__name">' . sanitize($guild['name']) . '</span>';
        $html .= '<span class="widget-item__meta">' . (int) $guild['member_count'] . ' members</span>';
        $html .= '</li>';
    }
    $html .= '</ol>';

    return $html;
}

function widget_online(PDO $pdo, int $limit = 5): string
{
    $stmt = $pdo->prepare('SELECT p.name, p.level, p.vocation
        FROM players_online po
        INNER JOIN players p ON p.id = po.player_id
        ORDER BY p.level DESC, p.name ASC
        LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $players = $stmt->fetchAll();

    $countStmt = $pdo->query('SELECT COUNT(*) FROM players_online');
    $totalOnline = (int) $countStmt->fetchColumn();

    if ($players === []) {
        return '<p class="widget-empty">No players are online.</p>';
    }

    $html = '<p class="widget-summary">' . $totalOnline . ' online</p>';
    $html .= '<ul class="widget-list widget-list--online">';
    foreach ($players as $player) {
        $html .= '<li>';
        $html .= '<span class="widget-item__name">' . sanitize($player['name']) . '</span>';
        $html .= '<span class="widget-item__meta">Lvl ' . (int) $player['level'] . ' ' . sanitize(vocation_name_widget((int) $player['vocation'])) . '</span>';
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

function widget_recent_deaths(PDO $pdo, int $limit = 5): string
{
    $stmt = $pdo->prepare('SELECT p.name, pd.level, pd.killed_by, pd.is_player, pd.time
        FROM player_deaths pd
        INNER JOIN players p ON p.id = pd.player_id
        ORDER BY pd.time DESC
        LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $deaths = $stmt->fetchAll();

    if ($deaths === []) {
        return '<p class="widget-empty">No recent deaths.</p>';
    }

    $html = '<ul class="widget-list widget-list--recent-deaths">';
    foreach ($deaths as $death) {
        $killer = sanitize($death['killed_by']);
        if ((int) $death['is_player'] === 1) {
            $killer .= ' (player)';
        }

        $timestamp = (int) $death['time'];
        $html .= '<li>';
        $html .= '<span class="widget-item__name">' . sanitize($death['name']) . '</span>';
        $html .= '<span class="widget-item__meta">Lvl ' . (int) $death['level'] . ' - ' . $killer . '</span>';
        if ($timestamp > 0) {
            $html .= '<time datetime="' . date('c', $timestamp) . '">' . sanitize(date('Y-m-d H:i', $timestamp)) . '</time>';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

function widget_server_status(PDO $pdo, int $limit = 5): string
{
    $onlineStmt = $pdo->query('SELECT COUNT(*) FROM players_online');
    $onlineCount = (int) $onlineStmt->fetchColumn();

    $recordStmt = $pdo->prepare('SELECT value FROM server_config WHERE config = :config LIMIT 1');
    $recordStmt->execute([':config' => 'players_record']);
    $recordValue = $recordStmt->fetchColumn();
    $record = $recordValue !== false ? (int) $recordValue : 0;

    $status = $onlineCount > 0 ? 'Online' : 'Offline';

    $html = '<dl class="widget-status">';
    $html .= '<div><dt>Status</dt><dd>' . sanitize($status) . '</dd></div>';
    $html .= '<div><dt>Players Online</dt><dd>' . $onlineCount . '</dd></div>';
    $html .= '<div><dt>Record</dt><dd>' . $record . '</dd></div>';
    $html .= '</dl>';

    return $html;
}

function widget_vote_links(PDO $pdo, int $limit = 5): string
{
    $links = [
        ['label' => 'Vote on OTServList', 'url' => 'https://otservlist.org/'],
        ['label' => 'Support us on Reddit', 'url' => 'https://www.reddit.com/r/otserv/'],
        ['label' => 'Join our Discord', 'url' => 'https://discord.gg/'],
    ];

    if ($links === []) {
        return '<p class="widget-empty">No vote links available.</p>';
    }

    $limit = max(0, $limit);
    $visibleLinks = $limit > 0 ? array_slice($links, 0, $limit) : $links;

    $html = '<ul class="widget-links">';
    foreach ($visibleLinks as $link) {
        $html .= '<li><a href="' . htmlspecialchars($link['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener" target="_blank">' . sanitize($link['label']) . '</a></li>';
    }
    $html .= '</ul>';

    return $html;
}

function render_widget_box(string $slug, int $limit = 5): string
{
    global $WIDGETS;

    if (!isset($WIDGETS[$slug])) {
        return '';
    }

    $widget = $WIDGETS[$slug];
    $renderer = $widget['renderer'] ?? null;
    if (!is_callable($renderer)) {
        return '';
    }

    $ttl = isset($widget['ttl']) ? (int) $widget['ttl'] : 0;
    $key = cache_key('widget_' . $slug, ['limit' => $limit]);

    if ($ttl > 0) {
        $cached = cache_get($key, $ttl);
        if ($cached !== null) {
            return $cached;
        }
    }

    $pdo = db();
    $innerHtml = call_user_func($renderer, $pdo, $limit);
    if (!is_string($innerHtml)) {
        $innerHtml = (string) $innerHtml;
    }
    $title = htmlspecialchars($widget['title'] ?? $slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $box = '<section class="widget"><h3>' . $title . '</h3><div class="widget-body">' . $innerHtml . '</div></section>';

    if ($ttl > 0) {
        cache_set($key, $box);
    }

    return $box;
}
