<?php

declare(strict_types=1);

/**
 * Fetch the latest published news entries.
 *
 * @return array{items: list<array{id:int,title:string,slug:string,body:string,tags:array<int,string>,created_at:string}>, error: ?string}
 */
function nx_news_latest(PDO $pdo, int $limit = 5): array
{
    $limit = max(1, min(50, $limit));

    $result = [
        'items' => [],
        'error' => null,
    ];

    try {
        if (!nx_table_exists($pdo, 'news')) {
            $result['error'] = 'News content is currently unavailable.';

            return $result;
        }

        $stmt = $pdo->prepare('SELECT id, title, slug, body, tags, created_at
            FROM news
            ORDER BY created_at DESC
            LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $result['items'] = array_map(static function (array $row): array {
            $rawTags = array_filter(array_map('trim', explode(',', (string) ($row['tags'] ?? ''))));

            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                'tags' => array_values($rawTags),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    } catch (Throwable $exception) {
        error_log('news.php: ' . $exception->getMessage());
        $result['error'] = 'News content is currently unavailable.';
    }

    return $result;
}

function nx_news_format_date(?string $value, string $format = 'F j, Y H:i'): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable($value);

        return $date->format($format);
    } catch (Exception $exception) {
        return trim((string) $value);
    }
}

