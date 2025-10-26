<?php
declare(strict_types=1);

$pdo = db();
$monsterId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($monsterId <= 0) {
    echo '<section class="page"><p>Monster not found.</p></section>';
    return;
}

$stmt = $pdo->prepare('SELECT * FROM monster_index WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $monsterId]);
$monster = $stmt->fetch();

if ($monster === false) {
    echo '<section class="page"><p>Monster not found.</p></section>';
    return;
}

$elemental = [];
if (isset($monster['elemental']) && $monster['elemental'] !== null) {
    $decoded = json_decode((string) $monster['elemental'], true);
    if (is_array($decoded)) {
        $elemental = $decoded;
    }
}

$immunities = [];
if (isset($monster['immunities']) && $monster['immunities'] !== null) {
    $decoded = json_decode((string) $monster['immunities'], true);
    if (is_array($decoded)) {
        $immunities = $decoded;
    }
}

$flags = [];
if (isset($monster['flags']) && $monster['flags'] !== null) {
    $decoded = json_decode((string) $monster['flags'], true);
    if (is_array($decoded)) {
        $flags = $decoded;
    }
}

$outfit = [];
if (isset($monster['outfit']) && $monster['outfit'] !== null) {
    $decoded = json_decode((string) $monster['outfit'], true);
    if (is_array($decoded)) {
        $outfit = $decoded;
    }
}

$lootStmt = $pdo->prepare('SELECT ml.*, i.name AS index_name FROM monster_loot ml
    LEFT JOIN item_index i ON i.id = ml.item_id
    WHERE ml.monster_id = :id
    ORDER BY ml.chance DESC, COALESCE(i.name, ml.item_name) ASC');
$lootStmt->execute(['id' => $monsterId]);
$loot = $lootStmt->fetchAll();

$related = [];
if (!empty($monster['race'])) {
    $relatedStmt = $pdo->prepare('SELECT id, name FROM monster_index WHERE race = :race AND id != :id ORDER BY experience DESC, name ASC LIMIT 6');
    $relatedStmt->execute([
        'race' => $monster['race'],
        'id' => $monsterId,
    ]);
    $related = $relatedStmt->fetchAll();
}
?>
<section class="page monster-detail">
    <header>
        <h2><?php echo sanitize($monster['name']); ?></h2>
        <?php if (!empty($monster['race'])): ?>
            <p class="monster-detail__race">Race: <?php echo sanitize($monster['race']); ?></p>
        <?php endif; ?>
    </header>

    <div class="monster-detail__layout">
        <div class="monster-detail__panel">
            <h3>Stats</h3>
            <div class="monster-detail__stats">
                <span><strong>Experience:</strong> <?php echo (int) $monster['experience']; ?></span>
                <span><strong>Health:</strong> <?php echo (int) $monster['health']; ?></span>
                <span><strong>Speed:</strong> <?php echo (int) $monster['speed']; ?></span>
                <span><strong>Summonable:</strong> <?php echo ((int) $monster['summonable']) === 1 ? 'Yes' : 'No'; ?></span>
                <span><strong>Convinceable:</strong> <?php echo ((int) $monster['convinceable']) === 1 ? 'Yes' : 'No'; ?></span>
                <span><strong>Illusionable:</strong> <?php echo ((int) $monster['illusionable']) === 1 ? 'Yes' : 'No'; ?></span>
            </div>
            <?php if ($outfit !== []): ?>
                <p><strong>Outfit:</strong>
                    <?php
                        $parts = [];
                        foreach ($outfit as $key => $value) {
                            $parts[] = sanitize($key . ': ' . $value);
                        }
                        echo implode(' &middot; ', $parts);
                    ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="monster-detail__panel monster-detail__elements">
            <h3>Elemental Profile</h3>
            <?php if ($elemental === []): ?>
                <p>No elemental data available.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Element</th>
                            <th>Percent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($elemental as $element => $value): ?>
                            <tr>
                                <td><?php echo sanitize(ucfirst($element)); ?></td>
                                <td><?php echo (int) $value; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="monster-detail__panel">
            <h3>Traits</h3>
            <?php if ($immunities !== []): ?>
                <p><strong>Immunities</strong></p>
                <ul class="monster-detail__list">
                    <?php foreach ($immunities as $key => $value): ?>
                        <li><?php echo sanitize(ucwords($key) . ': ' . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($flags !== []): ?>
                <p><strong>Flags</strong></p>
                <ul class="monster-detail__list">
                    <?php foreach ($flags as $key => $value): ?>
                        <li><?php echo sanitize(ucwords($key) . ': ' . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <section class="monster-detail__panel">
        <h3>Loot</h3>
        <?php if ($loot === []): ?>
            <p>No loot data recorded.</p>
        <?php else: ?>
            <table class="monster-detail__loot-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Chance</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loot as $entry): ?>
                        <?php
                            $chance = $entry['chance'] !== null ? (int) $entry['chance'] : null;
                            $chanceDisplay = $chance === null ? '—' : sprintf('%.1f%%', $chance / 1000);
                            $countMin = (int) ($entry['count_min'] ?? 1);
                            $countMax = (int) ($entry['count_max'] ?? 1);
                            $quantity = $countMin === $countMax ? $countMin : $countMin . ' - ' . $countMax;
                            $itemName = $entry['index_name'] ?? $entry['item_name'] ?? 'Unknown Item';
                        ?>
                        <tr>
                            <td><?php echo sanitize($itemName); ?><?php if (!empty($entry['item_id'])): ?> (ID <?php echo (int) $entry['item_id']; ?>)<?php endif; ?></td>
                            <td><?php echo sanitize($chanceDisplay); ?></td>
                            <td><?php echo sanitize($quantity); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if ($related !== []): ?>
        <section class="monster-detail__panel">
            <h3>Related Monsters</h3>
            <div class="monster-detail__related">
                <?php foreach ($related as $rel): ?>
                    <a href="?p=monster&amp;id=<?php echo (int) $rel['id']; ?>"><?php echo sanitize($rel['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
