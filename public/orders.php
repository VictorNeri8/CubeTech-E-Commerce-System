
<?php
session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

// Require customer login.
if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (int) $_SESSION['customer_id'];

// Get this customer's orders.
$orderStmt = $pdo->prepare("
        SELECT
        id,
        order_number,
        customer_name,
        customer_email,
        contact_number,
        delivery_address,
        payment_method,
        notes,
        subtotal,
        total,
        status,
        created_at
    FROM orders
    WHERE customer_id = ?
    ORDER BY created_at DESC
");

$orderStmt->execute([$customerId]);
$orders = $orderStmt->fetchAll();

// Get purchased items belonging to this customer's orders.
$itemStmt = $pdo->prepare("
    SELECT
        oi.order_id,
        oi.product_name,
        oi.unit_price,
        oi.quantity,
        oi.line_total
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE o.customer_id = ?
    ORDER BY oi.id ASC
");

$itemStmt->execute([$customerId]);
$items = $itemStmt->fetchAll();

$itemsByOrder = [];

foreach ($items as $item) {
    $itemsByOrder[$item['order_id']][] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <title>My Orders | CubeTech</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-content">

        <a href="index.php" class="logo">
    <img src="assets/images/favicon.svg" alt="CubeTech" class="logo-icon">
    <span>CubeTech</span>
</a>

       

        <div class="nav-actions">

            <a href="cart.php" class="cart-link">
                Cart
            </a>

            <div class="account-menu">

                <button
                    type="button"
                    class="account-menu-toggle"
                    id="accountMenuToggle"
                    aria-expanded="false"
                    aria-controls="accountDropdown"
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="22"
                        height="22"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                    </svg>

                    <span>
                        <?= !empty($_SESSION['customer_id'])
                            ? htmlspecialchars($_SESSION['customer_name'])
                            : 'Login' ?>
                    </span>
                </button>

                <div class="account-dropdown" id="accountDropdown">

                    <?php if (!empty($_SESSION['customer_id'])): ?>

                        <a href="account.php">My Account</a>
                        <a href="cart.php">My Cart</a>
                        <a href="orders.php">My Orders</a>
                        <a href="logout.php" class="logout-link">Log Out</a>

                    <?php else: ?>

                        <a href="login.php">Log In</a>
                        <a href="register.php">Create Account</a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>
</header>

<main class="container orders-page">

    <div class="orders-heading">
        <h1>My Orders</h1>
        <p>View your order history and track your purchases.</p>
    </div>

    <?php if (empty($orders)): ?>

        <div class="orders-empty">
            <h2>No orders yet</h2>
            <p>You haven't placed any orders.</p>
            <a href="index.php" class="btn-primary">Start Shopping</a>
        </div>

    <?php else: ?>

        <div class="orders-list">

            <?php foreach ($orders as $order): ?>

                <section class="order-card">

                    <div class="order-card-header">

                        <div>
                            <span class="order-label">Order Number</span>
                            <h2>
                                <?= htmlspecialchars($order['order_number']) ?>
                            </h2>
                            <p>
                                <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?>
                            </p>
                        </div>

                        <span class="order-status status-<?= strtolower($order['status']) ?>">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>

                    </div>
                     <?php if ($order['status'] === 'Cancelled'): ?>

                        <div class="order-progress cancelled-progress">
                            <span class="progress-cancelled">
                                This order has been cancelled.
                            </span>
                        </div>

                    <?php else: ?>

                        <?php
                        $statusSteps = [
                            'Pending',
                            'Confirmed',
                            'Preparing',
                            'Shipped',
                            'Completed'
                        ];

                        $currentStep = array_search(
                            $order['status'],
                            $statusSteps,
                            true
                        );
                        ?>

                        <div class="order-progress">

                            <?php foreach ($statusSteps as $index => $step): ?>

                                <div class="progress-step <?= $index <= $currentStep ? 'completed' : '' ?>">

                                    <div class="progress-circle">
                                        <?= $index < $currentStep ? '✓' : $index + 1 ?>
                                    </div>

                                    <span><?= $step ?></span>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>




                    <div class="order-items">

                        <?php foreach ($itemsByOrder[$order['id']] ?? [] as $item): ?>

                            <div class="order-item">

                                <div>
                                    <strong>
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </strong>
                                    <p>
                                        <?= (int) $item['quantity'] ?> ×
                                        ₱<?= number_format((float) $item['unit_price'], 2) ?>
                                    </p>
                                </div>

                                <strong>
                                    ₱<?= number_format((float) $item['line_total'], 2) ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    </div>

                                        <details class="order-details">

                        <summary>View Order Details</summary>

                        <div class="order-details-content">

                            <div>
                                <span>Customer Name</span>
                                <strong>
                                    <?= htmlspecialchars($order['customer_name']) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Email Address</span>
                                <strong>
                                    <?= htmlspecialchars($order['customer_email']) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Contact Number</span>
                                <strong>
                                    <?= htmlspecialchars($order['contact_number']) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Delivery Address</span>
                                <strong>
                                    <?= nl2br(htmlspecialchars($order['delivery_address'])) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Payment Method</span>
                                <strong>
                                    <?= htmlspecialchars($order['payment_method']) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Order Notes</span>
                                <strong>
                                    <?= !empty($order['notes'])
                                        ? nl2br(htmlspecialchars($order['notes']))
                                        : 'No additional notes' ?>
                                </strong>
                            </div>

                        </div>

                    </details>

                    <div class="order-card-footer">

                        <div>
                            <span>Payment Method</span>
                            <strong>
                                <?= htmlspecialchars($order['payment_method']) ?>
                            </strong>
                        </div>

                        <div class="order-total">
                            <span>Total</span>
                            <strong>
                                ₱<?= number_format((float) $order['total'], 2) ?>
                            </strong>
                        </div>

                    </div>

                </section>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<script src="assets/js/main.js"></script>

</body>
</html>
