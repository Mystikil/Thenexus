<?php
declare(strict_types=1);

require_once __DIR__ . '/../widgets/_cache.php';

$pdo = db();

$search = trim((string) ($_GET['search'] ?? ''));
$vocation = trim((string) ($_GET['vocation'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));
$sort = $_GET['sort'] ?? 'name_asc';
$levelMin = isset($_GET['level_min']) ? max(0, (int) $_GET['level_min']) : null;
$levelMax = isset($_GET['level_max']) ? max(0, (int) $_GET['level_max']) : null;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 24;

$validSorts = [
    'name_asc' => 'name ASC',
    'name_desc' => 'name DESC',
    'level_desc' => 'level DESC, name ASC',
    'mana_asc' => 'mana ASC, name ASC',
    'mana_desc' => 'mana DESC, name ASC',
    'cooldown_asc' => 'cooldown ASC, name ASC',
];

if (!array_key_exists($sort, $validSorts)) {
    $sort = 'name_asc';
}

$filtersForCache = [
    'search' => $search,
    'vocation' => $vocation,
    'type' => $type,
    'sort' => $sort,
    'level_min' => $levelMin,
    'level_max' => $levelMax,
    'page' => $page,
];

$cacheKey = cache_key('page:spells', $filtersForCache);
if ($cached = cache_get($cacheKey, 30)) {
    echo $cached;
    return;
}

ob_start();

$vocationRows = $pdo->query("SELECT vocations FROM spells_index WHERE vocations IS NOT NULL AND vocations <> ''")->fetchAll(PDO::FETCH_COLUMN);
$vocationOptions = [];
foreach ($vocationRows as $row) {
    $parts = array_filter(array_map('trim', explode(',', (string) $row)));
    foreach ($parts as $part) {
        $vocationOptions[$part] = true;
    }
}
ksort($vocationOptions);

$typeStmt = $pdo->query("SELECT DISTINCT type FROM spells_index WHERE type IS NOT NULL AND type <> '' ORDER BY type");
$typeOptions = $typeStmt->fetchAll(PDO::FETCH_COLUMN);

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(name LIKE :search OR words LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($vocation !== '') {
    $whereClauses[] = 'FIND_IN_SET(:vocation, vocations)';
    $params['vocation'] = $vocation;
}

if ($type !== '') {
    $whereClauses[] = 'type = :type';
    $params['type'] = $type;
}

if ($levelMin !== null && $levelMin > 0) {
    $whereClauses[] = 'level IS NOT NULL AND level >= :level_min';
    $params['level_min'] = $levelMin;
}

if ($levelMax !== null && $levelMax > 0) {
    $whereClauses[] = 'level IS NOT NULL AND level <= :level_max';
    $params['level_max'] = $levelMax;
}

$whereSql = $whereClauses !== [] ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM spells_index' . $whereSql);
$countStmt->execute($params);
$totalResults = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$dataSql = 'SELECT id, file_path, name, words, level, mana, cooldown, vocations, type, attributes'
    . ' FROM spells_index'
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
$spells = $dataStmt->fetchAll();

$baseQuery = [
    'p' => 'spells',
    'search' => $search,
    'vocation' => $vocation,
    'type' => $type,
    'sort' => $sort,
    'level_min' => $levelMin,
    'level_max' => $levelMax,
];
?>
<section class="page spells-page">
    <h2>Spell Library</h2>
    <form method="get" class="spells-filters">
        <input type="hidden" name="p" value="spells">
        <div class="spells-filters__group">
            <label for="spells-search">Search</label>
            <input type="text" id="spells-search" name="search" value="<?php echo sanitize($search); ?>" placeholder="Name or words">
        </div>
        <div class="spells-filters__group">
            <label for="spells-vocation">Vocation</label>
            <select id="spells-vocation" name="vocation">
                <option value="">All vocations</option>
                <?php foreach (array_keys($vocationOptions) as $vocationOption): ?>
                    <option value="<?php echo sanitize($vocationOption); ?>"<?php echo $vocationOption === $vocation ? ' selected' : ''; ?>><?php echo sanitize($vocationOption); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="spells-filters__group">
            <label for="spells-type">Type</label>
            <select id="spells-type" name="type">
                <option value="">All types</option>
                <?php foreach ($typeOptions as $typeOption): ?>
                    <option value="<?php echo sanitize($typeOption); ?>"<?php echo $typeOption === $type ? ' selected' : ''; ?>><?php echo sanitize(ucfirst($typeOption)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="spells-filters__group">
            <label for="spells-level-min">Level Min</label>
            <input type="number" id="spells-level-min" name="level_min" min="0" value="<?php echo $levelMin !== null ? $levelMin : ''; ?>">
        </div>
        <div class="spells-filters__group">
            <label for="spells-level-max">Level Max</label>
            <input type="number" id="spells-level-max" name="level_max" min="0" value="<?php echo $levelMax !== null ? $levelMax : ''; ?>">
        </div>
        <div class="spells-filters__group">
            <label for="spells-sort">Sort By</label>
            <select id="spells-sort" name="sort">
                <option value="name_asc"<?php echo $sort === 'name_asc' ? ' selected' : ''; ?>>Name A-Z</option>
                <option value="name_desc"<?php echo $sort === 'name_desc' ? ' selected' : ''; ?>>Name Z-A</option>
                <option value="level_desc"<?php echo $sort === 'level_desc' ? ' selected' : ''; ?>>Level (desc)</option>
                <option value="mana_asc"<?php echo $sort === 'mana_asc' ? ' selected' : ''; ?>>Mana (asc)</option>
                <option value="mana_desc"<?php echo $sort === 'mana_desc' ? ' selected' : ''; ?>>Mana (desc)</option>
                <option value="cooldown_asc"<?php echo $sort === 'cooldown_asc' ? ' selected' : ''; ?>>Cooldown (asc)</option>
            </select>
        </div>
        <div class="spells-filters__actions">
            <button type="submit">Apply</button>
        </div>
    </form>

    <?php if ($spells === []): ?>
        <p>No spells matched your filters.</p>
    <?php else: ?>
        <div class="spells-grid">
            <?php foreach ($spells as $spell): ?>
                <?php
                    $attributes = [];
                    if (isset($spell['attributes']) && $spell['attributes'] !== null) {
                        $decoded = json_decode((string) $spell['attributes'], true);
                        if (is_array($decoded)) {
                            $attributes = $decoded;
                        }
                    }

                    $vocationsList = [];
                    if (!empty($spell['vocations'])) {
                        $vocationsList = array_filter(array_map('trim', explode(',', (string) $spell['vocations'])));
                    }
                ?>
                <article class="spell-card">
                    <header class="spell-card__header">
                        <h3 class="spell-card__title"><?php echo sanitize($spell['name']); ?></h3>
                        <?php if (!empty($spell['words'])): ?>
                            <span class="spell-card__words">"<?php echo sanitize($spell['words']); ?>"</span>
                        <?php endif; ?>
                    </header>
                    <div class="spell-card__stats">
                        <?php if ($spell['level'] !== null): ?>
                            <span><strong>Level:</strong> <?php echo (int) $spell['level']; ?></span>
                        <?php endif; ?>
                        <?php if ($spell['mana'] !== null): ?>
                            <span><strong>Mana:</strong> <?php echo (int) $spell['mana']; ?></span>
                        <?php endif; ?>
                        <?php if ($spell['cooldown'] !== null): ?>
                            <span><strong>Cooldown:</strong> <?php echo (int) $spell['cooldown']; ?> ms</span>
                        <?php endif; ?>
                        <?php if (!empty($spell['type'])): ?>
                            <span><strong>Type:</strong> <?php echo sanitize(ucfirst($spell['type'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($vocationsList !== []): ?>
                        <div class="spell-card__vocations">
                            <?php foreach ($vocationsList as $vocationName): ?>
                                <span class="spell-card__vocation"><?php echo sanitize($vocationName); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($attributes !== []): ?>
                        <div class="spell-card__meta">
                            <?php foreach ($attributes as $key => $value): ?>
                                <?php if ($key === 'children') { continue; } ?>
                                <div>
                                    <span class="spell-card__meta-key"><?php echo sanitize(ucwords(str_replace('_', ' ', $key))); ?>:</span>
                                    <span class="spell-card__meta-value"><?php echo sanitize(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="spells-pagination" aria-label="Spell pages">
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
