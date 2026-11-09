
<?php

require_once __DIR__ . '/../../app/middleware/auth.php';
requireAdmin();

$pdo = require __DIR__ . '/../../app/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$allowedStatuses = [
    'Pending' => ['Confirmed', 'Cancelled'],
    'Confirmed' => ['Preparing', 'Cancelled'],
    'Preparing' => ['Shipped'],
    'Shipped' => ['Completed'],
    'Completed' => [],
    'Cancelled' => []
];

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $message = '';

    $orderId = filter_var(
        $_POST['order_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['admin_csrf'], $_POST['csrf_token'])
    ) {
        $message = 'Invalid form submission. Please refresh the page.';
        $_SESSION['admin_order_message'] = $message;

        header('Location: orders.php' . ($orderId ? '?view=' . $orderId : ''));
        exit;
    }

    $newStatus = $_POST['status'] ?? '';

    if (!$orderId || $orderId < 1) {
        $message = 'Invalid order.';
    } else {

        try {
            $pdo->beginTransaction();

            // Lock the order to prevent simultaneous updates
            $stmt = $pdo->prepare("
                SELECT id, status
                FROM orders
                WHERE id = :id
                FOR UPDATE
            ");

            $stmt->execute(['id' => $orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $currentStatus = $order['status'];

            if (!in_array(
                $newStatus,
                $allowedStatuses[$currentStatus] ?? [],
                true
            )) {
                throw new RuntimeException(
                    'This order status change is not allowed.'
                );
            }

            // Restore stock when cancelling an eligible order
            if ($newStatus === 'Cancelled') {

                $itemsStmt = $pdo->prepare("
                    SELECT product_id, quantity
                    FROM order_items
                    WHERE order_id = :order_id
                ");

                $itemsStmt->execute(['order_id' => $orderId]);
                $items = $itemsStmt->fetchAll();

                $restoreStock = $pdo->prepare("
                    UPDATE products
                    SET stock = stock + :quantity
                    WHERE id = :product_id
                ");

                foreach ($items as $item) {
                    $restoreStock->execute([
                        'quantity' => $item['quantity'],
                        'product_id' => $item['product_id']
                    ]);
                }
            }

            $update = $pdo->prepare("
                UPDATE orders
                SET status = :status
                WHERE id = :id
            ");

            $update->execute([
                'status' => $newStatus,
                'id' => $orderId
            ]);

            $pdo->commit();

            $message = 'Order status updated to ' . $newStatus . '.';

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($exception->getMessage());
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Unable to update the order. Please try again.';
        }
    }

    $_SESSION['admin_order_message'] = $message;

    header('Location: orders.php?view=' . $orderId);
    exit;
}

// Display one-time messages
$flashMessage = $_SESSION['admin_order_message'] ?? '';
unset($_SESSION['admin_order_message']);

// Search and filter
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

$sql = "
    SELECT *
    FROM orders
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            order_number LIKE :search
            OR customer_name LIKE :search_name
            OR customer_email LIKE :search_email
        )
    ";

    $params['search'] = "%$search%";
    $params['search_name'] = "%$search%";
    $params['search_email'] = "%$search%";
}

$validFilters = [
    'Pending',
    'Confirmed',
    'Preparing',
    'Shipped',
    'Completed',
    'Cancelled'
];

if (in_array($statusFilter, $validFilters, true)) {
    $sql .= " AND status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY created_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Load order details when an order is selected
$viewOrderId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT);
$viewOrder = null;
$orderItems = [];

if ($viewOrderId) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM orders
        WHERE id = :id
    ");

    $stmt->execute(['id' => $viewOrderId]);
    $viewOrder = $stmt->fetch();

    if ($viewOrder) {
        $itemsStmt = $pdo->prepare("
            SELECT *
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
        ");

        $itemsStmt->execute(['order_id' => $viewOrderId]);
        $orderItems = $itemsStmt->fetchAll();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <title>Orders | CubeTech Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">

    <aside class="admin-sidebar" id="adminSidebar">

    <div class="admin-sidebar-header">
        <h2>CubeTech</h2>

        <button type="button" class="admin-menu-toggle"
            id="adminMenuToggle"
            aria-label="Toggle navigation"
            aria-expanded="false">
            ☰
        </button>
    </div>

    <div class="admin-sidebar-content" id="adminSidebarContent">

        <p class="admin-sidebar-label">ADMIN PANEL</p>

        <nav class="admin-nav">
            <a href="index.php" class="active">Dashboard</a>
            <a href="products.php">Products</a>
            <a href="categories.php">Categories</a>
            <a href="customers.php">Customers</a>
            <a href="orders.php">Orders</a>
        </nav>

        <div class="admin-sidebar-bottom">
            <a href="../index.php">View Store</a>
            <a href="logout.php">Logout</a>
        </div>

    </div>

</aside>

    <main class="admin-main">

        <?php if ($viewOrder): ?>

            <header class="admin-header">
                <div>
                    <h1>Order Details</h1>
                    <p><?= e($viewOrder['order_number']) ?></p>
                </div>

                <a href="orders.php" class="admin-reset-button">
                    Back to Orders
                </a>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="admin-success-message">
                    <?= e($flashMessage) ?>
                </div>
            <?php endif; ?>

            <section class="admin-order-detail-grid">

                <div class="admin-order-card">
                    <h2>Customer Information</h2>

                    <p><strong>Name:</strong> <?= e($viewOrder['customer_name']) ?></p>
                    <p><strong>Email:</strong> <?= e($viewOrder['customer_email']) ?></p>
                    <p><strong>Contact:</strong> <?= e($viewOrder['contact_number']) ?></p>
                    <p><strong>Address:</strong> <?= nl2br(e($viewOrder['delivery_address'])) ?></p>
                </div>

                <div class="admin-order-card">
                    <h2>Order Information</h2>

                    <p>
                        <strong>Order Number:</strong>
                        <?= e($viewOrder['order_number']) ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?= date('M d, Y h:i A', strtotime($viewOrder['created_at'])) ?>
                    </p>

                    <p>
                        <strong>Payment Method:</strong>
                        <?= e($viewOrder['payment_method']) ?>
                    </p>

                    <p>
                        <strong>Status:</strong>
                        <span class="admin-status <?= strtolower(e($viewOrder['status'])) ?>">
                            <?= e($viewOrder['status']) ?>
                        </span>
                    </p>

                    <p>
                        <strong>Notes:</strong>
                        <?= $viewOrder['notes'] !== null && $viewOrder['notes'] !== ''
                            ? nl2br(e($viewOrder['notes']))
                            : 'No notes' ?>
                    </p>
                </div>

            </section>

            <section class="admin-order-card admin-order-items">
                <h2>Purchased Items</h2>

                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td><?= e($item['product_name']) ?></td>
                                    <td>₱<?= number_format((float) $item['unit_price'], 2) ?></td>
                                    <td><?= (int) $item['quantity'] ?></td>
                                    <td>₱<?= number_format((float) $item['line_total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-order-total">
                    <p>Subtotal: <strong>₱<?= number_format((float) $viewOrder['subtotal'], 2) ?></strong></p>
                    <h3>Total: ₱<?= number_format((float) $viewOrder['total'], 2) ?></h3>
                </div>
            </section>

            <section class="admin-order-card admin-order-update">
                <h2>Update Order Status</h2>

                <?php
                $nextStatuses = $allowedStatuses[$viewOrder['status']] ?? [];
                ?>

                <?php if (!empty($nextStatuses)): ?>

                    <form method="POST" class="admin-order-status-form">

                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_csrf']) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $viewOrder['id'] ?>">

                        <select name="status" required>
                            <option value="">Select next status</option>

                            <?php foreach ($nextStatuses as $nextStatus): ?>
                                <option value="<?= e($nextStatus) ?>">
                                    <?= e($nextStatus) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="admin-primary-button">
                            Update Status
                        </button>

                    </form>

                    <?php if (in_array($viewOrder['status'], ['Pending', 'Confirmed'], true)): ?>
                        <p class="admin-cancel-note">
                            Cancelling this order will restore its purchased quantities to product inventory.
                        </p>
                    <?php endif; ?>

                <?php else: ?>

                    <p class="admin-final-status">
                        This order is <?= e($viewOrder['status']) ?> and cannot be changed.
                    </p>

                <?php endif; ?>

            </section>

        <?php else: ?>

            <header class="admin-header">
                <div>
                    <h1>Orders</h1>
                    <p>View and manage customer orders.</p>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="admin-success-message">
                    <?= e($flashMessage) ?>
                </div>
            <?php endif; ?>

            <form method="GET" class="admin-filter-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search order number, customer, or email..."
                    value="<?= e($search) ?>"
                >

                <select name="status">
                    <option value="all">All Statuses</option>

                    <?php foreach ($validFilters as $filter): ?>
                        <option
                            value="<?= e($filter) ?>"
                            <?= $statusFilter === $filter ? 'selected' : '' ?>
                        >
                            <?= e($filter) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="admin-filter-button">
                    Filter
                </button>

                <a href="orders.php" class="admin-reset-button">
                    Reset
                </a>

            </form>

            <div class="admin-table-container">

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($orders)): ?>

                            <tr>
                                <td colspan="6" class="admin-empty">
                                    No orders found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($order['order_number']) ?></strong>
                                    </td>

                                    <td>
                                        <?= e($order['customer_name']) ?>
                                        <br>
                                        <small><?= e($order['customer_email']) ?></small>
                                    </td>

                                    <td>
                                        <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                    </td>

                                    <td>
                                        ₱<?= number_format((float) $order['total'], 2) ?>
                                    </td>

                                    <td>
                                        <span class="admin-status <?= strtolower(e($order['status'])) ?>">
                                            <?= e($order['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="orders.php?view=<?= (int) $order['id'] ?>"
                                            class="admin-edit-link"
                                        >
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

            <p class="admin-result-count">
                Showing <?= count($orders) ?> order(s)
            </p>

        <?php endif; ?>

    </main>

</div>


<script src="../assets/js/main.js"></script>
</body>
</html>
