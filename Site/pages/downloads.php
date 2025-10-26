<?php

declare(strict_types=1);

$cmsSlug = 'downloads';
$defaultTitle = 'Downloads';
$contentTitle = $defaultTitle;
$contentBody = '';

try {
    $pdo = db();

    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT title, body FROM cms_pages WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $cmsSlug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false) {
            $contentTitle = (string) ($row['title'] ?? $defaultTitle);
            $contentBody = (string) ($row['body'] ?? '');
        }
    }
} catch (Throwable $exception) {
    // Ignore CMS loading errors and fall back to placeholder content.
}
?>
<section class="page page--downloads">
    <div class="container-page">
        <div class="card nx-card nx-glow">
            <div class="card-body">
                <h2 class="mb-3"><?php echo sanitize($contentTitle); ?></h2>
                <?php if (trim($contentBody) !== ''): ?>
                    <div class="cms-content"><?php echo $contentBody; ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">Content coming soon.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
