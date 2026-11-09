
<?php

session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

require_once __DIR__ . '/../app/models/Product.php';

$productModel = new Product($pdo);

// Get product ID from URL
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    http_response_code(404);
    exit('Product not found.');
}

// Retrieve product
$product = $productModel->getProductById($productId);

if (!$product) {
    http_response_code(404);
    exit('Product not found or unavailable.');
}

// Retrieve related products
$relatedProducts = $productModel->getRelatedProducts(
    (int) $product['category_id'],
    (int) $product['id']
);

$imageFile = basename($product['image'] ?? '');
$imagePath = __DIR__ . '/assets/images/' . $imageFile;

$message = $_SESSION['cart_message'] ?? null;
unset($_SESSION['cart_message']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">

    <title>
        <?= htmlspecialchars($product['name']) ?> | CubeTech
    </title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>


<header class="navbar">
    <div class="container nav-content">

        <a href="index.php" class="logo">
            CubeTech<span>.</span>
        </a>

       <!--  <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="index.php#products" class="active">Products</a>
        </nav> -->

        <div class="nav-actions">

            <a href="cart.php" class="cart-link">Cart</a>

            <div class="account-menu">

                <button
                    type="button"
                    class="account-menu-toggle"
                    id="accountMenuToggle"
                    aria-expanded="false"
                    aria-controls="accountDropdown"
                >
                    <svg viewBox="0 0 24 24" width="22" height="22"
                         fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
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


<main class="container details-page">

<?php if ($message): ?>
    <div class="cart-message <?= htmlspecialchars($message['type']) ?>">
        <?= htmlspecialchars($message['text']) ?>
    </div>
<?php endif; ?>

    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>/</span>
        <a href="index.php#products">Products</a>
        <span>/</span>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </div>

    <section class="product-details">

        <div class="details-image">

            <?php if ($imageFile !== '' && is_file($imagePath)): ?>

                <img
                    src="assets/images/<?= rawurlencode($imageFile) ?>"
                    alt="<?= htmlspecialchars($product['name']) ?>"
                >

            <?php else: ?>

                <div class="image-placeholder">
                    No image available
                </div>

            <?php endif; ?>

        </div>

        <div class="details-info">

            <p class="product-category">
                <?= htmlspecialchars($product['category_name']) ?>
            </p>

            <h1><?= htmlspecialchars($product['name']) ?></h1>

            <p class="details-price">
                ₱<?= number_format((float) $product['price'], 2) ?>
            </p>

            <p class="details-description">
                <?= nl2br(htmlspecialchars(
                    $product['description'] ?? 'No description available.'
                )) ?>
            </p>

            <div class="details-stock">

                <?php if ((int) $product['stock'] > 0): ?>

                    <span class="stock-status available">
                        In Stock
                    </span>

                    <span class="stock-quantity">
                        <?= (int) $product['stock'] ?> available
                    </span>

                <?php else: ?>

                    <span class="stock-status unavailable">
                        Out of Stock
                    </span>

                <?php endif; ?>

            </div>

            <?php if ((int) $product['stock'] > 0): ?>

                <form action="cart.php" method="POST" class="add-cart-form">

                    <input
                        type="hidden"
                        name="action"
                        value="add"
                    >

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?= (int) $product['id'] ?>"
                    >

                    <div class="quantity-control">

                        <label for="quantity">Quantity</label>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="1"
                            min="1"
                            max="<?= (int) $product['stock'] ?>"
                            required
                        >

                    </div>

                    <button type="submit" class="primary-button add-cart-button">
                        Add to Cart
                    </button>

                </form>

            <?php else: ?>

                <button class="primary-button add-cart-button" disabled>
                    Out of Stock
                </button>

            <?php endif; ?>

            <a href="index.php" class="back-link">
                ← Continue Shopping
            </a>

        </div>

    </section>

    <?php if (!empty($relatedProducts)): ?>

        <section class="related-section">

            <div class="section-heading">
                <div>
                    <p class="eyebrow">YOU MAY ALSO LIKE</p>
                    <h2>Related Products</h2>
                </div>
            </div>

            <div class="product-grid">

                <?php foreach ($relatedProducts as $related): ?>

                    <?php
                    $relatedImage = basename($related['image'] ?? '');
                    $relatedImagePath = __DIR__ . '/assets/images/' . $relatedImage;
                    ?>

                    <article class="product-card">

                        <a
                            href="product-details.php?id=<?= (int) $related['id'] ?>"
                            class="product-image"
                        >

                            <?php if (
                                $relatedImage !== '' &&
                                is_file($relatedImagePath)
                            ): ?>

                                <img
                                    src="assets/images/<?= rawurlencode($relatedImage) ?>"
                                    alt="<?= htmlspecialchars($related['name']) ?>"
                                >

                            <?php else: ?>

                                <div class="image-placeholder">
                                    No image available
                                </div>

                            <?php endif; ?>

                        </a>

                        <div class="product-info">

                            <h3>
                                <?= htmlspecialchars($related['name']) ?>
                            </h3>

                            <p class="product-price">
                                ₱<?= number_format((float) $related['price'], 2) ?>
                            </p>

                            <a
                                href="product-details.php?id=<?= (int) $related['id'] ?>"
                                class="product-button"
                            >
                                View Product
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</main>

<footer class="footer">
    <div class="container">
        <p>© <?= date('Y') ?> CubeTech Store. All rights reserved.</p>
    </div>
</footer>
<script src="assets/js/main.js"></script>
</body>
</html>
