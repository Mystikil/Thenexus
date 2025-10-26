<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/players.php';

@session_start();

require_login();

$pdo = db();

if (!$pdo instanceof PDO) {
    echo '<section class="page page--characters"><h2>My Characters</h2><p class="text-muted mb-0">The database connection is unavailable. Please try again later.</p></section>';
    return;
}

$user = current_user();
$accountId = isset($user['account_id']) ? (int) $user['account_id'] : 0;

$successMessage = take_flash('success');
$errorMessage = take_flash('error');
$createErrors = [];
$deleteErrors = [];
$nameValue = '';
$sexValue = '1';
$vocationValue = '0';
$townValue = '1';

$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        if ($action === 'delete') {
            $deleteErrors[] = 'Invalid request. Please try again.';
        } else {
            $createErrors[] = 'Invalid request. Please try again.';
        }
    } elseif ($action === 'create') {
        if ($accountId <= 0) {
            $createErrors[] = 'You must link your game account before creating characters.';
        } else {
            $nameInput = trim((string) ($_POST['name'] ?? ''));
            $nameInput = preg_replace('/\s+/', ' ', $nameInput);
            $nameValue = $nameInput;
            $sexValue = isset($_POST['sex']) ? (string) $_POST['sex'] : $sexValue;
            $vocationValue = isset($_POST['vocation']) ? (string) $_POST['vocation'] : $vocationValue;
            $townValue = isset($_POST['town_id']) ? (string) $_POST['town_id'] : $townValue;

            if ($nameInput === '') {
                $createErrors[] = 'Character name is required.';
            } elseif (!preg_match('/^[A-Za-z ]{3,20}$/', $nameInput)) {
                $createErrors[] = 'Character names must be 3-20 letters (A-Z) with optional spaces.';
            } elseif (strpos($nameInput, '  ') !== false) {
                $createErrors[] = 'Character names cannot contain consecutive spaces.';
            }

            $normalizedName = ucwords(strtolower($nameInput));

            if ($createErrors === [] && nx_name_is_taken($pdo, $normalizedName)) {
                $createErrors[] = 'That name is already taken. Please choose another.';
            }

            $sex = (int) ($sexValue);
            $vocation = (int) ($vocationValue);
            $townId = max(1, (int) ($townValue));

            if ($createErrors === []) {
                try {
                    nx_insert_player($pdo, [
                        'account_id' => $accountId,
                        'name' => $normalizedName,
                        'sex' => $sex,
                        'vocation' => $vocation,
                        'town_id' => $townId,
                        'looktype' => $sex === 1 ? 134 : 136,
                    ]);

                    flash('success', 'Character created successfully.');
                    nx_redirect('?p=characters');
                } catch (Throwable $exception) {
                    $createErrors[] = 'Unable to create the character. Please try again.';
                }
            }
        }
    } elseif ($action === 'delete') {
        if ($accountId <= 0) {
            $deleteErrors[] = 'You must link your game account before deleting characters.';
        } else {
            $characterId = isset($_POST['character_id']) ? (int) $_POST['character_id'] : 0;

            if ($characterId <= 0) {
                $deleteErrors[] = 'Invalid character selected.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM players WHERE id = ? AND account_id = ? LIMIT 1');
                $stmt->execute([$characterId, $accountId]);

                if ($stmt->rowCount() > 0) {
                    flash('success', 'Character deleted.');
                    nx_redirect('?p=characters');
                } else {
                    $deleteErrors[] = 'Unable to delete that character.';
                }
            }
        }
    }
}

$characters = [];

if ($accountId > 0) {
    $stmt = $pdo->prepare('SELECT id, name, level, vocation, sex, town_id FROM players WHERE account_id = ? ORDER BY name ASC');
    $stmt->execute([$accountId]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$townOptions = [];

if (nx_table_exists($pdo, 'towns')) {
    $townStmt = $pdo->query('SELECT id, name FROM towns ORDER BY id ASC');
    foreach ($townStmt->fetchAll(PDO::FETCH_ASSOC) as $town) {
        $townId = (int) ($town['id'] ?? 0);
        $townName = trim((string) ($town['name'] ?? ''));
        if ($townId > 0) {
            $townOptions[$townId] = $townName !== '' ? $townName : 'Town #' . $townId;
        }
    }
}

if ($townOptions === []) {
    $townOptions = [1 => 'Town #1'];
}

$vocations = [
    0 => 'None',
    1 => 'Sorcerer',
    2 => 'Druid',
    3 => 'Paladin',
    4 => 'Knight',
];

?>
<section class="page page--characters">
    <h2 class="mb-3">My Characters</h2>

    <?php if ($errorMessage): ?>
        <div class="alert alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($accountId <= 0): ?>
        <div class="alert alert--info">Link your game account to create and manage characters.</div>
    <?php endif; ?>

    <div class="mb-4">
        <h3 class="h5">Your roster</h3>
        <?php if ($characters === []): ?>
            <p class="text-muted mb-0">No characters yet. Create one using the form below.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Level</th>
                            <th scope="col">Vocation</th>
                            <th scope="col">Sex</th>
                            <th scope="col">Town</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($characters as $character): ?>
                            <tr>
                                <td><?php echo char_link((string) $character['name']); ?></td>
                                <td><?php echo (int) ($character['level'] ?? 1); ?></td>
                                <td><?php echo sanitize($vocations[(int) ($character['vocation'] ?? 0)] ?? 'Unknown'); ?></td>
                                <td><?php echo (int) ($character['sex'] ?? 0) === 1 ? 'Male' : 'Female'; ?></td>
                                <td>
                                    <?php
                                        $townId = (int) ($character['town_id'] ?? 1);
                                        echo sanitize($townOptions[$townId] ?? ('Town #' . $townId));
                                    ?>
                                </td>
                                <td class="text-end">
                                    <form method="post" action="?p=characters" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="character_id" value="<?php echo (int) $character['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete <?php echo sanitize($character['name']); ?>?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($deleteErrors !== []): ?>
            <div class="alert alert--error mt-3">
                <ul class="mb-0">
                    <?php foreach ($deleteErrors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="card nx-glow">
        <div class="card-body">
            <h3 class="h5">Create Character</h3>
            <p class="text-muted">Choose a unique name and starting vocation to enter the world.</p>

            <?php if ($createErrors !== []): ?>
                <div class="alert alert--error">
                    <ul class="mb-0">
                        <?php foreach ($createErrors as $error): ?>
                            <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="?p=characters" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="action" value="create">

                <div class="col-12">
                    <label class="form-label" for="character-name">Name</label>
                    <input
                        type="text"
                        class="form-control"
                        id="character-name"
                        name="name"
                        pattern="[A-Za-z ]{3,20}"
                        required
                        value="<?php echo sanitize($nameValue); ?>"
                    >
                    <div class="form-text">Letters and spaces only, 3-20 characters. No double spaces.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="character-sex">Sex</label>
                    <select class="form-select" id="character-sex" name="sex">
                        <option value="0"<?php echo $sexValue === '0' ? ' selected' : ''; ?>>Female</option>
                        <option value="1"<?php echo $sexValue === '1' ? ' selected' : ''; ?>>Male</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="character-vocation">Vocation</label>
                    <select class="form-select" id="character-vocation" name="vocation">
                        <?php foreach ($vocations as $id => $label): ?>
                            <option value="<?php echo (int) $id; ?>"<?php echo $vocationValue === (string) $id ? ' selected' : ''; ?>><?php echo sanitize($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="character-town">Town</label>
                    <select class="form-select" id="character-town" name="town_id">
                        <?php foreach ($townOptions as $id => $label): ?>
                            <option value="<?php echo (int) $id; ?>"<?php echo $townValue === (string) $id ? ' selected' : ''; ?>><?php echo sanitize($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create Character</button>
                </div>
            </form>
        </div>
    </div>
</section>
