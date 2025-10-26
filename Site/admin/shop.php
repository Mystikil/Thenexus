<?php

declare(strict_types=1);

require_once __DIR__ . '/partials/bootstrap.php';
require_once __DIR__ . '/../auth.php';
require_admin('admin');

$adminPageTitle = 'Shop';
$adminNavActive = 'shop';

require __DIR__ . '/partials/header.php';

$pdo = db();

if (!$pdo instanceof PDO) {
    echo '<section class="admin-section"><h2>Shop</h2><div class="admin-alert admin-alert--error">Database connection unavailable.</div></section>';
    require __DIR__ . '/partials/footer.php';

    return;
}

$currentAdmin = current_user();
$adminId = $currentAdmin !== null ? (int) $currentAdmin['id'] : null;
$actorIsMaster = $currentAdmin !== null && is_master($currentAdmin);

$tab = $_GET['tab'] ?? 'products';
$tab = in_array($tab, ['products', 'orders'], true) ? $tab : 'products';
$currentEditId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

$csrfToken = csrf_token();
$successMessage = take_flash('success');
$errorMessage = take_flash('error');

function decode_meta_json(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Meta must be valid JSON.');
    }

    if ($decoded === null) {
        return [];
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('Meta JSON must decode to an object.');
    }

    return $decoded;
}

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
                $itemIndexId = (int) ($_POST['item_index_id'] ?? 0);
                $priceCoins = (int) ($_POST['price_coins'] ?? 0);
                $metaJson = trim((string) ($_POST['meta_json'] ?? ''));
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($itemIndexId <= 0) {
                    throw new RuntimeException('Select an item from the index.');
                }

                $itemStmt = $pdo->prepare('SELECT id, name FROM item_index WHERE id = :id LIMIT 1');
                $itemStmt->execute(['id' => $itemIndexId]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

                if ($item === false) {
                    throw new RuntimeException('The selected item could not be found.');
                }

                if ($name === '') {
                    $name = (string) $item['name'];
                }

                if ($priceCoins < 0) {
                    throw new RuntimeException('Price cannot be negative.');
                }

                $metaArray = decode_meta_json($metaJson);
                $metaEncoded = json_encode($metaArray, JSON_UNESCAPED_UNICODE);
                if ($metaEncoded === false) {
                    throw new RuntimeException('Unable to encode meta data.');
                }

                $insert = $pdo->prepare('INSERT INTO shop_products (name, item_id, price_coins, is_active, item_index_id, meta) VALUES (:name, :item_id, :price, :active, :item_index_id, :meta)');
                $insert->execute([
                    'name' => $name,
                    'item_id' => (int) $item['id'],
                    'price' => $priceCoins,
                    'active' => $isActive,
                    'item_index_id' => $itemIndexId,
                    'meta' => $metaEncoded,
                ]);

                $productId = (int) $pdo->lastInsertId();

                audit_log($adminId, 'admin_shop_product_create', null, [
                    'product_id' => $productId,
                    'name' => $name,
                    'price_coins' => $priceCoins,
                    'item_index_id' => $itemIndexId,
                    'meta' => $metaArray,
                    'a_is_master' => $actorIsMaster ? 1 : 0,
                ]);

                flash('success', 'Product created successfully.');
                redirect('shop.php?tab=products');
                break;

            case 'update_product':
                $productId = (int) ($_POST['product_id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                $itemIndexId = (int) ($_POST['item_index_id'] ?? 0);
                $priceCoins = (int) ($_POST['price_coins'] ?? 0);
                $metaJson = trim((string) ($_POST['meta_json'] ?? ''));
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $select = $pdo->prepare('SELECT * FROM shop_products WHERE id = :id LIMIT 1');
                $select->execute(['id' => $productId]);
                $existing = $select->fetch(PDO::FETCH_ASSOC);

                if ($existing === false) {
                    throw new RuntimeException('Product not found.');
                }

                if ($itemIndexId <= 0) {
                    throw new RuntimeException('Select an item from the index.');
                }

                $itemStmt = $pdo->prepare('SELECT id, name FROM item_index WHERE id = :id LIMIT 1');
                $itemStmt->execute(['id' => $itemIndexId]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

                if ($item === false) {
                    throw new RuntimeException('The selected item could not be found.');
                }

                if ($name === '') {
                    $name = (string) $item['name'];
                }

                if ($priceCoins < 0) {
                    throw new RuntimeException('Price cannot be negative.');
                }

                $metaArray = decode_meta_json($metaJson);
                $metaEncoded = json_encode($metaArray, JSON_UNESCAPED_UNICODE);
                if ($metaEncoded === false) {
                    throw new RuntimeException('Unable to encode meta data.');
                }

                $update = $pdo->prepare('UPDATE shop_products SET name = :name, item_id = :item_id, price_coins = :price, is_active = :active, item_index_id = :item_index_id, meta = :meta WHERE id = :id');
                $update->execute([
                    'name' => $name,
                    'item_id' => (int) $item['id'],
                    'price' => $priceCoins,
                    'active' => $isActive,
                    'item_index_id' => $itemIndexId,
                    'meta' => $metaEncoded,
                    'id' => $productId,
                ]);

                audit_log($adminId, 'admin_shop_product_update', $existing, [
                    'id' => $productId,
                    'name' => $name,
                    'price_coins' => $priceCoins,
                    'item_index_id' => $itemIndexId,
                    'meta' => $metaArray,
                    'is_active' => $isActive,
                    'a_is_master' => $actorIsMaster ? 1 : 0,
                ]);

                flash('success', 'Product updated successfully.');
                redirect('shop.php?tab=products&edit=' . $productId);
                break;

            case 'delete_product':
                $productId = (int) ($_POST['product_id'] ?? 0);

                $select = $pdo->prepare('SELECT * FROM shop_products WHERE id = :id LIMIT 1');
                $select->execute(['id' => $productId]);
                $product = $select->fetch(PDO::FETCH_ASSOC);

                if ($product === false) {
                    throw new RuntimeException('Product not found.');
                }

                $delete = $pdo->prepare('DELETE FROM shop_products WHERE id = :id');
                $delete->execute(['id' => $productId]);

                audit_log($adminId, 'admin_shop_product_delete', $product, [
                    'product_id' => $productId,
                    'a_is_master' => $actorIsMaster ? 1 : 0,
                ]);

                flash('success', 'Product deleted.');
                redirect('shop.php?tab=products');
                break;

            case 'enqueue_order':
                $orderId = (int) ($_POST['order_id'] ?? 0);

                $orderStmt = $pdo->prepare('SELECT o.*, u.email, p.name AS product_name, p.item_index_id, p.item_id, p.meta, p.price_coins
                    FROM shop_orders o
                    INNER JOIN website_users u ON u.id = o.user_id
                    INNER JOIN shop_products p ON p.id = o.product_id
                    WHERE o.id = :id LIMIT 1');
                $orderStmt->execute(['id' => $orderId]);
                $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

                if ($order === false) {
                    throw new RuntimeException('Order not found.');
                }

                if ($order['status'] !== 'pending') {
                    throw new RuntimeException('Only pending orders can be queued.');
                }

                if (trim((string) $order['player_name']) === '') {
                    throw new RuntimeException('Order is missing a character name.');
                }

                $existingJobStmt = $pdo->prepare("SELECT id FROM rcon_jobs WHERE type = 'deliver_shop_order' AND args_json LIKE :pattern AND status IN ('queued', 'in_progress') LIMIT 1");
                $existingJobStmt->execute(['pattern' => '%"order_id":' . $orderId . '%']);
                if ($existingJobStmt->fetch()) {
                    throw new RuntimeException('A delivery job already exists for this order.');
                }

                $metaArray = [];
                if (isset($order['meta']) && $order['meta'] !== null) {
                    $decoded = json_decode((string) $order['meta'], true);
                    if (is_array($decoded)) {
                        $metaArray = $decoded;
                    }
                }

                $deliveryItemId = (int) ($order['item_index_id'] ?? 0);
                if ($deliveryItemId <= 0) {
                    $deliveryItemId = (int) $order['item_id'];
                }

                $deliveryCount = max(1, (int) ($metaArray['count'] ?? 1));

                $args = [
                    'order_id' => (int) $order['id'],
                    'player' => $order['player_name'],
                    'item_id' => $deliveryItemId,
                    'count' => $deliveryCount,
                    'inbox' => true,
                ];

                $jobStmt = $pdo->prepare('INSERT INTO rcon_jobs (type, args_json) VALUES (:type, :args)');
                $jobStmt->execute([
                    'type' => 'deliver_shop_order',
                    'args' => json_encode($args, JSON_UNESCAPED_UNICODE),
                ]);

                $jobId = (int) $pdo->lastInsertId();

                $updateOrder = $pdo->prepare('UPDATE shop_orders SET result_text = :result WHERE id = :id');
                $updateOrder->execute([
                    'result' => 'Queued in job #' . $jobId,
                    'id' => $orderId,
                ]);

                audit_log($adminId, 'admin_shop_order_enqueue', $order, [
                    'order_id' => $orderId,
                    'job_id' => $jobId,
                    'args' => $args,
                    'a_is_master' => $actorIsMaster ? 1 : 0,
                ]);

                flash('success', 'Delivery job queued (Job #' . $jobId . ').');
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

$prefillProduct = [
    'name' => '',
    'item_index_id' => '',
    'item_label' => '',
    'price_coins' => '',
    'is_active' => 1,
    'meta_json' => '{}',
];

$pickerOpen = isset($_GET['picker']) && $_GET['picker'] !== '0';
$itemSearchQuery = trim((string) ($_GET['item_search'] ?? ''));
$itemSearchResults = [];

if ($pickerOpen) {
    if ($itemSearchQuery !== '') {
        $conditions = ['name LIKE :name'];
        $params = ['name' => '%' . $itemSearchQuery . '%'];

        if (ctype_digit($itemSearchQuery)) {
            $conditions[] = 'id = :id';
            $params['id'] = (int) $itemSearchQuery;
        }

        $query = 'SELECT id, name, description FROM item_index WHERE ' . implode(' OR ', $conditions) . ' ORDER BY name ASC LIMIT 25';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $itemSearchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$useItemId = isset($_GET['use_item']) ? (int) $_GET['use_item'] : 0;
$selectedItem = null;

if ($useItemId > 0) {
    $stmt = $pdo->prepare('SELECT id, name FROM item_index WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $useItemId]);
    $useItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($useItem !== false) {
        $selectedItem = $useItem;
        $prefillProduct['item_index_id'] = (int) $useItem['id'];
        $prefillProduct['name'] = (string) $useItem['name'];
        $prefillProduct['item_label'] = '#' . (int) $useItem['id'] . ' — ' . (string) $useItem['name'];
    }
}

$productsStmt = $pdo->query('SELECT p.*, i.name AS index_name FROM shop_products p LEFT JOIN item_index i ON i.id = p.item_index_id ORDER BY p.name ASC');
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as &$product) {
    $product['meta_decoded'] = [];
    if (isset($product['meta']) && $product['meta'] !== null) {
        $decoded = json_decode((string) $product['meta'], true);
        if (is_array($decoded)) {
            $product['meta_decoded'] = $decoded;
        }
    }
}
unset($product);

$editProduct = null;
if ($tab === 'products' && $currentEditId !== null) {
    foreach ($products as $productRow) {
        if ((int) $productRow['id'] === $currentEditId) {
            $editProduct = $productRow;
            $editProduct['meta_json'] = json_encode($productRow['meta_decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($editProduct['meta_json'] === false) {
                $editProduct['meta_json'] = '{}';
            }
            break;
        }
    }
}

if ($editProduct !== null && $selectedItem !== null) {
    $editProduct['item_index_id'] = (int) $selectedItem['id'];
}

$ordersStmt = $pdo->query('SELECT o.id, o.player_name, o.created_at, o.status, o.result_text, u.email, p.name AS product_name, p.item_index_id, p.item_id, p.price_coins, p.meta
    FROM shop_orders o
    INNER JOIN website_users u ON u.id = o.user_id
    INNER JOIN shop_products p ON p.id = o.product_id
    ORDER BY o.created_at DESC
    LIMIT 200');
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

function order_status_badge(string $status): string
{
    $status = strtolower($status);
    $classes = [
        'pending' => 'admin-status',
        'delivered' => 'admin-status admin-status--ok',
        'failed' => 'admin-status admin-status--error',
        'in_progress' => 'admin-status',
    ];

    $class = $classes[$status] ?? 'admin-status';
    return '<span class="' . $class . '">' . htmlspecialchars(ucfirst($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
}
$pickerQueryBase = 'tab=products';
if ($tab === 'products' && $currentEditId !== null) {
    $pickerQueryBase .= '&edit=' . $currentEditId;
}
?>
<?php if ($pickerOpen): ?>
    <style>
        .admin-modal { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); display: flex; align-items: center; justify-content: center; z-index: 50; }
        .admin-modal__dialog { background: rgba(15, 23, 42, 0.95); border-radius: 0.75rem; padding: 1.5rem; width: min(90vw, 640px); box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6); }
        .admin-modal__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .admin-modal__body { max-height: 60vh; overflow-y: auto; }
    </style>
    <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="item-picker-title">
        <div class="admin-modal__dialog">
            <div class="admin-modal__header">
                <h3 id="item-picker-title" class="mb-0">Item Picker</h3>
                <a class="admin-button admin-button--secondary" href="shop.php?<?php echo sanitize($pickerQueryBase); ?>">Close</a>
            </div>
            <div class="admin-modal__body">
                <form class="admin-form admin-form--inline" method="get" action="shop.php">
                    <input type="hidden" name="tab" value="products">
                    <input type="hidden" name="picker" value="1">
                    <?php if ($tab === 'products' && $currentEditId !== null): ?>
                        <input type="hidden" name="edit" value="<?php echo (int) $currentEditId; ?>">
                    <?php endif; ?>
                    <div class="admin-form__group">
                        <label for="item-search">Search items</label>
                        <input type="text" id="item-search" name="item_search" value="<?php echo sanitize($itemSearchQuery); ?>" placeholder="Name or ID">
                    </div>
                    <div class="admin-form__actions">
                        <button type="submit" class="admin-button">Search</button>
                    </div>
                </form>

                <?php if ($itemSearchQuery !== '' && $itemSearchResults === []): ?>
                    <p>No items matched your search.</p>
                <?php elseif ($itemSearchResults !== []): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itemSearchResults as $item): ?>
                                <tr>
                                    <td><?php echo (int) $item['id']; ?></td>
                                    <td><?php echo sanitize((string) $item['name']); ?></td>
                                    <td><?php echo sanitize((string) ($item['description'] ?? '')); ?></td>
                                    <td><a class="admin-button admin-button--secondary" href="shop.php?<?php echo sanitize($pickerQueryBase); ?>&amp;use_item=<?php echo (int) $item['id']; ?>">Select</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<section class="admin-section admin-section--shop">
    <h2>Shop Management</h2>

    <div class="admin-tabs">
        <a class="admin-tabs__link <?php echo $tab === 'products' ? 'is-active' : ''; ?>" href="?tab=products">Products</a>
        <a class="admin-tabs__link <?php echo $tab === 'orders' ? 'is-active' : ''; ?>" href="?tab=orders">Orders</a>
    </div>

    <?php if ($errorMessage): ?>
        <div class="admin-alert admin-alert--error"><?php echo sanitize($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="admin-alert admin-alert--success"><?php echo sanitize($successMessage); ?></div>
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
                    <input type="text" id="product-name" name="name" value="<?php echo sanitize((string) $prefillProduct['name']); ?>">
                </div>

                <div class="form-group">
                    <label for="product-item-index">Item Index ID</label>
                    <input type="number" id="product-item-index" name="item_index_id" value="<?php echo sanitize((string) $prefillProduct['item_index_id']); ?>" min="1" required>
                    <div class="admin-table__meta"><?php echo $prefillProduct['item_label'] !== '' ? sanitize((string) $prefillProduct['item_label']) : 'Select via the item picker.'; ?></div>
                    <a class="admin-button admin-button--secondary" href="shop.php?tab=products&amp;picker=1">Open item picker</a>
                </div>

                <div class="form-group">
                    <label for="product-price">Price (coins)</label>
                    <input type="number" id="product-price" name="price_coins" value="<?php echo sanitize((string) $prefillProduct['price_coins']); ?>" min="0" required>
                </div>

                <div class="form-group">
                    <label for="product-meta">Meta JSON</label>
                    <textarea id="product-meta" name="meta_json" rows="4" placeholder='{"count": 100}'><?php echo sanitize((string) $prefillProduct['meta_json']); ?></textarea>
                </div>

                <div class="form-group form-group--checkbox">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo (int) $prefillProduct['is_active'] === 1 ? 'checked' : ''; ?>>
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
                <p>No products created yet.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Meta</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $productRow): ?>
                            <tr>
                                <td><?php echo sanitize((string) $productRow['name']); ?></td>
                                <td>
                                    <?php if ($productRow['item_index_id']): ?>
                                        <div>#<?php echo (int) $productRow['item_index_id']; ?></div>
                                        <?php if ($productRow['index_name']): ?>
                                            <div class="admin-table__meta"><?php echo sanitize((string) $productRow['index_name']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="admin-table__meta">n/a</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize(number_format((int) $productRow['price_coins'])); ?> coins</td>
                                <td><code><?php echo sanitize(json_encode($productRow['meta_decoded'], JSON_UNESCAPED_UNICODE)); ?></code></td>
                                <td><?php echo (int) $productRow['is_active'] === 1 ? '<span class="admin-status admin-status--ok">Active</span>' : '<span class="admin-status admin-status--error">Disabled</span>'; ?></td>
                                <td class="admin-table__actions">
                                    <a class="admin-button admin-button--secondary" href="shop.php?tab=products&amp;edit=<?php echo (int) $productRow['id']; ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this product?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="tab" value="products">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $productRow['id']; ?>">
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
                        <input type="text" id="edit-name" name="name" value="<?php echo sanitize((string) $editProduct['name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="edit-item-index">Item Index ID</label>
                        <input type="number" id="edit-item-index" name="item_index_id" value="<?php echo (int) $editProduct['item_index_id']; ?>" min="1" required>
                        <a class="admin-button admin-button--secondary" href="shop.php?tab=products&amp;edit=<?php echo (int) $editProduct['id']; ?>&amp;picker=1">Open item picker</a>
                    </div>

                    <div class="form-group">
                        <label for="edit-price">Price (coins)</label>
                        <input type="number" id="edit-price" name="price_coins" value="<?php echo (int) $editProduct['price_coins']; ?>" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="edit-meta">Meta JSON</label>
                        <textarea id="edit-meta" name="meta_json" rows="4"><?php echo sanitize((string) $editProduct['meta_json']); ?></textarea>
                    </div>

                    <div class="form-group form-group--checkbox">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php echo (int) $editProduct['is_active'] === 1 ? 'checked' : ''; ?>>
                            Active
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="admin-button">Save changes</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <section class="admin-card">
            <h3>Recent Orders</h3>
            <?php if ($orders === []): ?>
                <p>No orders recorded.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Character</th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                                $orderMeta = [];
                                if ($order['meta'] !== null) {
                                    $decoded = json_decode((string) $order['meta'], true);
                                    if (is_array($decoded)) {
                                        $orderMeta = $decoded;
                                    }
                                }
                                $orderCount = (int) ($orderMeta['count'] ?? 1);
                            ?>
                            <tr>
                                <td><?php echo (int) $order['id']; ?></td>
                                <td><?php echo sanitize((string) $order['email']); ?></td>
                                <td><?php echo sanitize((string) $order['player_name']); ?></td>
                                <td>
                                    <?php echo sanitize((string) $order['product_name']); ?>
                                    <div class="admin-table__meta">Item ID: <?php echo (int) ($order['item_index_id'] ?? $order['item_id']); ?></div>
                                    <div class="admin-table__meta">Count: <?php echo $orderCount; ?></div>
                                    <?php if ($order['result_text']): ?>
                                        <div class="admin-table__meta"><?php echo sanitize((string) $order['result_text']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize(number_format((int) $order['price_coins'])); ?> coins</td>
                                <td><?php echo order_status_badge((string) $order['status']); ?></td>
                                <td><?php echo sanitize((string) $order['created_at']); ?></td>
                                <td class="admin-table__actions">
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <form method="post">
                                            <input type="hidden" name="action" value="enqueue_order">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <input type="hidden" name="tab" value="orders">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <button type="submit" class="admin-button">Enqueue Delivery</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="admin-table__meta">No actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/partials/footer.php';
