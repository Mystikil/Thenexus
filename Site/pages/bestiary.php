<?php
declare(strict_types=1);

require_once __DIR__ . '/../widgets/_cache.php';

$pdo = db();

$search = trim((string) ($_GET['search'] ?? ''));
$race = trim((string) ($_GET['race'] ?? ''));
$elementRelation = $_GET['element_relation'] ?? '';
$elementType = $_GET['element_type'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 24;

$validSorts = [
    'name_asc' => 'name ASC',
    'exp_desc' => 'experience DESC',
    'health_desc' => 'health DESC',
    'speed_desc' => 'speed DESC',
];

if (!array_key_exists($sort, $validSorts)) {
    $sort = 'name_asc';
}

$elementTypes = ['physical', 'energy', 'earth', 'fire', 'ice', 'death', 'holy', 'drown'];
if (!in_array($elementType, $elementTypes, true)) {
    $elementType = '';
}

$elementRelations = ['strong', 'weak', 'immune', 'vulnerable'];
if (!in_array($elementRelation, $elementRelations, true)) {
    $elementRelation = '';
}

$filtersForCache = [
    'search' => $search,
    'race' => $race,
    'relation' => $elementRelation,
    'element' => $elementType,
    'sort' => $sort,
    'page' => $page,
];

$cacheKey = cache_key('page:bestiary', $filtersForCache);
if ($cached = cache_get($cacheKey, 30)) {
    echo $cached;
    return;
}

ob_start();

$racesStmt = $pdo->query("SELECT DISTINCT race FROM monster_index WHERE race IS NOT NULL AND race <> '' ORDER BY race");
$races = $racesStmt->fetchAll(PDO::FETCH_COLUMN);

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = 'name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

if ($race !== '') {
    $whereClauses[] = 'race = :race';
    $params['race'] = $race;
}

$elementExpr = null;
if ($elementType !== '' && $elementRelation !== '') {
    $elementExpr = sprintf("CAST(JSON_UNQUOTE(JSON_EXTRACT(elemental, '$.\"%s\"')) AS SIGNED)", $elementType);

    switch ($elementRelation) {
        case 'immune':
            $whereClauses[] = $elementExpr . ' >= 100';
            break;
        case 'strong':
            $whereClauses[] = $elementExpr . ' > 0';
            break;
        case 'weak':
            $whereClauses[] = $elementExpr . ' BETWEEN 1 AND 49';
            break;
        case 'vulnerable':
            $whereClauses[] = $elementExpr . ' < 0';
            break;
    }
}

$whereSql = $whereClauses !== [] ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM monster_index' . $whereSql);
$countStmt->execute($params);
$totalResults = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$dataSql = 'SELECT id, name, race, experience, health, speed, elemental FROM monster_index'
    . $whereSql
    . ' ORDER BY ' . $validSorts[$sort]
    . ' LIMIT :limit OFFSET :offset';

$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $value) {
    $dataStmt->bindValue(':' . $key, $value);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$monsters = $dataStmt->fetchAll();

$baseQuery = [
    'p' => 'bestiary',
    'search' => $search,
    'race' => $race,
    'element_relation' => $elementRelation,
    'element_type' => $elementType,
    'sort' => $sort,
];
?>
<section class="page bestiary-page">
    <h2>Bestiary</h2>
    <form method="get" class="bestiary-filters">
        <input type="hidden" name="p" value="bestiary">
        <div class="bestiary-filters__group">
            <label for="bestiary-search">Search</label>
            <input type="text" id="bestiary-search" name="search" value="<?php echo sanitize($search); ?>" placeholder="Monster name">
        </div>
        <div class="bestiary-filters__group">
            <label for="bestiary-race">Race</label>
            <select id="bestiary-race" name="race">
                <option value="">All races</option>
                <?php foreach ($races as $raceOption): ?>
                    <option value="<?php echo sanitize($raceOption); ?>"<?php echo $raceOption === $race ? ' selected' : ''; ?>><?php echo sanitize(ucwords($raceOption)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bestiary-filters__group">
            <label for="bestiary-element-relation">Element Focus</label>
            <select id="bestiary-element-relation" name="element_relation">
                <option value="">Any relation</option>
                <option value="strong"<?php echo $elementRelation === 'strong' ? ' selected' : ''; ?>>Strong Against</option>
                <option value="weak"<?php echo $elementRelation === 'weak' ? ' selected' : ''; ?>>Resistant</option>
                <option value="immune"<?php echo $elementRelation === 'immune' ? ' selected' : ''; ?>>Immune</option>
                <option value="vulnerable"<?php echo $elementRelation === 'vulnerable' ? ' selected' : ''; ?>>Vulnerable</option>
            </select>
        </div>
        <div class="bestiary-filters__group">
            <label for="bestiary-element-type">Element Type</label>
            <select id="bestiary-element-type" name="element_type">
                <option value="">Any element</option>
                <?php foreach ($elementTypes as $type): ?>
                    <option value="<?php echo sanitize($type); ?>"<?php echo $type === $elementType ? ' selected' : ''; ?>><?php echo sanitize(ucfirst($type)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bestiary-filters__group">
            <label for="bestiary-sort">Sort By</label>
            <select id="bestiary-sort" name="sort">
                <option value="name_asc"<?php echo $sort === 'name_asc' ? ' selected' : ''; ?>>Name A-Z</option>
                <option value="exp_desc"<?php echo $sort === 'exp_desc' ? ' selected' : ''; ?>>Experience (desc)</option>
                <option value="health_desc"<?php echo $sort === 'health_desc' ? ' selected' : ''; ?>>Health (desc)</option>
                <option value="speed_desc"<?php echo $sort === 'speed_desc' ? ' selected' : ''; ?>>Speed (desc)</option>
            </select>
        </div>
        <div class="bestiary-filters__actions">
            <button type="submit">Apply</button>
        </div>
    </form>

    <?php if ($monsters === []): ?>
        <p>No monsters matched your filters. Try adjusting the search or filters above.</p>
    <?php else: ?>
        <div class="bestiary-grid">
            <?php foreach ($monsters as $monster): ?>
                <?php
                    $elemental = [];
                    if (isset($monster['elemental']) && $monster['elemental'] !== null) {
                        $decoded = json_decode((string) $monster['elemental'], true);
                        if (is_array($decoded)) {
                            $elemental = $decoded;
                        }
                    }
                ?>
                <article class="bestiary-card">
                    <header class="bestiary-card__header">
                        <h3 class="bestiary-card__title"><?php echo sanitize($monster['name']); ?></h3>
                        <span class="bestiary-card__race"><?php echo sanitize($monster['race'] ?? 'Unknown'); ?></span>
                    </header>
                    <div class="bestiary-card__stats">
                        <span>EXP: <?php echo (int) $monster['experience']; ?></span>
                        <span>HP: <?php echo (int) $monster['health']; ?></span>
                        <span>Speed: <?php echo (int) $monster['speed']; ?></span>
                    </div>
                    <?php if ($elemental !== []): ?>
                        <div class="bestiary-card__elements">
                            <?php foreach ($elemental as $type => $value): ?>
                                <?php
                                    $valueInt = (int) $value;
                                    $class = '';
                                    if ($valueInt >= 100) {
                                        $class = ' bestiary-card__element--immune';
                                    } elseif ($valueInt > 0) {
                                        $class = ' bestiary-card__element--resist';
                                    } elseif ($valueInt < 0) {
                                        $class = ' bestiary-card__element--weak';
                                    }
                                ?>
                                <?php if ($valueInt !== 0): ?>
                                    <span class="bestiary-card__element<?php echo $class; ?>"><?php echo sanitize(ucfirst($type)); ?> <?php echo $valueInt; ?>%</span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <footer class="bestiary-card__footer">
                        <a class="bestiary-card__link" href="?p=monster&amp;id=<?php echo (int) $monster['id']; ?>">View</a>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="bestiary-pagination" aria-label="Bestiary pages">
                <?php if ($page > 1): ?>
                    <?php $prevQuery = http_build_query(array_merge($baseQuery, ['page' => $page - 1])); ?>
                    <a href="?<?php echo sanitize($prevQuery); ?>">&laquo; Prev</a>
                <?php endif; ?>
                <span class="is-active">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <?php $nextQuery = http_build_query(array_merge($baseQuery, ['page' => $page + 1])); ?>
                    <a href="?<?php echo sanitize($nextQuery); ?>">Next &raquo;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
cache_set($cacheKey, $content);

echo $content;
