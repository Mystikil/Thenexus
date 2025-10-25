<?php
require_once __DIR__ . '/../widgets/_registry.php';
require_once __DIR__ . '/theme.php';

$pdo = db();
$pageSlug = nx_current_page_slug();
$widgets = nx_widget_order($pdo, 'left', $pageSlug);

$renderWidget = static function (string $slug, ?int $limit = null): string {
    if ($limit !== null) {
        return render_widget_box($slug, $limit);
    }

    return render_widget_box($slug);
};

$template = nx_locate_template($pdo, 'sidebar-left');

if (is_string($template) && $template !== '') {
    $orderedWidgets = $widgets;
    $renderWidgetBox = $renderWidget;
    $currentPageSlug = $pageSlug;
    include $template;

    return;
}

foreach ($widgets as $widget) {
    if (!is_array($widget)) {
        continue;
    }

    if (empty($widget['enabled'])) {
        continue;
    }

    $slug = $widget['slug'] ?? '';

    if (!is_string($slug) || $slug === '') {
        continue;
    }

    $limit = $widget['limit'] ?? null;
    $limit = is_int($limit) ? $limit : null;

    echo $renderWidget($slug, $limit);
}
