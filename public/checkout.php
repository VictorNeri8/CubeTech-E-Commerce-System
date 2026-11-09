<?php

session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}


// Load saved details for logged-in customers.
$customerId = null;
$customerDefaults = [];

if (!empty($_SESSION['customer_id'])) {
    $stmt = $pdo->prepare("
        SELECT id, name, email, contact_number, delivery_address
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([(int) $_SESSION['customer_id']]);
    $savedCustomer = $stmt->fetch();

    if ($savedCustomer) {
        $customerId = (int) $savedCustomer['id'];

        $customerDefaults = [
            'customer_name' => $savedCustomer['name'],
            'customer_email' => $savedCustomer['email'],
            'contact_number' => $savedCustomer['contact_number'],
            'delivery_address' => $savedCustomer['delivery_address']
        ];
    } else {
        unset(
            $_SESSION['customer_id'],
            $_SESSION['customer_name']
        );
    }
}


function checkoutRedirect(): void
{
    header('Location: checkout.php');
    exit;
}

function checkoutMessage(string $message, string $type = 'error'): void
{
    $_SESSION['checkout_message'] = [
        'text' => $message,
        'type' => $type
    ];
}

function checkoutMoney(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

// Create a CSRF token
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

// Identify the current cart contents
$cartSignature = hash('sha256', serialize($_SESSION['cart']));

$productIds = array_map('intval', array_keys($_SESSION['cart']));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

// Load current product information
$stmt = $pdo->prepare("
    SELECT id, name, price, stock, image, is_active
    FROM products
    WHERE id IN ($placeholders)
");

$stmt->execute($productIds);
$products = $stmt->fetchAll();

$productsById = [];

foreach ($products as $product) {
    $productsById[(int) $product['id']] = $product;
}

// Initialize or refresh the checkout price snapshot
// when the cart contents or quantities change.
if (
    !isset($_SESSION['checkout_snapshot']) ||
    ($_SESSION['checkout_cart_signature'] ?? '') !== $cartSignature
) {
    $_SESSION['checkout_snapshot'] = [];

    foreach ($productsById as $id => $product) {
        $_SESSION['checkout_snapshot'][$id] = checkoutMoney(
            (float) $product['price']
        );
    }

    $_SESSION['checkout_cart_signature'] = $cartSignature;
}

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !hash_equals(
            $_SESSION['checkout_token'],
            $_POST['checkout_token'] ?? ''
        )
    ) {
        checkoutMessage('Invalid checkout request. Please try again.');
        checkoutRedirect();
    }

    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $errors = [];

    if ($customerName === '' || strlen($customerName) > 150) {
        $errors[] = 'Enter a valid customer name.';
    }

    if (
        $customerEmail === '' ||
        !filter_var($customerEmail, FILTER_VALIDATE_EMAIL) ||
        strlen($customerEmail) > 150
    ) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($contactNumber === '' || strlen($contactNumber) > 30) {
        $errors[] = 'Enter a valid contact number.';
    }

    if ($deliveryAddress === '') {
        $errors[] = 'Delivery address is required.';
    }

    $allowedPayments = ['COD', 'E-Wallet', 'Bank Transfer'];

    if (!in_array($paymentMethod, $allowedPayments, true)) {
        $errors[] = 'Select a valid payment method.';
    }

    if (strlen($notes) > 5000) {
        $errors[] = 'Order notes are too long.';
    }

    if (!empty($errors)) {
        $_SESSION['checkout_errors'] = $errors;
        $_SESSION['checkout_old'] = $_POST;
        checkoutRedirect();
    }

    try {
        $pdo->beginTransaction();

        // Lock product records to prevent simultaneous orders
        // from selling more stock than is available.
        $lockStmt = $pdo->prepare("
            SELECT id, name, price, stock, is_active
            FROM products
            WHERE id IN ($placeholders)
            FOR UPDATE
        ");

        $lockStmt->execute($productIds);
        $lockedProducts = $lockStmt->fetchAll();

        $lockedById = [];

        foreach ($lockedProducts as $product) {
            $lockedById[(int) $product['id']] = $product;
        }

        $currentSubtotal = 0;
        $priceChanged = false;
        $newSnapshot = [];

        foreach ($_SESSION['cart'] as $id => $quantity) {
            $id = (int) $id;
            $quantity = (int) $quantity;

            if (!isset($lockedById[$id])) {
                throw new RuntimeException(
                    'A product in your cart is no longer available.'
                );
            }

            $product = $lockedById[$id];

            if ((int) $product['is_active'] !== 1) {
                throw new RuntimeException(
                    $product['name'] . ' is no longer available.'
                );
            }

            if ($quantity < 1 || $quantity > (int) $product['stock']) {
                throw new RuntimeException(
                    'Insufficient stock for ' . $product['name'] .
                    '. Available stock: ' . $product['stock'] . '.'
                );
            }

            $currentPrice = checkoutMoney((float) $product['price']);

            $oldPrice = $_SESSION['checkout_snapshot'][$id] ?? null;

            if ($oldPrice !== $currentPrice) {
                $priceChanged = true;
            }

            $newSnapshot[$id] = $currentPrice;

            $currentSubtotal +=
                (float) $currentPrice * $quantity;
        }

        // If any price changed, stop and require customer review.
        if ($priceChanged) {
            $pdo->rollBack();

            $_SESSION['checkout_snapshot'] = $newSnapshot;
            $_SESSION['checkout_cart_signature'] = $cartSignature;

            $_SESSION['checkout_old'] = $_POST;

            checkoutMessage(
                'One or more product prices have changed. ' .
                'Please review the updated order total before placing your order.',
                'warning'
            );

            checkoutRedirect();
        }

        $currentSubtotal = round($currentSubtotal, 2);

        // Generate a unique order number
        $orderNumber = 'CT-' . date('Ymd') . '-' .
            strtoupper(bin2hex(random_bytes(3)));

        // Save the order
        $orderStmt = $pdo->prepare("
           
INSERT INTO orders (
    customer_id,
    order_number,
    customer_name,
    customer_email,
    contact_number,
    delivery_address,
    payment_method,
    notes,
    subtotal,
    total,
    status
) VALUES (
    :customer_id,
    :order_number,
    :customer_name,
    :customer_email,
    :contact_number,
    :delivery_address,
    :payment_method,
    :notes,
    :subtotal,
    :total,
    'Pending'
)

        ");

        $orderStmt->execute([
    'customer_id' => $customerId,
    'order_number' => $orderNumber,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'contact_number' => $contactNumber,
    'delivery_address' => $deliveryAddress,
    'payment_method' => $paymentMethod,
    'notes' => $notes !== '' ? $notes : null,
    'subtotal' => $currentSubtotal,
    'total' => $currentSubtotal
]);

        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id,
                product_id,
                product_name,
                unit_price,
                quantity,
                line_total
            ) VALUES (
                :order_id,
                :product_id,
                :product_name,
                :unit_price,
                :quantity,
                :line_total
            )
        ");

        $stockStmt = $pdo->prepare("
            UPDATE products
            SET stock = stock - :quantity
            WHERE id = :id
            AND is_active = 1
            AND stock >= :required_stock
        ");

        foreach ($_SESSION['cart'] as $id => $quantity) {
            $id = (int) $id;
            $quantity = (int) $quantity;

            $product = $lockedById[$id];
            $unitPrice = (float) $product['price'];
            $lineTotal = round($unitPrice * $quantity, 2);

            $itemStmt->execute([
                'order_id' => $orderId,
                'product_id' => $id,
                'product_name' => $product['name'],
                'unit_price' => checkoutMoney($unitPrice),
                'quantity' => $quantity,
                'line_total' => checkoutMoney($lineTotal)
            ]);

            $stockStmt->execute([
                'quantity' => $quantity,
                'id' => $id,
                'required_stock' => $quantity
            ]);

            if ($stockStmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Stock could not be updated. Please try again.'
                );
            }
        }

        $pdo->commit();

        // Clear cart and checkout information only after success.
        $_SESSION['cart'] = [];
        unset(
            $_SESSION['checkout_snapshot'],
            $_SESSION['checkout_cart_signature'],
            $_SESSION['checkout_old'],
            $_SESSION['checkout_errors'],
            $_SESSION['checkout_message']
        );

        $_SESSION['checkout_token'] = bin2hex(random_bytes(32));

        $_SESSION['last_order_number'] = $orderNumber;
        header('Location: confirmation.php?order=' . urlencode($orderNumber));
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        $_SESSION['checkout_old'] = $_POST;

        checkoutMessage(
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to place your order. Please try again.'
        );

        checkoutRedirect();
    }
}

// Prepare checkout display
$cartItems = [];
$subtotal = 0;
$hasUnavailableItems = false;

foreach ($_SESSION['cart'] as $id => $quantity) {
    $id = (int) $id;
    $quantity = (int) $quantity;

    if (!isset($productsById[$id])) {
        $hasUnavailableItems = true;
        continue;
    }

    $product = $productsById[$id];

    $available =
        (int) $product['is_active'] === 1 &&
        (int) $product['stock'] >= $quantity &&
        (int) $product['stock'] > 0;

    if (!$available) {
        $hasUnavailableItems = true;
    }

    $lineTotal = round((float) $product['price'] * $quantity, 2);
    $subtotal += $lineTotal;

    $cartItems[] = [
        'product' => $product,
        'quantity' => $quantity,
        'line_total' => $lineTotal,
        'available' => $available
    ];
}

$subtotal = round($subtotal, 2);

$message = $_SESSION['checkout_message'] ?? null;
$errors = $_SESSION['checkout_errors'] ?? [];
$old = array_merge(
    $customerDefaults,
    $_SESSION['checkout_old'] ?? []
);

unset(
    $_SESSION['checkout_message'],
    $_SESSION['checkout_errors'],
    $_SESSION['checkout_old']
);

function checkoutValue(string $field, array $old): string
{
    return htmlspecialchars($old[$field] ?? '', ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <title>Checkout | CubeTech</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>



<main class="container checkout-page">

    <div class="checkout-heading">
        <h1>Checkout</h1>
        <p>Enter your details to complete your order.</p>
    </div>

    <?php if ($message): ?>
        <div class="cart-message <?= htmlspecialchars($message['type']) ?>">
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="cart-message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($hasUnavailableItems): ?>
        <div class="cart-message error">
            Some products are unavailable or have insufficient stock.
            Please return to your cart and update it before continuing.
            <a href="cart.php">Return to cart</a>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">

        <section class="checkout-form-section">
            <h2>Customer Information</h2>

            <form method="POST" action="checkout.php" class="checkout-form">

                <input
                    type="hidden"
                    name="checkout_token"
                    value="<?= htmlspecialchars($_SESSION['checkout_token']) ?>"
                >

                <div class="form-group">
                    <label for="customer_name">Full Name *</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        maxlength="150"
                        value="<?= checkoutValue('customer_name', $old) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="customer_email">Email Address *</label>
                    <input
                        type="email"
                        id="customer_email"
                        name="customer_email"
                        maxlength="150"
                        value="<?= checkoutValue('customer_email', $old) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="contact_number">Contact Number *</label>
                    <input
                        type="tel"
                        id="contact_number"
                        name="contact_number"
                        maxlength="30"
                        value="<?= checkoutValue('contact_number', $old) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="delivery_address">Delivery Address *</label>
                    <textarea
                        id="delivery_address"
                        name="delivery_address"
                        rows="4"
                        required
                    ><?= checkoutValue('delivery_address', $old) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select payment method</option>

                        <?php
                        $paymentOptions = [
                            'COD' => 'Cash on Delivery',
                            'E-Wallet' => 'E-Wallet',
                            'Bank Transfer' => 'Bank Transfer'
                        ];
                        ?>

                        <?php foreach ($paymentOptions as $value => $label): ?>
                            <option
                                value="<?= htmlspecialchars($value) ?>"
                                <?= ($old['payment_method'] ?? '') === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Order Notes (Optional)</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        maxlength="5000"
                    ><?= checkoutValue('notes', $old) ?></textarea>
                </div>

                <button
                    type="submit"
                    class="place-order-button"
                    <?= $hasUnavailableItems ? 'disabled' : '' ?>
                >
                    Place Order
                </button>

            </form>
        </section>

        <aside class="checkout-summary">
            <h2>Order Summary</h2>

            <?php foreach ($cartItems as $item): ?>
                <div class="checkout-summary-item">
                    <div>
                        <strong>
                            <?= htmlspecialchars($item['product']['name']) ?>
                        </strong>
                        <p>Quantity: <?= $item['quantity'] ?></p>

                        <?php if (!$item['available']): ?>
                            <small class="unavailable-text">
                                Unavailable or insufficient stock
                            </small>
                        <?php endif; ?>
                    </div>

                    <span>
                        ₱<?= number_format($item['line_total'], 2) ?>
                    </span>
                </div>
            <?php endforeach; ?>

            <div class="summary-row">
                <span>Subtotal</span>
                <span>₱<?= number_format($subtotal, 2) ?></span>
            </div>

            <div class="summary-row summary-total">
                <span>Total</span>
                <span>₱<?= number_format($subtotal, 2) ?></span>
            </div>

            <a href="cart.php" class="checkout-back-link">
                Back to Cart
            </a>
        </aside>

    </div>

</main>


</body>
</html>