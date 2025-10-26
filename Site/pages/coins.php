<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../lib/commerce.php';

@session_start();

require_login();

$pdo = db();

if (!$pdo instanceof PDO) {
    echo '<section class="page page--coins"><h2>Coins &amp; Premium</h2><p class="text-muted mb-0">The database connection is unavailable.</p></section>';
    return;
}

$user = current_user();
$accountId = isset($user['account_id']) ? (int) $user['account_id'] : 0;

$csrfToken = csrf_token();
$successMessage = take_flash('success');
$errorMessage = take_flash('error');
$errors = [];

$coinBalance = 0;
$premiumEndsAt = 0;

if ($accountId > 0 && nx_table_exists($pdo, 'web_accounts')) {
    $balanceStmt = $pdo->prepare('SELECT points FROM web_accounts WHERE account_id = ? LIMIT 1');
    $balanceStmt->execute([$accountId]);
    $coinBalance = (int) ($balanceStmt->fetchColumn() ?: 0);
}

if ($accountId > 0) {
    $premiumStmt = $pdo->prepare('SELECT premium_ends_at FROM accounts WHERE id = ? LIMIT 1');
    $premiumStmt->execute([$accountId]);
    $premiumEndsAt = (int) ($premiumStmt->fetchColumn() ?: 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif ($accountId <= 0) {
        $errors[] = 'Link your game account before managing coins or premium time.';
    } elseif ($action === 'buy_coins') {
        $coins = isset($_POST['coins']) ? (int) $_POST['coins'] : 0;
        if ($coins <= 0) {
            $errors[] = 'Select a valid coin package.';
        } else {
            nx_add_coins($pdo, $accountId, $coins);
            flash('success', 'Coins added to your balance.');
            nx_redirect('?p=coins');
        }
    } elseif ($action === 'buy_premium') {
        $days = isset($_POST['days']) ? (int) $_POST['days'] : 0;
        if ($days <= 0) {
            $errors[] = 'Select a valid premium package.';
        } else {
            nx_add_premium_days($pdo, $accountId, $days);
            flash('success', 'Premium time extended.');
            nx_redirect('?p=coins');
        }
    } else {
        $errors[] = 'Unknown action requested.';
    }
}

$coinsOptions = [250, 500, 1000];
$premiumOptions = [7, 30];

$premiumStatus = 'None';
if ($premiumEndsAt > time()) {
    $premiumStatus = date('Y-m-d H:i', $premiumEndsAt);
}

?>
<section class="page page--coins">
    <h2 class="mb-3">Coins &amp; Premium</h2>

    <?php if ($errorMessage): ?>
        <div class="alert alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert--error">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card nx-glow mb-4">
        <div class="card-body">
            <h3 class="h5">Account Summary</h3>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 border rounded bg-light">
                        <div class="text-muted text-uppercase small">Coins</div>
                        <div class="fs-4 fw-semibold text-reset"><?php echo number_format($coinBalance); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded bg-light">
                        <div class="text-muted text-uppercase small">Premium</div>
                        <div class="fs-6 fw-semibold text-reset"><?php echo sanitize($premiumStatus); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5">Buy Coins</h3>
                    <p class="text-muted">Add coins to your web balance instantly. Future payment integrations will deduct automatically.</p>
                    <div class="row g-2">
                        <?php foreach ($coinsOptions as $amount): ?>
                            <div class="col-6">
                                <form method="post" action="?p=coins" class="d-grid">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="action" value="buy_coins">
                                    <input type="hidden" name="coins" value="<?php echo (int) $amount; ?>">
                                    <button type="submit" class="btn btn-outline-primary">+<?php echo number_format($amount); ?> coins</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h5">Buy Premium</h3>
                    <p class="text-muted">Extend your premium time immediately. Prices are provisional for manual fulfillment.</p>
                    <div class="row g-2">
                        <?php foreach ($premiumOptions as $days): ?>
                            <div class="col-6">
                                <form method="post" action="?p=coins" class="d-grid">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="action" value="buy_premium">
                                    <input type="hidden" name="days" value="<?php echo (int) $days; ?>">
                                    <button type="submit" class="btn btn-outline-success">+<?php echo (int) $days; ?> days</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted mt-3 small">Need help? Contact support after purchasing so an admin can review manual payments.</p>
</section>
