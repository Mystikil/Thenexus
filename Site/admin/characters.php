<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_admin('admin');

$adminPageTitle = 'Characters';
$adminNavActive = 'characters';

require __DIR__ . '/partials/header.php';

$pdo = db();

if (!$pdo instanceof PDO) {
    echo '<section class="admin-section"><h2>Characters</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';

    return;
}

$currentAdmin = current_user();
$adminId = $currentAdmin !== null ? (int) $currentAdmin['id'] : null;
$actorIsMaster = $currentAdmin !== null && is_master($currentAdmin);

function admin_vocation_name(int $vocationId): string
{
    static $map = [
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

    return $map[$vocationId] ?? 'Unknown';
}

function admin_rcon_configured(): bool
{
    if (!defined('BRIDGE_SECRET')) {
        return false;
    }

    $secret = trim((string) BRIDGE_SECRET);

    return $secret !== '' && $secret !== 'replace-with-bridge-secret';
}

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$successMessage = take_flash('success');
$errorMessage = take_flash('error');
$csrfToken = csrf_token();
$rconConfigured = admin_rcon_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $characterId = (int) ($_POST['character_id'] ?? 0);
    $searchQuery = trim((string) ($_POST['search_query'] ?? $searchQuery));

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request. Please try again.');
        redirect('characters.php?q=' . urlencode($searchQuery));
    }

    if ($characterId <= 0) {
        flash('error', 'A valid character must be selected.');
        redirect('characters.php?q=' . urlencode($searchQuery));
    }

    if (!$rconConfigured) {
        flash('error', 'RCON bridge is not configured.');
        redirect('characters.php?q=' . urlencode($searchQuery));
    }

    $characterSql = sprintf(
        'SELECT p.id, p.name, p.level, p.vocation, p.account_id, a.%1$s AS account_name, a.email AS account_email '
        . 'FROM players p '
        . 'LEFT JOIN %2$s a ON a.%3$s = p.account_id '
        . 'WHERE p.id = :id LIMIT 1',
        TFS_NAME_COL,
        TFS_ACCOUNTS_TABLE,
        TFS_ACCOUNT_ID_COL
    );

    $stmt = $pdo->prepare($characterSql);
    $stmt->execute(['id' => $characterId]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($character === false) {
        flash('error', 'Character not found.');
        redirect('characters.php?q=' . urlencode($searchQuery));
    }

    $characterName = (string) $character['name'];
    $jobType = null;
    $jobArgs = ['player' => $characterName];

    switch ($action) {
        case 'rename':
            $newName = trim((string) ($_POST['new_name'] ?? ''));
            $length = mb_strlen($newName, 'UTF-8');

            if ($newName === '' || $length < 3 || $length > 25) {
                flash('error', 'Please provide a new name between 3 and 25 characters.');
                redirect('characters.php?q=' . urlencode($searchQuery));
            }

            if (strcasecmp($newName, $characterName) === 0) {
                flash('error', 'The new name must be different from the current name.');
                redirect('characters.php?q=' . urlencode($searchQuery));
            }

            $jobType = 'rename';
            $jobArgs['new'] = $newName;
            break;

        case 'kick':
            $jobType = 'kick';
            break;

        case 'ban':
            $jobType = 'ban';
            break;

        case 'mute':
            $jobType = 'mute';
            break;

        case 'temple':
            $jobType = 'teleport_temple';
            break;

        default:
            flash('error', 'Unknown action requested.');
            redirect('characters.php?q=' . urlencode($searchQuery));
    }

    $jobStmt = $pdo->prepare('INSERT INTO rcon_jobs (type, args_json) VALUES (:type, :args_json)');
    $jobStmt->execute([
        'type' => $jobType,
        'args_json' => json_encode($jobArgs, JSON_UNESCAPED_UNICODE),
    ]);

    $jobId = (int) $pdo->lastInsertId();

    $before = [
        'character_id' => (int) $character['id'],
        'name' => $characterName,
        'account_id' => $character['account_id'],
    ];

    $after = [
        'character_id' => (int) $character['id'],
        'job_id' => $jobId,
        'job_type' => $jobType,
        'job_args' => $jobArgs,
        'a_is_master' => $actorIsMaster ? 1 : 0,
    ];

    audit_log($adminId, 'admin_character_' . $jobType, $before, $after);
    flash('success', 'Action queued successfully (Job #' . $jobId . ').');
    redirect('characters.php?q=' . urlencode($searchQuery));
}

$whereClause = '';
$params = [];

if ($searchQuery !== '') {
    $whereClause = 'WHERE p.name LIKE :name';
    $params['name'] = '%' . $searchQuery . '%';
}

$listSql = sprintf(
    'SELECT p.id, p.name, p.level, p.vocation, p.account_id, a.%1$s AS account_name, a.email AS account_email '
    . 'FROM players p '
    . 'LEFT JOIN %2$s a ON a.%3$s = p.account_id '
    . '%4$s '
    . 'ORDER BY p.name ASC '
    . 'LIMIT 50',
    TFS_NAME_COL,
    TFS_ACCOUNTS_TABLE,
    TFS_ACCOUNT_ID_COL,
    $whereClause !== '' ? ' ' . $whereClause : ''
);

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$characters = $listStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="admin-section">
    <h2>Character Tools</h2>
    <p>Search for characters by name and queue in-game actions via the RCON bridge.</p>

    <form class="admin-form admin-form--inline" method="get" action="characters.php">
        <div class="admin-form__group">
            <label for="search-query">Character name</label>
            <input type="text" id="search-query" name="q" value="<?php echo sanitize($searchQuery); ?>" placeholder="Search...">
        </div>
        <div class="admin-form__actions">
            <button type="submit" class="admin-button">Search</button>
        </div>
    </form>

    <?php if (!$rconConfigured): ?>
        <div class="admin-alert admin-alert--warning">RCON bridge is not configured. Actions are disabled.</div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="admin-alert admin-alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="admin-alert admin-alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($characters === []): ?>
        <p>No characters found.</p>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Level</th>
                        <th>Vocation</th>
                        <th>Account</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($characters as $character): ?>
                        <tr>
                            <td><?php echo sanitize((string) $character['name']); ?></td>
                            <td><?php echo (int) $character['level']; ?></td>
                            <td><?php echo sanitize(admin_vocation_name((int) $character['vocation'])); ?></td>
                            <td>
                                <?php if ($character['account_id'] !== null): ?>
                                    <span>#<?php echo (int) $character['account_id']; ?></span>
                                    <?php if ($character['account_name']): ?>
                                        <div class="admin-table__meta"><?php echo sanitize((string) $character['account_name']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($character['account_email']): ?>
                                        <div class="admin-table__meta"><?php echo sanitize((string) $character['account_email']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="admin-table__meta">Unlinked</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-table__actions">
                                <form method="post" class="admin-inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="search_query" value="<?php echo sanitize($searchQuery); ?>">
                                    <input type="hidden" name="character_id" value="<?php echo (int) $character['id']; ?>">
                                    <div class="admin-inline-group">
                                        <input type="text" name="new_name" placeholder="New name" <?php echo $rconConfigured ? '' : 'disabled'; ?>>
                                        <button type="submit" name="action" value="rename" class="admin-button" <?php echo $rconConfigured ? '' : 'disabled title="RCON disabled"'; ?>>Rename</button>
                                    </div>
                                </form>
                                <div class="admin-inline-group">
                                    <?php foreach (['kick' => 'Kick', 'ban' => 'Ban', 'mute' => 'Mute', 'temple' => 'Temple'] as $key => $label): ?>
                                        <form method="post" class="admin-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <input type="hidden" name="search_query" value="<?php echo sanitize($searchQuery); ?>">
                                            <input type="hidden" name="character_id" value="<?php echo (int) $character['id']; ?>">
                                            <button type="submit" name="action" value="<?php echo $key; ?>" class="admin-button admin-button--secondary" <?php echo $rconConfigured ? '' : 'disabled title="RCON disabled"'; ?>><?php echo $label; ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/partials/footer.php';
