<?php
declare(strict_types=1);

$user = current_user();
$pdo = db();
$purchaseErrors = [];
$successMessage = take_flash('success');
$errorMessage = take_flash('error');
$csrfToken = csrf_token();
$selectedProductId = null;
$characterNameInput = '';

if ($user !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        $purchaseErrors[] = 'Invalid request. Please try again.';
    } else {
        $selectedProductId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : null;
        $characterNameInput = trim((string) ($_POST['character_name'] ?? ''));

        if ($selectedProductId === null || $selectedProductId <= 0) {
            $purchaseErrors[] = 'Please choose a product to buy.';
        }

        if ($characterNameInput === '') {
            $purchaseErrors[] = 'Please enter the name of the character that should receive the delivery.';
        }

        if ($characterNameInput !== '') {
            $characterLength = mb_strlen($characterNameInput, 'UTF-8');

            if ($characterLength > 255) {
                $purchaseErrors[] = 'Character names must be 255 characters or fewer.';
            } else {
                $playerCheck = $pdo->prepare('SELECT id FROM players WHERE name = :name LIMIT 1');
                $playerCheck->execute(['name' => $characterNameInput]);

                if ($playerCheck->fetch() === false) {
                    $purchaseErrors[] = 'That character could not be found.';
                }
            }
        }

        $product = null;

        if ($purchaseErrors === [] && $selectedProductId !== null) {
            $stmt = $pdo->prepare('SELECT id, name, item_id, price_coins FROM shop_products WHERE id = :id AND is_active = 1 LIMIT 1');
            $stmt->execute(['id' => $selectedProductId]);
            $product = $stmt->fetch();

            if ($product === false) {
                $purchaseErrors[] = 'The selected product is not available.';
            }
        }

        if ($purchaseErrors === [] && $product !== null) {
            $productId = (int) $product['id'];
            $priceCoins = (int) $product['price_coins'];

            try {
                $pdo->beginTransaction();

                $balanceStmt = $pdo->prepare('SELECT coins FROM coin_balances WHERE user_id = :user_id FOR UPDATE');
                $balanceStmt->execute(['user_id' => $user['id']]);
                $balanceRow = $balanceStmt->fetch();
                $currentCoins = $balanceRow !== false ? (int) $balanceRow['coins'] : 0;

                if ($priceCoins > $currentCoins) {
                    $pdo->rollBack();
                    $purchaseErrors[] = 'You do not have enough coins to buy this product.';
                    audit_log((int) $user['id'], 'shop_purchase_failed', [
                        'product_id' => $productId,
                        'character' => $characterNameInput,
                        'coins' => $currentCoins,
                        'price' => $priceCoins,
                    ]);
                } else {
                    if ($balanceRow === false) {
                        $balanceInsert = $pdo->prepare('INSERT INTO coin_balances (user_id, coins) VALUES (:user_id, :coins)');
                        $balanceInsert->execute([
                            'user_id' => $user['id'],
                            'coins' => 0,
                        ]);
                    }

                    $coinsAfter = $currentCoins;

                    if ($priceCoins > 0) {
                        $updateStmt = $pdo->prepare('UPDATE coin_balances SET coins = coins - :price WHERE user_id = :user_id');
                        $updateStmt->execute([
                            'price' => $priceCoins,
                            'user_id' => $user['id'],
                        ]);
                        $coinsAfter = $currentCoins - $priceCoins;
                    }

                    $orderStmt = $pdo->prepare('INSERT INTO shop_orders (user_id, product_id, player_name) VALUES (:user_id, :product_id, :player_name)');
                    $orderStmt->execute([
                        'user_id' => $user['id'],
                        'product_id' => $productId,
                        'player_name' => $characterNameInput,
                    ]);
                    $orderId = (int) $pdo->lastInsertId();

                    $pdo->commit();

                    audit_log((int) $user['id'], 'shop_purchase_created', [
                        'product_id' => $productId,
                        'player_name' => $characterNameInput,
                        'coins_before' => $currentCoins,
                    ], [
                        'order_id' => $orderId,
                        'coins_after' => max(0, $coinsAfter),
                    ]);

                    flash('success', 'Your order has been placed and will be processed soon.');
                    redirect('?p=shop');
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $purchaseErrors[] = 'Unable to place your order right now. Please try again.';
                audit_log((int) $user['id'], 'shop_purchase_error', [
                    'product_id' => $selectedProductId,
                    'player_name' => $characterNameInput,
                ], [
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}

$productsStmt = $pdo->query('SELECT id, name, item_id, price_coins FROM shop_products WHERE is_active = 1 ORDER BY name ASC');
$products = $productsStmt->fetchAll();

$coinBalance = 0;

if ($user !== null) {
    $coinsStmt = $pdo->prepare('SELECT coins FROM coin_balances WHERE user_id = :user_id LIMIT 1');
    $coinsStmt->execute(['user_id' => $user['id']]);
    $coinsRow = $coinsStmt->fetch();
    $coinBalance = $coinsRow !== false ? (int) $coinsRow['coins'] : 0;
}
?>
<section class="page page--shop">
    <h2>Shop</h2>

    <?php if ($errorMessage): ?>
        <div class="alert alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if (!$user): ?>
        <p>You need to <a href="?p=account">log in</a> to buy products from the shop.</p>
    <?php else: ?>
        <div class="shop__balance">Current balance: <strong><?php echo sanitize(number_format($coinBalance)); ?></strong> coins</div>

        <?php if ($purchaseErrors): ?>
            <div class="alert alert--error">
                <ul class="form-errors">
                    <?php foreach ($purchaseErrors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($products === []): ?>
            <p>The shop is currently closed. Please check back later.</p>
        <?php else: ?>
            <table class="table table--shop">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Item ID</th>
                        <th>Price (coins)</th>
                        <th>Character</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php $productId = (int) $product['id']; ?>
                        <tr>
                            <td><?php echo sanitize($product['name']); ?></td>
                            <td><?php echo (int) $product['item_id']; ?></td>
                            <td><?php echo sanitize(number_format((int) $product['price_coins'])); ?></td>
                            <td>
                                <?php $formId = 'shop-order-' . $productId; ?>
                                <label class="visually-hidden" for="character-<?php echo $productId; ?>">Character Name</label>
                                <input
                                    type="text"
                                    id="character-<?php echo $productId; ?>"
                                    name="character_name"
                                    form="<?php echo sanitize($formId); ?>"
                                    value="<?php echo $selectedProductId === $productId ? sanitize($characterNameInput) : ''; ?>"
                                    placeholder="e.g. Hero Knight"
                                    required
                                >
                            </td>
                            <td>
                                <form method="post" id="<?php echo sanitize($formId); ?>" class="shop__order-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                    <button type="submit">Buy</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</section>
