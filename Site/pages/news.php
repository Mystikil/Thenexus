<?php
require_once __DIR__ . '/../includes/news.php';

$newsItems = [];
$newsError = null;

$pdo = db();

if ($pdo instanceof PDO) {
    $newsResult = nx_news_latest($pdo, 25);
    $newsItems = $newsResult['items'];
    $newsError = $newsResult['error'];
} else {
    $newsError = 'News content is currently unavailable.';
}

unset($newsResult, $pdo);
?>

<section class="page page--news">
    <h2 class="mb-4">News</h2>

    <?php if ($newsError !== null): ?>
        <div class="alert alert-warning" role="alert">
            <?php echo sanitize($newsError); ?>
        </div>
    <?php elseif ($newsItems === []): ?>
        <p class="text-muted">No news posts have been published yet.</p>
    <?php else: ?>
        <div class="nx-news-feed">
            <?php foreach ($newsItems as $news): ?>
                <article class="nx-news-entry mb-4 pb-4 border-bottom">
                    <header class="mb-2">
                        <h3 class="h5 mb-1"><?php echo sanitize($news['title']); ?></h3>
                        <?php $publishedAt = nx_news_format_date($news['created_at']); ?>
                        <?php if ($publishedAt !== ''): ?>
                            <div class="text-muted small">
                                Published on <?php echo sanitize($publishedAt); ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <div class="nx-news-body">
                        <?php echo nl2br(sanitize($news['body'])); ?>
                    </div>

                    <?php if (!empty($news['tags'])): ?>
                        <div class="mt-3">
                            <?php foreach ($news['tags'] as $tag): ?>
                                <span class="badge rounded-pill text-bg-light me-1 mb-1"><?php echo sanitize($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
