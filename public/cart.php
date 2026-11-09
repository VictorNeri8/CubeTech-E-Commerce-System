
<?php

session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function redirectToCart(): void
{
    header('Location: cart.php');
    exit;
}

function redirectToProduct(int $productId): void
{
    header('Location: product-details.php?id=' . $productId);
    exit;
}

function setCartMessage(string $message, string $type = 'success'): void
{
    $_SESSION['cart_message'] = [
        'text' => $message,
        'type' => $type
    ];
}

function getActiveProduct(PDO $pdo, int $productId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, name, price, stock, image, is_active
        FROM products
        WHERE id = :id AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute(['id' => $productId]);

    $product = $stmt->fetch();

    return $product ?: null;
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
    $productId = filter_var(
        $_POST['product_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $quantity = filter_var(
        $_POST['quantity'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$productId) {
        setCartMessage('Invalid product.', 'error');
        redirectToCart();
    }

    if (!$quantity || $quantity < 1) {
        setCartMessage('Please enter a valid quantity.', 'error');
        redirectToProduct($productId);
    }

    $product = getActiveProduct($pdo, $productId);

    if (!$product) {
        setCartMessage('This product is unavailable.', 'error');
        redirectToProduct($productId);
    }

    if ($product['stock'] < 1) {
        setCartMessage('This product is out of stock.', 'error');
        redirectToProduct($productId);
    }

    $currentQuantity = $_SESSION['cart'][$productId] ?? 0;
    $newQuantity = $currentQuantity + $quantity;

    if ($newQuantity > $product['stock']) {
        setCartMessage(
            'Only ' . $product['stock'] . ' item(s) are available.',
            'error'
        );
        redirectToProduct($productId);
    }

    $_SESSION['cart'][$productId] = $newQuantity;

    setCartMessage('Product added to your cart.');
    redirectToCart();
}



    
    if ($action === 'update') {
        $productId = filter_var(
            $_POST['product_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $_POST['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            !$productId ||
            !isset($_SESSION['cart'][$productId]) ||
            !$quantity ||
            $quantity < 1
        ) {
            setCartMessage('Please enter a valid quantity.', 'error');
            redirectToCart();
        }

        $product = getActiveProduct($pdo, $productId);

        if (!$product) {
            setCartMessage('This product is no longer available.', 'error');
            redirectToCart();
        }

        if ($quantity > $product['stock']) {
            setCartMessage(
                'Only ' . $product['stock'] . ' item(s) are available.',
                'error'
            );
            redirectToCart();
        }

        $_SESSION['cart'][$productId] = $quantity;

        setCartMessage('Cart quantity updated.');
        redirectToCart();
    }

    if ($action === 'remove') {
        $productId = filter_var(
            $_POST['product_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($productId && isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
            setCartMessage('Product removed from your cart.');
        }

        redirectToCart();
    }
}

// Get flash message
$message = $_SESSION['cart_message'] ?? null;
unset($_SESSION['cart_message']);

// Load current product information from the database
$cartItems = [];
$subtotal = 0;
$hasUnavailableItems = false;

if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id, name, price, stock, image, is_active
        FROM products
        WHERE id IN ($placeholders)
    ");

    $stmt->execute($productIds);

    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $productId = (int) $product['id'];
        $quantity = (int) $_SESSION['cart'][$productId];

        $isAvailable =
            (int) $product['is_active'] === 1 &&
            (int) $product['stock'] >= $quantity &&
            (int) $product['stock'] > 0;

        if (!$isAvailable) {
            $hasUnavailableItems = true;
        }

        $lineTotal = (float) $product['price'] * $quantity;
        $subtotal += $lineTotal;

        $cartItems[] = [
            'product' => $product,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
            'available' => $isAvailable
        ];
    }

    // Remove products that no longer exist in the database
    $foundIds = array_map(
        fn($item) => (int) $item['id'],
        $products
    );

    foreach ($productIds as $productId) {
        if (!in_array((int) $productId, $foundIds, true)) {
            unset($_SESSION['cart'][$productId]);
        }
    }
}

$cartCount = array_sum($_SESSION['cart']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <title>Shopping Cart | CubeTech</title>
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


<main class="container cart-page">

    <div class="cart-heading">
        <div>
            <h1>Shopping Cart</h1>
            <p>Review and manage your selected products.</p>
        </div>

        <a href="index.php" class="continue-shopping">
            Continue Shopping
        </a>
    </div>

    <?php if ($message): ?>
        <div class="cart-message <?= htmlspecialchars($message['type']) ?>">
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>

        <div class="empty-cart">
            <h2>Your cart is empty</h2>
            <p>You haven't added any products yet.</p>
            <a href="index.php" class="shop-button">Browse Products</a>
        </div>

    <?php else: ?>

        <?php if ($hasUnavailableItems): ?>
            <div class="cart-message error">
                Some items are unavailable or have insufficient stock.
                Please update or remove them before checkout.
            </div>
        <?php endif; ?>

        <div class="cart-layout">

            <section class="cart-items">

                <?php foreach ($cartItems as $item): ?>
                    <?php
                        $product = $item['product'];
                        $productId = (int) $product['id'];
                        $imagePath = 'assets/images/' . $product['image'];
                        $imageExists = !empty($product['image'])
                            && file_exists(__DIR__ . '/' . $imagePath);
                    ?>

                    <article class="cart-item">

                        <div class="cart-item-image">
                            <?php if ($imageExists): ?>
                                <img
                                    src="<?= htmlspecialchars($imagePath) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>"
                                >
                            <?php else: ?>
                                <div class="image-placeholder">No Image</div>
                            <?php endif; ?>
                        </div>

                        <div class="cart-item-info">
                            <h3><?= htmlspecialchars($product['name']) ?></h3>

                            <p class="cart-item-price">
                                ₱<?= number_format((float) $product['price'], 2) ?>
                            </p>

                            <?php if (!$item['available']): ?>
                                <p class="unavailable-text">
                                    <?php if ((int) $product['is_active'] !== 1): ?>
                                        This product is inactive.
                                    <?php elseif ((int) $product['stock'] === 0): ?>
                                        Out of stock.
                                    <?php else: ?>
                                        Only <?= (int) $product['stock'] ?> item(s)
                                        currently available.
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="available-text">In Stock</p>
                            <?php endif; ?>

                            <div class="cart-item-actions">

                                <form method="POST" action="cart.php"
                                      class="cart-update-form">

                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id"
                                           value="<?= $productId ?>">

                                    <label for="quantity-<?= $productId ?>">
                                        Quantity:
                                    </label>

                                    <input
                                        type="number"
                                        id="quantity-<?= $productId ?>"
                                        name="quantity"
                                        min="1"
                                        max="<?= (int) $product['stock'] ?>"
                                        value="<?= $item['quantity'] ?>"
                                        required
                                    >

                                    <button type="submit" class="update-button">
                                        Update
                                    </button>
                                </form>

                                <form method="POST" action="cart.php">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id"
                                           value="<?= $productId ?>">

                                    <button type="submit" class="remove-button">
                                        Remove
                                    </button>
                                </form>

                            </div>
                        </div>

                        <div class="cart-item-total">
                            <span>₱<?= number_format($item['line_total'], 2) ?></span>
                        </div>

                    </article>
                <?php endforeach; ?>

            </section>

            <aside class="cart-summary">
                <h2>Order Summary</h2>

                <div class="summary-row">
                    <span>Items</span>
                    <span><?= $cartCount ?></span>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₱<?= number_format($subtotal, 2) ?></span>
                </div>

                <div class="summary-row summary-total">
                    <span>Total</span>
                    <span>₱<?= number_format($subtotal, 2) ?></span>
                </div>

                <?php if (!$hasUnavailableItems): ?>
    <a href="checkout.php" class="checkout-button">
        Proceed to Checkout
    </a>
<?php else: ?>
    <button type="button" class="checkout-button" disabled>
        Proceed to Checkout
    </button>
<?php endif; ?>
            </aside>

        </div>

    <?php endif; ?>

</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> CubeTech. All rights reserved.</p>
    </div>
</footer>
<script src="assets/js/main.js"></script>
</body>
</html>
