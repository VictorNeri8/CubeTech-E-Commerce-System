
<?php

session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

$orderNumber = $_GET['order'] ?? '';
$lastOrderNumber = $_SESSION['last_order_number'] ?? '';

if (
    $orderNumber === '' ||
    $lastOrderNumber === '' ||
    !hash_equals($lastOrderNumber, $orderNumber)
) {
    http_response_code(404);
    exit('Order confirmation not found.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE order_number = :order_number
    LIMIT 1
");

$stmt->execute(['order_number' => $orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemStmt = $pdo->prepare("
    SELECT product_name, unit_price, quantity, line_total
    FROM order_items
    WHERE order_id = :order_id
");

$itemStmt->execute(['order_id' => $order['id']]);
$items = $itemStmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <title>Order Confirmation | CubeTech</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- <header class="site-header">
    <div class="container nav-container">
        <a href="index.php" class="logo">CubeTech</a>

        <nav>
            <a href="index.php">Home</a>
            <a href="index.php">Products</a>
        </nav>
    </div>
</header> -->

<main class="container confirmation-page">

    <div class="confirmation-card">

        <div class="confirmation-icon">&#10003;</div>

        <h1>Order Placed Successfully!</h1>

        <p class="confirmation-message">
            Thank you for your purchase. Your order has been received.
        </p>

        <div class="confirmation-order-number">
            <span>Order Number</span>
            <strong><?= htmlspecialchars($order['order_number']) ?></strong>
        </div>

        <div class="confirmation-status">
            <span>Order Status</span>
            <strong><?= htmlspecialchars($order['status']) ?></strong>
        </div>

        <div class="confirmation-details">
            <h2>Customer Details</h2>

            <p>
                <strong>Name:</strong>
                <?= htmlspecialchars($order['customer_name']) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= htmlspecialchars($order['customer_email']) ?>
            </p>

            <p>
                <strong>Contact:</strong>
                <?= htmlspecialchars($order['contact_number']) ?>
            </p>

            <p>
                <strong>Delivery Address:</strong>
                <?= nl2br(htmlspecialchars($order['delivery_address'])) ?>
            </p>

            <p>
                <strong>Payment Method:</strong>
                <?= htmlspecialchars($order['payment_method']) ?>
            </p>
        </div>

        <div class="confirmation-items">
            <h2>Order Items</h2>

            <?php foreach ($items as $item): ?>
                <div class="confirmation-item">
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

        <div class="confirmation-total">
            <span>Total</span>
            <strong>
                ₱<?= number_format((float) $order['total'], 2) ?>
            </strong>
        </div>

        <a href="index.php" class="shop-button">
            Continue Shopping
        </a>

    </div>

</main>

<!-- <footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> CubeTech. All rights reserved.</p>
    </div>
</footer> -->

</body>
</html>
