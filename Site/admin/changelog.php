<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_admin('admin');

$adminPageTitle = 'Changelog';
$adminNavActive = 'changelog';

$pdo = db();

if (!$pdo instanceof PDO) {
    require __DIR__ . '/partials/header.php';
    echo '<section class="admin-section"><h2>Changelog</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';

    return;
}

$currentAdmin = current_user();
$errors = [];
$formData = [
    'id' => 0,
    'title' => '',
    'body' => '',
];
$editingId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        $errors[] = 'The request could not be validated. Please try again.';
    } elseif ($action === 'save_changelog') {
        $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));

        $editingId = $entryId;
        $formData = [
            'id' => $entryId,
            'title' => $title,
            'body' => $body,
        ];

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($body === '') {
            $errors[] = 'Body content is required.';
        }

        if ($errors === []) {
            try {
                if ($entryId > 0) {
                    $stmt = $pdo->prepare('UPDATE changelog SET title = :title, body = :body WHERE id = :id');
                    $stmt->execute([
                        'title' => $title,
                        'body' => $body,
                        'id' => $entryId,
                    ]);
                    $savedId = $entryId;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO changelog (title, body, created_at) VALUES (:title, :body, NOW())');
                    $stmt->execute([
                        'title' => $title,
                        'body' => $body,
                    ]);
                    $savedId = (int) $pdo->lastInsertId();
                }

                if (function_exists('audit_log')) {
                    audit_log($currentAdmin['id'] ?? null, 'changelog_save', null, ['changelog_id' => $savedId]);
                }

                flash('success', 'Changelog entry saved successfully.');
                redirect('changelog.php');
            } catch (Throwable $exception) {
                $errors[] = 'Unable to save the changelog entry. Please try again.';
            }
        }
    } elseif ($action === 'delete_changelog') {
        $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;

        if ($entryId <= 0) {
            $errors[] = 'Invalid changelog entry selected for deletion.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM changelog WHERE id = :id');
                $stmt->execute(['id' => $entryId]);

                if ($stmt->rowCount() > 0) {
                    if (function_exists('audit_log')) {
                        audit_log($currentAdmin['id'] ?? null, 'changelog_delete', null, ['changelog_id' => $entryId]);
                    }

                    flash('success', 'Changelog entry deleted.');
                    redirect('changelog.php');
                } else {
                    $errors[] = 'The requested changelog entry could not be found.';
                }
            } catch (Throwable $exception) {
                $errors[] = 'Unable to delete the changelog entry. Please try again.';
            }
        }
    } else {
        $errors[] = 'Unknown action requested.';
    }
}

if ($editingId > 0 && ($formData['id'] ?? 0) === 0) {
    try {
        $stmt = $pdo->prepare('SELECT id, title, body FROM changelog WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $editingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false) {
            $formData = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
            ];
        }
    } catch (Throwable $exception) {
        $errors[] = 'Unable to load the requested changelog entry.';
    }
}

$entries = [];

try {
    $stmt = $pdo->query('SELECT id, title, created_at FROM changelog ORDER BY created_at DESC LIMIT 200');
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $exception) {
    $errors[] = 'Unable to load changelog entries.';
}

$successMessage = take_flash('success');
$errorMessage = take_flash('error');

require __DIR__ . '/partials/header.php';
?>
<section class="admin-section">
    <h2>Manage Changelog</h2>
    <p>Publish updates and patches for players to review.</p>

    <?php if ($errorMessage): ?>
        <div class="admin-alert admin-alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="admin-alert admin-alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="admin-alert admin-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="admin-grid">
        <div class="admin-grid__item">
            <h3><?php echo $formData['id'] > 0 ? 'Edit Entry' : 'Create Entry'; ?></h3>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_changelog">
                <input type="hidden" name="entry_id" value="<?php echo (int) ($formData['id'] ?? 0); ?>">

                <div class="admin-form__group">
                    <label for="changelog-title">Title</label>
                    <input type="text" id="changelog-title" name="title" value="<?php echo sanitize($formData['title']); ?>" required>
                </div>

                <div class="admin-form__group">
                    <label for="changelog-body">Body</label>
                    <textarea id="changelog-body" name="body" rows="12" required><?php echo htmlspecialchars($formData['body']); ?></textarea>
                </div>

                <button type="submit" class="admin-button">Save Entry</button>
            </form>
        </div>

        <div class="admin-grid__item">
            <h3>Recent Entries</h3>
            <?php if ($entries === []): ?>
                <p class="text-muted">No changelog entries found.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?php echo (int) $entry['id']; ?></td>
                                <td><?php echo sanitize($entry['title']); ?></td>
                                <td><?php echo sanitize((string) $entry['created_at']); ?></td>
                                <td class="admin-table__actions">
                                    <a class="admin-link" href="changelog.php?id=<?php echo (int) $entry['id']; ?>">Edit</a>
                                    <form method="post" action="changelog.php" class="admin-inline-form" onsubmit="return confirm('Delete this changelog entry?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete_changelog">
                                        <input type="hidden" name="entry_id" value="<?php echo (int) $entry['id']; ?>">
                                        <button type="submit" class="admin-link admin-link--danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
require __DIR__ . '/partials/footer.php';
