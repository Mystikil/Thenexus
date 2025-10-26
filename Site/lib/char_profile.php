<?php

declare(strict_types=1);

if (!function_exists('nx_table_exists')) {
    function nx_table_exists(PDO $pdo, string $t): bool
    {
        $s = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $s->execute([$t]);

        return (bool) $s->fetchColumn();
    }
}

function nx_fetch_player(PDO $pdo, string $name): ?array
{
    $sql = "SELECT p.*, a.id AS account_id, a.name AS account_name, a.premium_ends_at, a.creation AS account_created,
                 CASE WHEN a.premium_ends_at > UNIX_TIMESTAMP() THEN 1 ELSE 0 END AS is_premium
          FROM players p
          JOIN accounts a ON a.id = p.account_id
          WHERE p.name = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$name]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function nx_fetch_skills(PDO $pdo, int $playerId): array
{
    $skills = ['fist' => 0, 'club' => 0, 'sword' => 0, 'axe' => 0, 'distance' => 0, 'shield' => 0, 'fish' => 0];
    if (nx_table_exists($pdo, 'player_skills')) {
        $st = $pdo->prepare('SELECT skillid, value FROM player_skills WHERE player_id = ?');
        $st->execute([$playerId]);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $map = [0 => 'fist', 1 => 'club', 2 => 'sword', 3 => 'axe', 4 => 'distance', 5 => 'shield', 6 => 'fish'];
            $k = $map[(int) $r['skillid']] ?? null;
            if ($k) {
                $skills[$k] = (int) $r['value'];
            }
        }
    }

    return $skills;
}

function nx_fetch_guild(PDO $pdo, int $playerId): ?array
{
    if (!nx_table_exists($pdo, 'guild_membership')) {
        return null;
    }
    $sql = 'SELECT g.id, g.name, gm.rank FROM guild_membership gm JOIN guilds g ON g.id = gm.guild_id WHERE gm.player_id = ? LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute([$playerId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    return $r ?: null;
}

function nx_fetch_house(PDO $pdo, int $playerId): ?array
{
    if (nx_table_exists($pdo, 'house_owners') && nx_table_exists($pdo, 'houses')) {
        $st = $pdo->prepare('SELECT h.id, h.name, h.town_id FROM house_owners ho JOIN houses h ON h.id=ho.house_id WHERE ho.owner_id=? LIMIT 1');
        $st->execute([$playerId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            return $r;
        }
    }
    if (nx_table_exists($pdo, 'houses') && nx_table_exists($pdo, 'players')) {
        $st = $pdo->prepare('SELECT id, name, town_id FROM houses WHERE owner = ? LIMIT 1');
        $st->execute([$playerId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            return $r;
        }
    }

    return null;
}

function nx_fetch_deaths(PDO $pdo, int $playerId): array
{
    if (nx_table_exists($pdo, 'player_deaths')) {
        $sql = 'SELECT d.time, d.level, k.name AS killer
            FROM player_deaths d
            LEFT JOIN players k ON k.id = d.killer_id
            WHERE d.player_id = ? ORDER BY d.time DESC LIMIT 10';
    } else {
        $sql = 'SELECT time, level, killer AS killer FROM deaths WHERE player_id = ? ORDER BY time DESC LIMIT 10';
    }
    $st = $pdo->prepare($sql);
    $st->execute([$playerId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function nx_fetch_kills(PDO $pdo, int $playerId): array
{
    if (!nx_table_exists($pdo, 'player_killers')) {
        return [];
    }
    $sql = 'SELECT d.time, v.name AS victim, d.level
          FROM player_killers pk
          JOIN killers k ON k.id=pk.killer_id
          JOIN death_list d ON d.death_id=k.death_id
          JOIN players v ON v.id=d.player_id
          WHERE pk.player_id = ?
          ORDER BY d.time DESC LIMIT 10';
    $st = $pdo->prepare($sql);
    $st->execute([$playerId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function nx_fetch_equipment(PDO $pdo, int $playerId): array
{
    if (!nx_table_exists($pdo, 'player_items')) {
        return [];
    }
    $st = $pdo->prepare('SELECT pid, itemtype AS item_id, COUNT(*) AS count FROM player_items WHERE player_id=? AND pid BETWEEN 1 AND 10 GROUP BY pid, itemtype ORDER BY pid');
    $st->execute([$playerId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $slots = [1 => 'Head', 2 => 'Amulet', 3 => 'Backpack', 4 => 'Armor', 5 => 'Right', 6 => 'Left', 7 => 'Legs', 8 => 'Feet', 9 => 'Ring', 10 => 'Ammo'];
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['slot' => $slots[(int) $r['pid']] ?? (string) $r['pid'], 'item_id' => (int) $r['item_id'], 'count' => (int) $r['count']];
    }

    return $out;
}
