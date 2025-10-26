<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_admin('admin');

$adminPageTitle = 'News';
$adminNavActive = 'news';

$pdo = db();

if (!$pdo instanceof PDO) {
    require __DIR__ . '/partials/header.php';
    echo '<section class="admin-section"><h2>News</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';

    return;
}

$currentAdmin = current_user();
$errors = [];
$formData = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'body' => '',
    'tags' => '',
];
$editingId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;

$slugify = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
    $value = trim((string) $value, '-');

    return $value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        $errors[] = 'The request could not be validated. Please try again.';
    } elseif ($action === 'save_news') {
        $newsId = isset($_POST['news_id']) ? (int) $_POST['news_id'] : 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $tagsInput = trim((string) ($_POST['tags'] ?? ''));

        $editingId = $newsId;
        $formData = [
            'id' => $newsId,
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
            'tags' => $tagsInput,
        ];

        if ($slug === '' && $title !== '') {
            $slug = $slugify($title);
        } else {
            $slug = $slug !== '' ? $slugify($slug) : '';
        }

        if ($slug === '') {
            $slug = 'news-' . time();
        }

        $tags = implode(', ', array_filter(array_map('trim', explode(',', $tagsInput)), static function (string $tag): bool {
            return $tag !== '';
        }));

        $formData['slug'] = $slug;
        $formData['tags'] = $tags;

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($body === '') {
            $errors[] = 'Body content is required.';
        }

        if ($errors === []) {
            try {
                if ($newsId > 0) {
                    $stmt = $pdo->prepare('UPDATE news SET title = :title, slug = :slug, body = :body, tags = :tags WHERE id = :id');
                    $stmt->execute([
                        'title' => $title,
                        'slug' => $slug,
                        'body' => $body,
                        'tags' => $tags,
                        'id' => $newsId,
                    ]);
                    $savedId = $newsId;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO news (title, slug, body, tags, created_at) VALUES (:title, :slug, :body, :tags, NOW())');
                    $stmt->execute([
                        'title' => $title,
                        'slug' => $slug,
                        'body' => $body,
                        'tags' => $tags,
                    ]);
                    $savedId = (int) $pdo->lastInsertId();
                }

                if (function_exists('audit_log')) {
                    audit_log($currentAdmin['id'] ?? null, 'news_save', null, ['news_id' => $savedId]);
                }

                flash('success', 'News entry saved successfully.');
                redirect('news.php');
            } catch (Throwable $exception) {
                $errors[] = 'Unable to save the news entry. Please try again.';
            }
        }
    } elseif ($action === 'delete_news') {
        $newsId = isset($_POST['news_id']) ? (int) $_POST['news_id'] : 0;

        if ($newsId <= 0) {
            $errors[] = 'Invalid news entry selected for deletion.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM news WHERE id = :id');
                $stmt->execute(['id' => $newsId]);

                if ($stmt->rowCount() > 0) {
                    if (function_exists('audit_log')) {
                        audit_log($currentAdmin['id'] ?? null, 'news_delete', null, ['news_id' => $newsId]);
                    }

                    flash('success', 'News entry deleted.');
                    redirect('news.php');
                } else {
                    $errors[] = 'The requested news entry could not be found.';
                }
            } catch (Throwable $exception) {
                $errors[] = 'Unable to delete the news entry. Please try again.';
            }
        }
    } else {
        $errors[] = 'Unknown action requested.';
    }
}

if ($editingId > 0 && ($formData['id'] ?? 0) === 0) {
    try {
        $stmt = $pdo->prepare('SELECT id, title, slug, body, tags FROM news WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $editingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false) {
            $formData = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'body' => (string) $row['body'],
                'tags' => (string) ($row['tags'] ?? ''),
            ];
        }
    } catch (Throwable $exception) {
        $errors[] = 'Unable to load the requested news entry.';
    }
}

$newsRows = [];

try {
    $stmt = $pdo->query('SELECT id, title, created_at FROM news ORDER BY created_at DESC LIMIT 200');
    $newsRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $exception) {
    $errors[] = 'Unable to load news entries.';
}

$successMessage = take_flash('success');
$errorMessage = take_flash('error');

require __DIR__ . '/partials/header.php';
?>
<section class="admin-section">
    <h2>Manage News</h2>
    <p>Create, edit, and delete news posts displayed on the site.</p>

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
            <h3><?php echo $formData['id'] > 0 ? 'Edit News Entry' : 'Create News Entry'; ?></h3>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_news">
                <input type="hidden" name="news_id" value="<?php echo (int) ($formData['id'] ?? 0); ?>">

                <div class="admin-form__group">
                    <label for="news-title">Title</label>
                    <input type="text" id="news-title" name="title" value="<?php echo sanitize($formData['title']); ?>" required>
                </div>

                <div class="admin-form__group">
                    <label for="news-slug">Slug</label>
                    <input type="text" id="news-slug" name="slug" value="<?php echo sanitize($formData['slug']); ?>" placeholder="auto-generated if empty">
                    <p class="admin-form__help">Used in URLs. Leave blank to auto-generate from the title.</p>
                </div>

                <div class="admin-form__group">
                    <label for="news-tags">Tags</label>
                    <input type="text" id="news-tags" name="tags" value="<?php echo sanitize($formData['tags']); ?>" placeholder="news, update">
                    <p class="admin-form__help">Comma-separated list of tags.</p>
                </div>

                <div class="admin-form__group">
                    <label for="news-body">Body</label>
                    <textarea id="news-body" name="body" rows="12" required><?php echo htmlspecialchars($formData['body']); ?></textarea>
                </div>

                <button type="submit" class="admin-button">Save News</button>
            </form>
        </div>

        <div class="admin-grid__item">
            <h3>Recent News</h3>
            <?php if ($newsRows === []): ?>
                <p class="text-muted">No news entries found.</p>
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
                        <?php foreach ($newsRows as $news): ?>
                            <tr>
                                <td><?php echo (int) $news['id']; ?></td>
                                <td><?php echo sanitize($news['title']); ?></td>
                                <td><?php echo sanitize((string) $news['created_at']); ?></td>
                                <td class="admin-table__actions">
                                    <a class="admin-link" href="news.php?id=<?php echo (int) $news['id']; ?>">Edit</a>
                                    <form method="post" action="news.php" class="admin-inline-form" onsubmit="return confirm('Delete this news entry?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete_news">
                                        <input type="hidden" name="news_id" value="<?php echo (int) $news['id']; ?>">
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
