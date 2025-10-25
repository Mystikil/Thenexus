<?php

declare(strict_types=1);

$adminPageTitle = 'Shop';
$adminNavActive = 'shop';

require __DIR__ . '/partials/header.php';

$pdo = db();
$currentAdmin = current_user();
$tab = $_GET['tab'] ?? 'products';
$tab = in_array($tab, ['products', 'orders'], true) ? $tab : 'products';
$csrfToken = csrf_token();
$successMessage = take_flash('success');
$errorMessage = take_flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;
    $redirectTab = $_POST['tab'] ?? $tab;
    $redirectTab = in_array($redirectTab, ['products', 'orders'], true) ? $redirectTab : 'products';

    if (!csrf_validate($token)) {
        flash('error', 'Invalid request. Please try again.');
        redirect('shop.php?tab=' . urlencode($redirectTab));
    }

    try {
        switch ($action) {
            case 'create_product':
                $name = trim((string) ($_POST['name'] ?? ''));
                $itemId = (int) ($_POST['item_id'] ?? 0);
                $priceCoins = (int) ($_POST['price_coins'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($name === '') {
                    throw new RuntimeException('Product name is required.');
                }

                if ($itemId <= 0) {
                    throw new RuntimeException('Item ID must be greater than zero.');
                }

                if ($priceCoins < 0) {
                    throw new RuntimeException('Price cannot be negative.');
                }

                $stmt = $pdo->prepare('INSERT INTO shop_products (name, item_id, price_coins, is_active) VALUES (:name, :item_id, :price_coins, :is_active)');
                $stmt->execute([
                    'name' => $name,
                    'item_id' => $itemId,
                    'price_coins' => $priceCoins,
                    'is_active' => $isActive,
                ]);

                $productId = (int) $pdo->lastInsertId();

                audit_log($currentAdmin['id'] ?? null, 'admin_shop_product_create', null, [
                    'product_id' => $productId,
                    'name' => $name,
                    'item_id' => $itemId,
                    'price_coins' => $priceCoins,
                    'is_active' => $isActive,
                ]);

                flash('success', 'Product created successfully.');
                redirect('shop.php?tab=products');
                break;

            case 'update_product':
                $productId = (int) ($_POST['product_id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                $itemId = (int) ($_POST['item_id'] ?? 0);
                $priceCoins = (int) ($_POST['price_coins'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $stmt = $pdo->prepare('SELECT * FROM shop_products WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $productId]);
                $product = $stmt->fetch();

                if ($product === false) {
                    throw new RuntimeException('Product not found.');
                }

                if ($name === '') {
                    throw new RuntimeException('Product name is required.');
                }

                if ($itemId <= 0) {
                    throw new RuntimeException('Item ID must be greater than zero.');
                }

                if ($priceCoins < 0) {
                    throw new RuntimeException('Price cannot be negative.');
                }

                $updateStmt = $pdo->prepare('UPDATE shop_products SET name = :name, item_id = :item_id, price_coins = :price_coins, is_active = :is_active WHERE id = :id');
                $updateStmt->execute([
                    'name' => $name,
                    'item_id' => $itemId,
                    'price_coins' => $priceCoins,
                    'is_active' => $isActive,
                    'id' => $productId,
                ]);

                audit_log($currentAdmin['id'] ?? null, 'admin_shop_product_update', $product, [
                    'id' => $productId,
                    'name' => $name,
                    'item_id' => $itemId,
                    'price_coins' => $priceCoins,
                    'is_active' => $isActive,
                ]);

                flash('success', 'Product updated successfully.');
                redirect('shop.php?tab=products');
                break;

            case 'delete_product':
                $productId = (int) ($_POST['product_id'] ?? 0);

                $stmt = $pdo->prepare('SELECT * FROM shop_products WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $productId]);
                $product = $stmt->fetch();

                if ($product === false) {
                    throw new RuntimeException('Product not found.');
                }

                $deleteStmt = $pdo->prepare('DELETE FROM shop_products WHERE id = :id');
                $deleteStmt->execute(['id' => $productId]);

                audit_log($currentAdmin['id'] ?? null, 'admin_shop_product_delete', $product, null);

                flash('success', 'Product deleted successfully.');
                redirect('shop.php?tab=products');
                break;

            case 'enqueue_order':
                $orderId = (int) ($_POST['order_id'] ?? 0);

                $orderStmt = $pdo->prepare('SELECT o.*, u.email, p.name AS product_name, p.item_id
                    FROM shop_orders o
                    INNER JOIN website_users u ON u.id = o.user_id
                    INNER JOIN shop_products p ON p.id = o.product_id
                    WHERE o.id = :id AND o.status = "pending"
                    LIMIT 1');
                $orderStmt->execute(['id' => $orderId]);
                $order = $orderStmt->fetch();

                if ($order === false) {
                    throw new RuntimeException('Order not found or already processed.');
                }

                if (trim((string) $order['player_name']) === '') {
                    throw new RuntimeException('Order is missing a character name.');
                }

                $existingJobStmt = $pdo->prepare("SELECT id FROM rcon_jobs WHERE type = 'deliver_shop_order' AND args_json LIKE :pattern AND status IN ('queued', 'in_progress') LIMIT 1");
                $existingJobStmt->execute(['pattern' => '%"order_id":' . $orderId . '%']);

                if ($existingJobStmt->fetch()) {
                    throw new RuntimeException('An active delivery job already exists for this order.');
                }

                $args = [
                    'order_id' => (int) $order['id'],
                    'player' => $order['player_name'],
                    'item_id' => (int) $order['item_id'],
                    'count' => 1,
                    'inbox' => true,
                ];

                $argsJson = json_encode($args, JSON_UNESCAPED_UNICODE);

                $jobStmt = $pdo->prepare('INSERT INTO rcon_jobs (type, args_json) VALUES (:type, :args_json)');
                $jobStmt->execute([
                    'type' => 'deliver_shop_order',
                    'args_json' => $argsJson,
                ]);

                $jobId = (int) $pdo->lastInsertId();

                $updateOrderStmt = $pdo->prepare('UPDATE shop_orders SET result_text = :result_text WHERE id = :id');
                $updateOrderStmt->execute([
                    'result_text' => 'Queued in job #' . $jobId,
                    'id' => $orderId,
                ]);

                audit_log($currentAdmin['id'] ?? null, 'admin_shop_order_enqueue', $order, [
                    'order_id' => $orderId,
                    'job_id' => $jobId,
                    'args' => $args,
                ]);

                flash('success', 'Delivery job queued successfully.');
                redirect('shop.php?tab=orders');
                break;

            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
        redirect('shop.php?tab=' . urlencode($redirectTab));
    }
}

$products = $pdo->query('SELECT id, name, item_id, price_coins, is_active FROM shop_products ORDER BY name ASC')->fetchAll();

$editProduct = null;

if ($tab === 'products' && isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($products as $product) {
        if ((int) $product['id'] === $editId) {
            $editProduct = $product;
            break;
        }
    }
}

$ordersStmt = $pdo->query('SELECT o.id, o.player_name, o.created_at, u.email, p.name AS product_name, p.item_id, p.price_coins
    FROM shop_orders o
    INNER JOIN website_users u ON u.id = o.user_id
    INNER JOIN shop_products p ON p.id = o.product_id
    WHERE o.status = "pending"
    ORDER BY o.created_at ASC');
$pendingOrders = $ordersStmt->fetchAll();
?>
<section class="admin-section admin-section--shop">
    <h2>Shop Management</h2>

    <div class="admin-tabs">
        <a class="admin-tabs__link <?php echo $tab === 'products' ? 'is-active' : ''; ?>" href="?tab=products">Products</a>
        <a class="admin-tabs__link <?php echo $tab === 'orders' ? 'is-active' : ''; ?>" href="?tab=orders">Orders</a>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert--success"><?php echo sanitize($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($tab === 'products'): ?>
        <section class="admin-card">
            <h3>Create Product</h3>
            <form method="post" class="admin-form">
                <input type="hidden" name="action" value="create_product">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="tab" value="products">

                <div class="form-group">
                    <label for="product-name">Name</label>
                    <input type="text" id="product-name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="product-item-id">Item ID</label>
                    <input type="number" id="product-item-id" name="item_id" min="1" required>
                </div>

                <div class="form-group">
                    <label for="product-price">Price (coins)</label>
                    <input type="number" id="product-price" name="price_coins" min="0" required>
                </div>

                <div class="form-group form-group--checkbox">
                    <label>
                        <input type="checkbox" name="is_active" value="1">
                        Active
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="admin-button">Create</button>
                </div>
            </form>
        </section>

        <section class="admin-card">
            <h3>Existing Products</h3>

            <?php if ($products === []): ?>
                <p>No products found.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Item ID</th>
                            <th>Price (coins)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo (int) $product['id']; ?></td>
                                <td><?php echo sanitize($product['name']); ?></td>
                                <td><?php echo (int) $product['item_id']; ?></td>
                                <td><?php echo sanitize(number_format((int) $product['price_coins'])); ?></td>
                                <td><?php echo (int) $product['is_active'] === 1 ? 'Active' : 'Inactive'; ?></td>
                                <td class="admin-table__actions">
                                    <a class="admin-button admin-button--secondary" href="?tab=products&amp;edit=<?php echo (int) $product['id']; ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this product?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="tab" value="products">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                        <button type="submit" class="admin-button admin-button--danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($editProduct !== null): ?>
            <section class="admin-card">
                <h3>Edit Product #<?php echo (int) $editProduct['id']; ?></h3>
                <form method="post" class="admin-form">
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="tab" value="products">
                    <input type="hidden" name="product_id" value="<?php echo (int) $editProduct['id']; ?>">

                    <div class="form-group">
                        <label for="edit-name">Name</label>
                        <input type="text" id="edit-name" name="name" value="<?php echo sanitize($editProduct['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="edit-item-id">Item ID</label>
                        <input type="number" id="edit-item-id" name="item_id" value="<?php echo (int) $editProduct['item_id']; ?>" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="edit-price">Price (coins)</label>
                        <input type="number" id="edit-price" name="price_coins" value="<?php echo (int) $editProduct['price_coins']; ?>" min="0" required>
                    </div>

                    <div class="form-group form-group--checkbox">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php echo (int) $editProduct['is_active'] === 1 ? 'checked' : ''; ?>>
                            Active
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="admin-button">Save Changes</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <section class="admin-card">
            <h3>Pending Orders</h3>

            <?php if ($pendingOrders === []): ?>
                <p>No pending orders found.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Character</th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingOrders as $order): ?>
                            <tr>
                                <td><?php echo (int) $order['id']; ?></td>
                                <td><?php echo sanitize($order['email']); ?></td>
                                <td><?php echo sanitize($order['player_name']); ?></td>
                                <td>
                                    <?php echo sanitize($order['product_name']); ?>
                                    <div class="admin-table__meta">Item ID: <?php echo (int) $order['item_id']; ?></div>
                                </td>
                                <td><?php echo sanitize(number_format((int) $order['price_coins'])); ?></td>
                                <td><?php echo sanitize($order['created_at']); ?></td>
                                <td class="admin-table__actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="enqueue_order">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="tab" value="orders">
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                        <button type="submit" class="admin-button">Enqueue Delivery</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php';
