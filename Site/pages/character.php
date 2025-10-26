<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

$pdo = db();

if (!$pdo instanceof PDO) {
    echo '<section class="page page--character"><h2>Character Search</h2><p class="text-muted mb-0">Character data is unavailable right now. Please try again later.</p></section>';
    return;
}

$nameQuery = trim((string) ($_GET['name'] ?? ''));
$normalizedQuery = preg_replace('/\s+/', ' ', $nameQuery);
$normalizedQuery = $normalizedQuery !== '' ? ucwords(strtolower($normalizedQuery)) : '';

$character = null;
$recentDeaths = [];
$guildName = null;
$message = '';

if ($normalizedQuery !== '') {
    $stmt = $pdo->prepare(
        'SELECT p.*, a.name AS account_name
         FROM players p
         JOIN accounts a ON a.id = p.account_id
         WHERE p.name = ?
         LIMIT 1'
    );
    $stmt->execute([$normalizedQuery]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($character === null) {
        $message = 'Character not found.';
    } else {
        $character['name'] = (string) $character['name'];
        $playerId = (int) ($character['id'] ?? 0);

        if (isset($GLOBALS['nx_meta_title'])) {
            $GLOBALS['nx_meta_title'] = $character['name'] . ' – Character';
        }

        if (nx_table_exists($pdo, 'guild_membership') && nx_table_exists($pdo, 'guilds')) {
            $guildStmt = $pdo->prepare(
                'SELECT g.name
                 FROM guild_membership gm
                 JOIN guilds g ON g.id = gm.guild_id
                 WHERE gm.player_id = ?
                 LIMIT 1'
            );
            $guildStmt->execute([$playerId]);
            $guildName = $guildStmt->fetchColumn();
        }

        $deathSql = null;
        if (nx_table_exists($pdo, 'deaths')) {
            $deathSql = 'SELECT d.time, d.level, d.is_player, d.killer_name, d.killer_id, k.name AS killer_player, p.name AS victim_name
                         FROM deaths d
                         JOIN players p ON p.id = d.player_id
                         LEFT JOIN players k ON k.id = d.killer_id
                         WHERE d.player_id = ?
                         ORDER BY d.time DESC
                         LIMIT 10';
        } elseif (nx_table_exists($pdo, 'player_deaths')) {
            $deathSql = 'SELECT d.date AS time, d.level, d.killed_by AS killer_name, d.is_player, NULL AS killer_player, p.name AS victim_name
                         FROM player_deaths d
                         JOIN players p ON p.id = d.player_id
                         WHERE d.player_id = ?
                         ORDER BY d.date DESC
                         LIMIT 10';
        }

        if ($deathSql !== null) {
            $deathStmt = $pdo->prepare($deathSql);
            $deathStmt->execute([$playerId]);
            $recentDeaths = $deathStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
} else {
    $message = 'Search for a character by name to view their profile.';
}

$townNames = [];
if (nx_table_exists($pdo, 'towns')) {
    $townStmt = $pdo->query('SELECT id, name FROM towns');
    foreach ($townStmt->fetchAll(PDO::FETCH_ASSOC) as $town) {
        $townId = (int) ($town['id'] ?? 0);
        if ($townId > 0) {
            $townName = trim((string) ($town['name'] ?? ''));
            $townNames[$townId] = $townName !== '' ? $townName : 'Town #' . $townId;
        }
    }
}

function nx_vocation_label(int $id): string
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

    return $vocations[$id] ?? 'Unknown';
}

function nx_format_timestamp(?int $timestamp): string
{
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return 'Never';
    }

    return date('Y-m-d H:i', $timestamp);
}

?>
<section class="page page--character">
    <h2 class="mb-3">Character Search</h2>

    <form method="get" class="row g-2 align-items-end mb-4" action="">
        <input type="hidden" name="p" value="character">
        <div class="col-sm-8">
            <label class="form-label" for="character-search-name">Name</label>
            <input
                type="text"
                class="form-control"
                id="character-search-name"
                name="name"
                value="<?php echo sanitize($nameQuery); ?>"
                placeholder="Ex: Knight John"
                required
            >
        </div>
        <div class="col-sm-4">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
    </form>

    <?php if ($message !== '' && $character === null): ?>
        <div class="alert alert--info"><?php echo sanitize($message); ?></div>
    <?php endif; ?>

    <?php if ($character !== null): ?>
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3" style="width:72px;height:72px;border-radius:16px;background:#1f2937;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                <?php echo sanitize(substr($character['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h3 class="h5 mb-1"><?php echo sanitize($character['name']); ?></h3>
                                <span class="badge bg-primary"><?php echo sanitize(nx_vocation_label((int) ($character['vocation'] ?? 0))); ?></span>
                            </div>
                        </div>
                        <dl class="row mb-0 small text-muted">
                            <dt class="col-5">Level</dt>
                            <dd class="col-7 text-reset"><?php echo (int) ($character['level'] ?? 1); ?></dd>
                            <dt class="col-5">Sex</dt>
                            <dd class="col-7 text-reset"><?php echo (int) ($character['sex'] ?? 0) === 1 ? 'Male' : 'Female'; ?></dd>
                            <dt class="col-5">Town</dt>
                            <dd class="col-7 text-reset">
                                <?php
                                    $townId = (int) ($character['town_id'] ?? 0);
                                    echo sanitize($townNames[$townId] ?? ($townId > 0 ? 'Town #' . $townId : 'Unknown'));
                                ?>
                            </dd>
                            <dt class="col-5">Last Login</dt>
                            <dd class="col-7 text-reset"><?php echo sanitize(nx_format_timestamp($character['lastlogin'] ?? null)); ?></dd>
                            <dt class="col-5">Account</dt>
                            <dd class="col-7 text-reset"><?php echo sanitize((string) ($character['account_name'] ?? '')); ?></dd>
                            <?php if ($guildName): ?>
                                <dt class="col-5">Guild</dt>
                                <dd class="col-7 text-reset"><?php echo sanitize((string) $guildName); ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card h-100 mb-3">
                    <div class="card-body">
                        <h3 class="h5">Recent Deaths</h3>
                        <?php if ($recentDeaths === []): ?>
                            <p class="text-muted mb-0">No recent deaths recorded.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Date</th>
                                            <th scope="col">Level</th>
                                            <th scope="col">Killer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentDeaths as $death): ?>
                                            <tr>
                                                <td><?php echo sanitize(nx_format_timestamp($death['time'] ?? null)); ?></td>
                                                <td><?php echo (int) ($death['level'] ?? 0); ?></td>
                                                <td>
                                                    <?php
                                                        $killerName = (string) ($death['killer_name'] ?? $death['killer_player'] ?? 'Unknown');
                                                        $killerId = isset($death['killer_id']) ? (int) $death['killer_id'] : 0;
                                                        if ($killerId > 0) {
                                                            echo '<a href="?p=character&amp;name=' . urlencode($killerName) . '">' . sanitize($killerName) . '</a>';
                                                        } else {
                                                            echo sanitize($killerName);
                                                        }
                                                        if ((int) ($death['is_player'] ?? 0) === 1 && $killerId === 0) {
                                                            echo ' (player)';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="h5">Attributes</h3>
                        <div class="row small text-muted">
                            <?php if (isset($character['health'], $character['healthmax'])): ?>
                                <div class="col-6 mb-2">
                                    <span class="text-reset">Health:</span>
                                    <?php echo (int) $character['health']; ?> / <?php echo (int) $character['healthmax']; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($character['mana'], $character['manamax'])): ?>
                                <div class="col-6 mb-2">
                                    <span class="text-reset">Mana:</span>
                                    <?php echo (int) $character['mana']; ?> / <?php echo (int) $character['manamax']; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($character['maglevel'])): ?>
                                <div class="col-6 mb-2">
                                    <span class="text-reset">Magic Level:</span>
                                    <?php echo (int) $character['maglevel']; ?>
                                </div>
                            <?php endif; ?>
                            <?php
                                $skillMap = [
                                    'skill_fist' => 'Fist Fighting',
                                    'skill_club' => 'Club Fighting',
                                    'skill_sword' => 'Sword Fighting',
                                    'skill_axe' => 'Axe Fighting',
                                    'skill_dist' => 'Distance Fighting',
                                    'skill_shielding' => 'Shielding',
                                    'skill_fishing' => 'Fishing',
                                ];
                                foreach ($skillMap as $field => $label):
                                    if (!isset($character[$field])) {
                                        continue;
                                    }
                            ?>
                                <div class="col-6 mb-2">
                                    <span class="text-reset"><?php echo sanitize($label); ?>:</span>
                                    <?php echo (int) $character[$field]; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
