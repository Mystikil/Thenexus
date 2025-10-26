<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_admin('admin');

$adminPageTitle = 'CMS';
$adminNavActive = 'cms';

$pdo = db();

if (!$pdo instanceof PDO) {
    require __DIR__ . '/partials/header.php';
    echo '<section class="admin-section"><h2>CMS</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';

    return;
}

$currentAdmin = current_user();
$errors = [];
$cmsPages = [
    'rules' => 'Rules',
    'downloads' => 'Downloads',
    'about' => 'About',
];

$requestedSlug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$editSlug = $requestedSlug !== '' && isset($cmsPages[$requestedSlug]) ? $requestedSlug : 'rules';
$formData = [
    'slug' => $editSlug,
    'title' => '',
    'body' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        $errors[] = 'The request could not be validated. Please try again.';
    } elseif ($action === 'save_page') {
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));

        if (!isset($cmsPages[$slug])) {
            $errors[] = 'Unknown CMS page selected.';
        }

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($body === '') {
            $errors[] = 'Body content is required.';
        }

        $formData = [
            'slug' => $slug,
            'title' => $title,
            'body' => $body,
        ];
        $editSlug = $slug;

        if ($errors === []) {
            try {
                $stmt = $pdo->prepare('INSERT INTO cms_pages (slug, title, body) VALUES (:slug, :title, :body)
                    ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body)');
                $stmt->execute([
                    'slug' => $slug,
                    'title' => $title,
                    'body' => $body,
                ]);

                if (function_exists('audit_log')) {
                    audit_log($currentAdmin['id'] ?? null, 'cms_save', null, ['slug' => $slug]);
                }

                flash('success', 'Page saved successfully.');
                redirect('cms.php?slug=' . urlencode($slug));
            } catch (Throwable $exception) {
                $errors[] = 'Unable to save the CMS page. Please try again.';
            }
        }
    } else {
        $errors[] = 'Unknown action requested.';
    }
}

$storedPages = [];

try {
    $placeholders = implode(', ', array_fill(0, count($cmsPages), '?'));
    $stmt = $pdo->prepare('SELECT slug, title, body FROM cms_pages WHERE slug IN (' . $placeholders . ')');
    $stmt->execute(array_keys($cmsPages));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $storedPages[strtolower((string) $row['slug'])] = [
            'title' => (string) $row['title'],
            'body' => (string) $row['body'],
        ];
    }
} catch (Throwable $exception) {
    $errors[] = 'Unable to load CMS pages from the database.';
}

if ($formData['title'] === '' && isset($storedPages[$editSlug])) {
    $formData['title'] = $storedPages[$editSlug]['title'];
    $formData['body'] = $storedPages[$editSlug]['body'];
}

$successMessage = take_flash('success');
$errorMessage = take_flash('error');

require __DIR__ . '/partials/header.php';
?>
<section class="admin-section">
    <h2>Content Management</h2>
    <p>Edit the static pages displayed on the website.</p>

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
            <h3>Edit Page</h3>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
                <input type="hidden" name="action" value="save_page">
                <input type="hidden" name="slug" value="<?php echo sanitize($formData['slug']); ?>">

                <div class="admin-form__group">
                    <label for="cms-title">Title</label>
                    <input type="text" id="cms-title" name="title" value="<?php echo sanitize($formData['title']); ?>" required>
                </div>

                <div class="admin-form__group">
                    <label for="cms-body">Body</label>
                    <textarea id="cms-body" name="body" rows="12" required><?php echo htmlspecialchars($formData['body']); ?></textarea>
                </div>

                <button type="submit" class="admin-button">Save Page</button>
            </form>
        </div>

        <div class="admin-grid__item">
            <h3>Pages</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cmsPages as $slug => $label): ?>
                        <?php $page = $storedPages[$slug] ?? null; ?>
                        <tr>
                            <td><?php echo sanitize($slug); ?></td>
                            <td><?php echo sanitize($page['title'] ?? $label); ?></td>
                            <td>
                                <?php if ($page !== null && trim($page['body']) !== ''): ?>
                                    <span class="admin-status admin-status--ok">Published</span>
                                <?php else: ?>
                                    <span class="admin-status admin-status--warning">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="admin-link" href="cms.php?slug=<?php echo urlencode($slug); ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php
require __DIR__ . '/partials/footer.php';
