<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/commerce.php';

require_admin('admin');

$adminPageTitle = 'Payments';
$adminNavActive = 'payments';

$pdo = db();

if (!$pdo instanceof PDO) {
    require __DIR__ . '/partials/header.php';
    echo '<section class="admin-section"><h2>Payments</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';
    return;
}

$currentAdmin = current_user();
$csrfToken = csrf_token();
$errors = [];
$successMessage = take_flash('success');
$errorMessage = take_flash('error');
$grantCoins = ['identifier' => '', 'amount' => 250];
$grantPremium = ['identifier' => '', 'days' => 7];

/**
 * @return array{id:int,name:string,email:string}|null
 */
function nx_admin_find_account(PDO $pdo, string $identifier): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }

    $select = sprintf('%s AS id, %s AS name, email', TFS_ACCOUNT_ID_COL, TFS_NAME_COL);

    if (ctype_digit($identifier)) {
        $stmt = $pdo->prepare(sprintf('SELECT %s FROM %s WHERE %s = :value LIMIT 1', $select, TFS_ACCOUNTS_TABLE, TFS_ACCOUNT_ID_COL));
        $stmt->execute(['value' => (int) $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['id'] = (int) $row['id'];
            return $row;
        }
    }

    $stmt = $pdo->prepare(sprintf('SELECT %s FROM %s WHERE email = :value LIMIT 1', $select, TFS_ACCOUNTS_TABLE));
    $stmt->execute(['value' => $identifier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row !== false) {
        $row['id'] = (int) $row['id'];
        return $row;
    }

    $stmt = $pdo->prepare(sprintf('SELECT %s FROM %s WHERE %s = :value LIMIT 1', $select, TFS_ACCOUNTS_TABLE, TFS_NAME_COL));
    $stmt->execute(['value' => $identifier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row !== false) {
        $row['id'] = (int) $row['id'];
        return $row;
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        $errors[] = 'The request could not be validated. Please try again.';
    } elseif ($action === 'grant_coins') {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
        $grantCoins = ['identifier' => $identifier, 'amount' => $amount];

        if ($identifier === '') {
            $errors[] = 'Enter an account ID, email, or name.';
        } elseif ($amount <= 0) {
            $errors[] = 'Enter a positive amount of coins.';
        } else {
            $account = nx_admin_find_account($pdo, $identifier);
            if ($account === null) {
                $errors[] = 'Account not found.';
            } else {
                nx_add_coins($pdo, $account['id'], $amount);
                if (function_exists('audit_log')) {
                    audit_log($currentAdmin['id'] ?? null, 'admin_grant_coins', null, [
                        'account_id' => $account['id'],
                        'amount' => $amount,
                    ]);
                }
                flash('success', sprintf('Granted %d coins to %s.', $amount, $account['name'] ?? ('#' . $account['id'])));
                nx_redirect('payments.php');
            }
        }
    } elseif ($action === 'grant_premium') {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $days = isset($_POST['days']) ? (int) $_POST['days'] : 0;
        $grantPremium = ['identifier' => $identifier, 'days' => $days];

        if ($identifier === '') {
            $errors[] = 'Enter an account ID, email, or name.';
        } elseif ($days <= 0) {
            $errors[] = 'Enter a positive number of days.';
        } else {
            $account = nx_admin_find_account($pdo, $identifier);
            if ($account === null) {
                $errors[] = 'Account not found.';
            } else {
                nx_add_premium_days($pdo, $account['id'], $days);
                if (function_exists('audit_log')) {
                    audit_log($currentAdmin['id'] ?? null, 'admin_grant_premium', null, [
                        'account_id' => $account['id'],
                        'days' => $days,
                    ]);
                }
                flash('success', sprintf('Extended premium by %d day(s) for %s.', $days, $account['name'] ?? ('#' . $account['id'])));
                nx_redirect('payments.php');
            }
        }
    } else {
        $errors[] = 'Unknown action requested.';
    }
}

$payments = [];
if (nx_table_exists($pdo, 'payments')) {
    $stmt = $pdo->query('SELECT id, provider, provider_ref, account_id, email, amount_cents, coins, premium_days, status, created_at FROM payments ORDER BY created_at DESC LIMIT 50');
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$coinOrders = [];
if (nx_table_exists($pdo, 'coin_orders')) {
    $stmt = $pdo->query('SELECT id, account_id, coins, status, created_at FROM coin_orders ORDER BY created_at DESC LIMIT 50');
    $coinOrders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

require __DIR__ . '/partials/header.php';
?>
<section class="admin-section">
    <h2>Payments &amp; Orders</h2>
    <p>Review manual payments or grant rewards directly.</p>

    <?php if ($errorMessage): ?>
        <div class="admin-alert admin-alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="admin-alert admin-alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="admin-alert admin-alert--error">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="admin-grid">
        <div class="admin-card">
            <h3>Grant Coins</h3>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="action" value="grant_coins">
                <div class="admin-form__group">
                    <label for="grant-coins-identifier">Account (ID / email / name)</label>
                    <input type="text" id="grant-coins-identifier" name="identifier" value="<?php echo sanitize($grantCoins['identifier']); ?>" required>
                </div>
                <div class="admin-form__group">
                    <label for="grant-coins-amount">Coins</label>
                    <input type="number" id="grant-coins-amount" name="amount" min="1" value="<?php echo (int) $grantCoins['amount']; ?>" required>
                </div>
                <div class="admin-form__actions">
                    <button type="submit" class="admin-button">Grant Coins</button>
                </div>
            </form>
        </div>
        <div class="admin-card">
            <h3>Grant Premium Days</h3>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="action" value="grant_premium">
                <div class="admin-form__group">
                    <label for="grant-premium-identifier">Account (ID / email / name)</label>
                    <input type="text" id="grant-premium-identifier" name="identifier" value="<?php echo sanitize($grantPremium['identifier']); ?>" required>
                </div>
                <div class="admin-form__group">
                    <label for="grant-premium-days">Days</label>
                    <input type="number" id="grant-premium-days" name="days" min="1" value="<?php echo (int) $grantPremium['days']; ?>" required>
                </div>
                <div class="admin-form__actions">
                    <button type="submit" class="admin-button">Grant Premium</button>
                </div>
            </form>
        </div>
    </div>

    <h3 class="mt-4">Recent Payments</h3>
    <?php if ($payments === []): ?>
        <p class="text-muted">No payment records yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Provider</th>
                    <th>Account</th>
                    <th>Email</th>
                    <th>Amount</th>
                    <th>Coins</th>
                    <th>Premium (days)</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo (int) $payment['id']; ?></td>
                        <td><?php echo sanitize((string) $payment['provider']); ?></td>
                        <td><?php echo (int) $payment['account_id']; ?></td>
                        <td><?php echo sanitize((string) $payment['email']); ?></td>
                        <td><?php echo sprintf('%.2f', ((int) $payment['amount_cents']) / 100); ?></td>
                        <td><?php echo (int) $payment['coins']; ?></td>
                        <td><?php echo (int) $payment['premium_days']; ?></td>
                        <td><?php echo sanitize((string) $payment['status']); ?></td>
                        <td><?php echo sanitize((string) $payment['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3 class="mt-4">Coin Orders</h3>
    <?php if ($coinOrders === []): ?>
        <p class="text-muted">No coin orders recorded.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Account</th>
                    <th>Coins</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coinOrders as $order): ?>
                    <tr>
                        <td><?php echo (int) $order['id']; ?></td>
                        <td><?php echo (int) $order['account_id']; ?></td>
                        <td><?php echo (int) $order['coins']; ?></td>
                        <td><?php echo sanitize((string) $order['status']); ?></td>
                        <td><?php echo sanitize((string) $order['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/partials/footer.php';
