<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../includes/theme.php';
require_admin('admin');

$adminPageTitle = 'Users';
$adminNavActive = 'users';

require __DIR__ . '/partials/header.php';

$pdo = db();

if (!$pdo instanceof PDO) {
    echo '<section class="admin-section"><h2>Users</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';

    return;
}

$currentAdmin = current_user();
$adminId = $currentAdmin !== null ? (int) $currentAdmin['id'] : null;
$actorIsMaster = $currentAdmin !== null && is_master($currentAdmin);

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$successMessage = take_flash('success');
$errorMessage = take_flash('error');
$passwordMessage = take_flash('password_notice');
$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $searchQuery = trim((string) ($_POST['search_query'] ?? $searchQuery));
    $userId = (int) ($_POST['user_id'] ?? 0);

    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid request. Please try again.');
        redirect('users.php?q=' . urlencode($searchQuery));
    }

    if ($userId <= 0) {
        flash('error', 'A valid user must be selected.');
        redirect('users.php?q=' . urlencode($searchQuery));
    }

    $userSql = sprintf(
        'SELECT wu.id, wu.email, wu.role, wu.account_id, wu.theme_preference, wu.created_at, a.%1$s AS account_name '
        . 'FROM website_users wu '
        . 'LEFT JOIN %2$s a ON a.%3$s = wu.account_id '
        . 'WHERE wu.id = :id LIMIT 1',
        TFS_NAME_COL,
        TFS_ACCOUNTS_TABLE,
        TFS_ACCOUNT_ID_COL
    );

    $stmt = $pdo->prepare($userSql);
    $stmt->execute(['id' => $userId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userRow === false) {
        flash('error', 'The specified user could not be found.');
        redirect('users.php?q=' . urlencode($searchQuery));
    }

    switch ($action) {
        case 'change_role':
            $role = strtolower(trim((string) ($_POST['role'] ?? '')));
            $allowedRoles = ['user', 'mod', 'gm', 'admin', 'owner'];

            if (!in_array($role, $allowedRoles, true)) {
                flash('error', 'Select a valid role.');
                redirect('users.php?q=' . urlencode($searchQuery));
            }

            $before = [
                'id' => (int) $userRow['id'],
                'role' => (string) $userRow['role'],
            ];

            $update = $pdo->prepare('UPDATE website_users SET role = :role WHERE id = :id');
            $update->execute([
                'role' => $role,
                'id' => $userRow['id'],
            ]);

            $after = $before;
            $after['role'] = $role;
            $after['a_is_master'] = $actorIsMaster ? 1 : 0;

            audit_log($adminId, 'admin_user_change_role', $before, $after);
            flash('success', 'Role updated successfully.');
            break;

        case 'set_theme':
            $rawPreference = (string) ($_POST['theme_preference'] ?? '');
            $preference = trim($rawPreference);
            $themeSlug = '';

            if ($preference !== '') {
                $themeSlug = nx_theme_normalize_slug($preference);
                $availableThemes = nx_themes_list();

                if ($themeSlug === '' || !isset($availableThemes[$themeSlug])) {
                    flash('error', 'Select a valid theme.');
                    redirect('users.php?q=' . urlencode($searchQuery));
                }
            }

            $before = [
                'id' => (int) $userRow['id'],
                'theme_preference' => $userRow['theme_preference'],
            ];

            $update = $pdo->prepare('UPDATE website_users SET theme_preference = :theme WHERE id = :id');
            $update->execute([
                'theme' => $themeSlug !== '' ? $themeSlug : null,
                'id' => $userRow['id'],
            ]);

            $after = [
                'id' => (int) $userRow['id'],
                'theme_preference' => $themeSlug !== '' ? $themeSlug : null,
                'a_is_master' => $actorIsMaster ? 1 : 0,
            ];

            audit_log($adminId, 'admin_user_set_theme', $before, $after);
            flash('success', 'Theme preference saved.');
            break;

        case 'reset_password':
            $accountId = (int) ($userRow['account_id'] ?? 0);

            if ($accountId <= 0) {
                flash('error', 'This user is not linked to a game account.');
                redirect('users.php?q=' . urlencode($searchQuery));
            }

            try {
                $newPassword = substr(bin2hex(random_bytes(8)), 0, 12);
            } catch (Throwable $exception) {
                $newPassword = substr(bin2hex(random_bytes(6)), 0, 12);
            }

            try {
                nx_password_set($pdo, $accountId, $newPassword);

                if (function_exists('nx_password_mode') && nx_password_mode() === 'dual') {
                    $updateWeb = $pdo->prepare('UPDATE website_users SET pass_hash = :hash WHERE id = :id');
                    $updateWeb->execute([
                        'hash' => nx_hash_web_secure($newPassword),
                        'id' => $userRow['id'],
                    ]);
                }

                $before = [
                    'user_id' => (int) $userRow['id'],
                    'account_id' => $accountId,
                ];

                $after = [
                    'user_id' => (int) $userRow['id'],
                    'account_id' => $accountId,
                    'generated_password_hint' => substr($newPassword, 0, 3) . str_repeat('*', 9),
                    'a_is_master' => $actorIsMaster ? 1 : 0,
                ];

                audit_log($adminId, 'admin_user_reset_password', $before, $after);
                flash('success', 'Password reset successfully.');
                flash('password_notice', 'New password for ' . $userRow['email'] . ': ' . $newPassword);
            } catch (Throwable $exception) {
                flash('error', 'Unable to reset password.');
            }

            break;

        default:
            flash('error', 'Unknown action requested.');
            break;
    }

    redirect('users.php?q=' . urlencode($searchQuery));
}

$whereClause = '';
$params = [];

if ($searchQuery !== '') {
    $whereClause = 'WHERE LOWER(wu.email) LIKE :email OR a.' . TFS_NAME_COL . ' LIKE :account_name';
    $params['email'] = '%' . strtolower($searchQuery) . '%';
    $params['account_name'] = '%' . $searchQuery . '%';
}

$listSql = sprintf(
    'SELECT wu.id, wu.email, wu.role, wu.account_id, wu.created_at, wu.theme_preference, a.%1$s AS account_name '
    . 'FROM website_users wu '
    . 'LEFT JOIN %2$s a ON a.%3$s = wu.account_id '
    . '%4$s '
    . 'ORDER BY wu.created_at DESC '
    . 'LIMIT 50',
    TFS_NAME_COL,
    TFS_ACCOUNTS_TABLE,
    TFS_ACCOUNT_ID_COL,
    $whereClause !== '' ? ' ' . $whereClause : ''
);

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$users = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$themes = nx_themes_list();
?>
<section class="admin-section">
    <h2>User Directory</h2>
    <p>Search by website email or linked game account name.</p>

    <form class="admin-form admin-form--inline" method="get" action="users.php">
        <div class="admin-form__group">
            <label for="search-query">Search users</label>
            <input type="text" id="search-query" name="q" value="<?php echo sanitize($searchQuery); ?>" placeholder="Email or account name">
        </div>
        <div class="admin-form__actions">
            <button type="submit" class="admin-button">Search</button>
        </div>
    </form>

    <?php if ($errorMessage): ?>
        <div class="admin-alert admin-alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="admin-alert admin-alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($passwordMessage): ?>
        <div class="admin-alert admin-alert--warning"><?php echo sanitize($passwordMessage); ?></div>
    <?php endif; ?>

    <?php if ($users === []): ?>
        <p>No users found for this query.</p>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Account</th>
                        <th>Theme</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo sanitize((string) $user['email']); ?></td>
                            <td><?php echo sanitize((string) $user['role']); ?></td>
                            <td>
                                <?php if ($user['account_id'] !== null): ?>
                                    <span>#<?php echo (int) $user['account_id']; ?></span>
                                    <?php if ($user['account_name']): ?>
                                        <div class="admin-table__meta"><?php echo sanitize((string) $user['account_name']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="admin-table__meta">Unlinked</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['theme_preference'] !== null && $user['theme_preference'] !== '' ? sanitize((string) $user['theme_preference']) : '<span class="admin-table__meta">Default</span>'; ?></td>
                            <td><?php echo sanitize((string) $user['created_at']); ?></td>
                            <td class="admin-table__actions">
                                <form method="post" class="admin-inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                    <input type="hidden" name="search_query" value="<?php echo sanitize($searchQuery); ?>">
                                    <select name="role">
                                        <?php foreach (['user', 'mod', 'gm', 'admin', 'owner'] as $roleOption): ?>
                                            <option value="<?php echo $roleOption; ?>" <?php echo $user['role'] === $roleOption ? 'selected' : ''; ?>><?php echo ucfirst($roleOption); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="admin-button admin-button--secondary">Update Role</button>
                                </form>

                                <form method="post" class="admin-inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="action" value="set_theme">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                    <input type="hidden" name="search_query" value="<?php echo sanitize($searchQuery); ?>">
                                    <select name="theme_preference">
                                        <option value="">Default</option>
                                        <?php foreach ($themes as $slug => $theme): ?>
                                            <option value="<?php echo sanitize($slug); ?>" <?php echo $user['theme_preference'] === $slug ? 'selected' : ''; ?>><?php echo sanitize((string) ($theme['name'] ?? ucfirst($slug))); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="admin-button admin-button--secondary">Set Theme</button>
                                </form>

                                <form method="post" class="admin-inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                    <input type="hidden" name="search_query" value="<?php echo sanitize($searchQuery); ?>">
                                    <button type="submit" class="admin-button">Reset Password</button>
                                </form>
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
