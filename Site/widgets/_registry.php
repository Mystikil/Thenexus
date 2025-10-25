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

function widget_resolve_attributes(string $slug, int $limit = 5, ?array $overrides = null): array
{
    global $WIDGETS;

    $limit = max(1, $limit);
    $attributes = [];

    if (isset($WIDGETS[$slug])) {
        $widget = $WIDGETS[$slug];
        $ttl = isset($widget['ttl']) ? (int) $widget['ttl'] : 0;

        if (in_array($slug, ['online', 'server_status'], true)) {
            $attributes['data-auto-refresh'] = $slug;

            if ($ttl > 0) {
                $attributes['data-interval'] = (string) max(5000, $ttl * 1000);
            }
        }
    }

    $attributes['data-limit'] = (string) $limit;

    if ($overrides !== null) {
        foreach ($overrides as $name => $value) {
            if ($value === null) {
                unset($attributes[$name]);
                continue;
            }

            $attributes[$name] = (string) $value;
        }
    }

    ksort($attributes);

    return $attributes;
}

function widget_cache_key(string $slug, int $limit, array $attributes): string
{
    return cache_key('widget_' . $slug, [
        'limit' => max(1, $limit),
        'attributes' => $attributes,
    ]);
}

function widget_format_attributes(array $attributes): string
{
    if ($attributes === []) {
        return '';
    }

    $chunks = [];

    foreach ($attributes as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $attrName = htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $attrValue = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $chunks[] = $attrName . '="' . $attrValue . '"';
    }

    return $chunks === [] ? '' : ' ' . implode(' ', $chunks);
}

function widget_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vocation_short_code_widget(int $vocationId): string
{
    $codes = [
        0 => 'None',
        1 => 'Sor',
        2 => 'Dru',
        3 => 'Pal',
        4 => 'Kni',
        5 => 'MS',
        6 => 'ED',
        7 => 'RP',
        8 => 'EK',
    ];

    return $codes[$vocationId] ?? 'N/A';
}

function widget_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

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
}

function widget_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
        if ($stmt->execute()) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['name']) && $row['name'] === $column) {
                    $cache[$key] = true;

                    return true;
                }
            }
        }
        $cache[$key] = false;

        return false;
    }

    $sql = 'SELECT 1 FROM information_schema.columns WHERE table_name = :table AND column_name = :column AND table_schema = DATABASE() LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table' => $table, ':column' => $column]);
    $cache[$key] = (bool) $stmt->fetchColumn();

    return $cache[$key];
}

function widget_relative_time(int $timestamp): string
{
    $diff = max(0, time() - $timestamp);
    $units = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second',
    ];

    foreach ($units as $seconds => $label) {
        if ($diff >= $seconds) {
            $value = (int) floor($diff / $seconds);
            $suffix = $value === 1 ? '' : 's';

            return $value . ' ' . $label . $suffix . ' ago';
        }
    }

    return 'just now';
}

function widget_top_levels(PDO $pdo, int $limit = 10): string
{
    $stmt = $pdo->prepare('SELECT name, level, vocation FROM players ORDER BY level DESC, experience DESC, name ASC LIMIT :lim');
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($players === [] || $players === false) {
        return '<p class="widget-empty">No players found.</p>';
    }

    $html = '<ol class="widget-list widget-list--top-levels">';
    foreach ($players as $player) {
        $name = (string) ($player['name'] ?? '');
        $level = (int) ($player['level'] ?? 0);
        $vocation = (int) ($player['vocation'] ?? 0);
        $html .= '<li>';
        $html .= '<a class="widget-item__name" href="?p=character&amp;name=' . rawurlencode($name) . '">' . widget_escape($name) . '</a>';
        $html .= '<span class="widget-item__meta">Lvl ' . $level . ' ' . widget_escape(vocation_short_code_widget($vocation)) . '</span>';
        $html .= '</li>';
    }
    $html .= '</ol>';

    return $html;
}

function widget_top_guilds(PDO $pdo, int $limit = 8): string
{
    $membershipTable = widget_table_exists($pdo, 'guild_memberships') ? 'guild_memberships' : 'guild_membership';
    $scoreColumn = null;
    $scoreLabel = '';

    if (widget_table_has_column($pdo, 'guilds', 'points')) {
        $scoreColumn = 'points';
        $scoreLabel = 'Points';
    } elseif (widget_table_has_column($pdo, 'guilds', 'frags')) {
        $scoreColumn = 'frags';
        $scoreLabel = 'Frags';
    }

    if ($scoreColumn !== null) {
        $sql = "SELECT g.name, COALESCE(g.$scoreColumn, 0) AS score, COUNT(m.player_id) AS members
            FROM guilds g
            LEFT JOIN $membershipTable m ON m.guild_id = g.id
            GROUP BY g.id, g.name, g.$scoreColumn
            ORDER BY score DESC, members DESC, g.name ASC
            LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $guilds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT g.id, g.name,
                COALESCE((SELECT AVG(sub.level)
                          FROM (
                              SELECT p.level
                              FROM $membershipTable gm2
                              JOIN players p ON p.id = gm2.player_id
                              WHERE gm2.guild_id = g.id
                              ORDER BY p.level DESC
                              LIMIT 10
                          ) AS sub), 0) AS avg_level,
                (SELECT COUNT(*) FROM $membershipTable gm3 WHERE gm3.guild_id = g.id) AS members
            FROM guilds g
            ORDER BY avg_level DESC, members DESC, g.name ASC
            LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $guilds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($guilds === [] || $guilds === false) {
        return '<p class="widget-empty">No guilds found.</p>';
    }

    $html = '<ol class="widget-list widget-list--top-guilds">';
    foreach ($guilds as $guild) {
        $name = (string) ($guild['name'] ?? '');
        $members = (int) ($guild['members'] ?? 0);
        $url = '?p=guilds&amp;name=' . rawurlencode($name);
        $html .= '<li>';
        $html .= '<a class="widget-item__name" href="' . $url . '">' . widget_escape($name) . '</a>';
        if ($scoreColumn !== null) {
            $scoreValue = (int) ($guild['score'] ?? 0);
            $html .= '<span class="widget-item__meta">' . $members . ' members • ' . widget_escape($scoreLabel) . ': ' . $scoreValue . '</span>';
        } else {
            $avg = number_format((float) ($guild['avg_level'] ?? 0), 1);
            $html .= '<span class="widget-item__meta">Avg Lv ' . $avg . ' • ' . $members . ' members</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ol>';

    return $html;
}

function widget_online(PDO $pdo, int $limit = 10): string
{
    $hasOnlineColumn = widget_table_has_column($pdo, 'players', 'online');
    $hasPlayersOnline = widget_table_exists($pdo, 'players_online');

    if (!$hasOnlineColumn && !$hasPlayersOnline) {
        return '<p class="widget-empty">No players online.</p>';
    }

    if ($hasOnlineColumn) {
        $listSql = 'SELECT name, level, vocation FROM players WHERE online = 1 ORDER BY level DESC, experience DESC, name ASC LIMIT :lim';
        $countSql = 'SELECT COUNT(*) FROM players WHERE online = 1';
    } else {
        $listSql = 'SELECT p.name, p.level, p.vocation
            FROM players_online po
            INNER JOIN players p ON p.id = po.player_id
            ORDER BY p.level DESC, p.experience DESC, p.name ASC
            LIMIT :lim';
        $countSql = 'SELECT COUNT(*) FROM players_online';
    }

    $stmt = $pdo->prepare($listSql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $countStmt = $pdo->query($countSql);
    $totalOnline = $countStmt !== false ? (int) $countStmt->fetchColumn() : count($players);

    if ($players === []) {
        return '<p class="widget-empty">No players online. (' . $totalOnline . ')</p>';
    }

    $html = '<p class="widget-summary">' . $totalOnline . ' online</p>';
    $html .= '<ul class="widget-list widget-list--online">';
    foreach ($players as $player) {
        $name = (string) ($player['name'] ?? '');
        $level = (int) ($player['level'] ?? 0);
        $vocation = (int) ($player['vocation'] ?? 0);
        $html .= '<li>';
        $html .= '<a class="widget-item__name" href="?p=character&amp;name=' . rawurlencode($name) . '">' . widget_escape($name) . '</a>';
        $html .= '<span class="widget-item__meta">Lvl ' . $level . ' ' . widget_escape(vocation_short_code_widget($vocation)) . '</span>';
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

function widget_recent_deaths(PDO $pdo, int $limit = 8): string
{
    $deathsTable = widget_table_exists($pdo, 'deaths') ? 'deaths' : 'player_deaths';
    $killerColumn = widget_table_has_column($pdo, $deathsTable, 'killer') ? 'killer' : 'killed_by';

    $sql = "SELECT p.name, d.level, d.time, d.$killerColumn AS killer
        FROM $deathsTable d
        INNER JOIN players p ON p.id = d.player_id
        ORDER BY d.time DESC
        LIMIT :lim";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $deaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($deaths === [] || $deaths === false) {
        return '<p class="widget-empty">No recent deaths.</p>';
    }

    $html = '<ul class="widget-list widget-list--recent-deaths">';
    foreach ($deaths as $death) {
        $name = (string) ($death['name'] ?? '');
        $level = (int) ($death['level'] ?? 0);
        $killer = (string) ($death['killer'] ?? 'Unknown');
        $time = isset($death['time']) ? (int) $death['time'] : 0;
        $relative = $time > 0 ? widget_relative_time($time) : 'Unknown time';
        $html .= '<li>';
        $html .= '<span class="widget-item__text"><strong>' . widget_escape($name) . '</strong> (Lv ' . $level . ') – slain by ' . widget_escape($killer) . ' – ';
        if ($time > 0) {
            $html .= '<time datetime="' . widget_escape(date('c', $time)) . '">' . widget_escape($relative) . '</time>';
        } else {
            $html .= widget_escape($relative);
        }
        $html .= '</span>';
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

function widget_server_status(PDO $pdo): string
{
    $hasOnlineColumn = widget_table_has_column($pdo, 'players', 'online');
    $hasPlayersOnline = widget_table_exists($pdo, 'players_online');

    if ($hasOnlineColumn) {
        $onlineStmt = $pdo->query('SELECT COUNT(*) FROM players WHERE online = 1');
    } elseif ($hasPlayersOnline) {
        $onlineStmt = $pdo->query('SELECT COUNT(*) FROM players_online');
    } else {
        $onlineStmt = false;
    }

    $onlineCount = $onlineStmt !== false ? (int) $onlineStmt->fetchColumn() : 0;

    $record = 0;
    if (widget_table_exists($pdo, 'server_config')) {
        $recordStmt = $pdo->prepare('SELECT value FROM server_config WHERE config = :config LIMIT 1');
        $recordStmt->execute([':config' => 'players_record']);
        $recordValue = $recordStmt->fetchColumn();
        if ($recordValue !== false) {
            $record = (int) $recordValue;
        }
    }

    $since = time() - 86400;
    $logins = 0;
    if (widget_table_has_column($pdo, 'players', 'lastlogin')) {
        $loginsStmt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE lastlogin >= :since');
        $loginsStmt->bindValue(':since', $since, PDO::PARAM_INT);
        $loginsStmt->execute();
        $logins = (int) $loginsStmt->fetchColumn();
    }

    $status = $onlineCount > 0 ? 'Online' : 'Offline';

    $html = '<table class="widget-status">';
    $html .= '<tr><th>Status</th><td>' . widget_escape($status) . '</td></tr>';
    $html .= '<tr><th>Players Online</th><td>' . $onlineCount . '</td></tr>';
    $html .= '<tr><th>Peak Online</th><td>' . $record . '</td></tr>';
    $html .= '<tr><th>Logins (24h)</th><td>' . $logins . '</td></tr>';
    $html .= '</table>';

    return $html;
}

function widget_vote_links(PDO $pdo, int $limit = 0): string
{
    $links = [];

    if (widget_table_exists($pdo, 'settings')) {
        $keys = [
            'vote_link_1_title',
            'vote_link_1_url',
            'vote_link_2_title',
            'vote_link_2_url',
        ];
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare('SELECT `key`, value FROM settings WHERE `key` IN (' . $placeholders . ')');
        $stmt->execute($keys);
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['key'])) {
                $settings[$row['key']] = (string) $row['value'];
            }
        }

        for ($i = 1; $i <= 2; $i++) {
            $titleKey = 'vote_link_' . $i . '_title';
            $urlKey = 'vote_link_' . $i . '_url';
            $title = trim($settings[$titleKey] ?? '');
            $url = trim($settings[$urlKey] ?? '');

            if ($url !== '') {
                $links[] = [
                    'label' => $title !== '' ? $title : 'Vote Link ' . $i,
                    'url' => $url,
                ];
            }
        }
    }

    if ($links === []) {
        $links = [
            ['label' => 'Vote on OTServList', 'url' => 'https://otservlist.org/'],
        ];
    }

    if ($limit > 0) {
        $links = array_slice($links, 0, $limit);
    }

    if ($links === []) {
        return '<p class="widget-empty">No vote links available.</p>';
    }

    $html = '<ul class="widget-links">';
    foreach ($links as $link) {
        $label = widget_escape($link['label']);
        $url = htmlspecialchars($link['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= '<li><a href="' . $url . '" rel="noopener noreferrer" target="_blank">' . $label . '</a></li>';
    }
    $html .= '</ul>';

    return $html;
}

function render_widget_box(string $slug, int $limit = 5, ?array $attributeOverrides = null): string
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

    $limit = max(1, $limit);
    $attributes = widget_resolve_attributes($slug, $limit, $attributeOverrides);
    $ttl = isset($widget['ttl']) ? (int) $widget['ttl'] : 0;
    $key = widget_cache_key($slug, $limit, $attributes);

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
    $attrString = widget_format_attributes($attributes);
    $box = '<section class="widget"' . $attrString . '><h3>' . $title . '</h3><div class="widget-body">' . $innerHtml . '</div></section>';

    if ($ttl > 0) {
        cache_set($key, $box);
    }

    return $box;
}
