<?php

declare(strict_types=1);

$adminPageTitle = 'Settings';
$adminNavActive = 'settings';

require __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../includes/theme.php';

$settingsMap = [
    'default_theme' => 'Default Theme',
    'site_title' => 'Site Title',
    'WEBHOOK_SECRET' => 'Webhook Secret',
    'BRIDGE_SECRET' => 'Bridge Secret',
];

$currentSettings = [
    'default_theme' => 'default',
    'site_title' => SITE_TITLE,
    'WEBHOOK_SECRET' => WEBHOOK_SECRET,
    'BRIDGE_SECRET' => BRIDGE_SECRET,
];

$availableThemes = nx_all_themes();

if (!isset($availableThemes['default'])) {
    $availableThemes['default'] = [
        'slug' => 'default',
        'name' => 'Default',
        'type' => 'skin',
        'version' => '',
        'path' => nx_theme_path('default'),
        'manifest' => [],
    ];
}

$availableThemes = array_replace([], $availableThemes);
ksort($availableThemes);

$availableThemeSlugs = array_keys($availableThemes);

$errors = [];
$successMessage = null;

$placeholders = implode(', ', array_fill(0, count($settingsMap), '?'));
$stmt = db()->prepare('SELECT `key`, value FROM settings WHERE `key` IN (' . $placeholders . ')');
$stmt->execute(array_keys($settingsMap));

while ($row = $stmt->fetch()) {
    $key = $row['key'];
    if (array_key_exists($key, $currentSettings)) {
        $currentSettings[$key] = (string) $row['value'];
    }
}

if (!in_array('default', $availableThemeSlugs, true)) {
    $availableThemeSlugs[] = 'default';
}

if (!in_array($currentSettings['default_theme'], $availableThemeSlugs, true)) {
    $currentSettings['default_theme'] = 'default';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'The request could not be validated. Please try again.';
    } else {
        $pdo = db();
        $upsert = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $user = current_user();
        $userId = $user !== null ? (int) $user['id'] : null;
        $changes = 0;

        foreach ($settingsMap as $key => $label) {
            $newValue = trim((string) ($_POST[$key] ?? ''));

            if ($key === 'default_theme') {
                if ($newValue === '' || !in_array($newValue, $availableThemeSlugs, true)) {
                    $newValue = 'default';
                }
            }
            $previousValue = $currentSettings[$key] ?? '';

            if ($newValue === $previousValue) {
                continue;
            }

            $upsert->execute([
                'key' => $key,
                'value' => $newValue,
            ]);

            audit_log($userId, 'update_setting', ['key' => $key, 'value' => $previousValue], ['key' => $key, 'value' => $newValue]);
            $currentSettings[$key] = $newValue;
            $changes++;
        }

        if ($changes > 0) {
            $successMessage = 'Settings updated successfully.';
        } else {
            $successMessage = 'No changes were detected.';
        }
    }
}
?>
<section class="admin-section">
    <h2>Settings</h2>
    <p>Update core site configuration values. Changes are logged for auditing.</p>

    <?php if ($errors !== []): ?>
        <div class="admin-alert admin-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($successMessage !== null): ?>
        <div class="admin-alert admin-alert--success">
            <?php echo sanitize($successMessage); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize(csrf_token()); ?>">
        <?php foreach ($settingsMap as $key => $label): ?>
            <div class="admin-form__group">
                <label for="<?php echo sanitize($key); ?>"><?php echo sanitize($label); ?></label>
                <?php if ($key === 'default_theme'): ?>
                    <select id="<?php echo sanitize($key); ?>" name="<?php echo sanitize($key); ?>">
                        <?php foreach ($availableThemeSlugs as $slug): ?>
                            <option value="<?php echo sanitize($slug); ?>"<?php echo ($currentSettings[$key] ?? '') === $slug ? ' selected' : ''; ?>>
                                <?php
                                    $theme = $availableThemes[$slug] ?? null;
                                    $labelText = $theme && isset($theme['name']) ? (string) $theme['name'] : ucfirst($slug);
                                    echo sanitize($labelText . ' (' . $slug . ')');
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input
                        type="text"
                        id="<?php echo sanitize($key); ?>"
                        name="<?php echo sanitize($key); ?>"
                        value="<?php echo sanitize($currentSettings[$key] ?? ''); ?>"
                    >
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="admin-button">Save Settings</button>
    </form>
</section>
<?php
require __DIR__ . '/partials/footer.php';
