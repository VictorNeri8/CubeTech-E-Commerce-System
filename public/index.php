<?php

session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

require_once __DIR__ . '/../app/models/Product.php';

$productModel = new Product($pdo);

// Read search, category, and sorting filters
$search = trim($_GET['search'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';

// Retrieve products
$products = $productModel->getActiveProducts(
    $search,
    $categoryId > 0 ? $categoryId : null,
    $sort
);

// Retrieve categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

// Retrieve featured products (latest active products)
$featuredProducts = $productModel->getActiveProducts('', null, 'newest');
$featuredProducts = array_slice($featuredProducts, 0, 4);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CubeTech Store</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
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
                       <!--  <a href="cart.php">My Cart</a> -->
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


<main>

    <section class="hero">
        <div class="container hero-content">
            <p class="eyebrow">WELCOME TO CUBETECH</p>

            <h1>Technology for<br>everyday living.</h1>

            <p class="hero-description">
                Discover quality electronics, accessories,
                and essentials for your everyday needs.
            </p>

            <a href="#products" class="primary-button">
                Explore Products
            </a>
        </div>
    </section>

    <section class="products-section container" id="products">

        <div class="section-heading">
            <div>
                <p class="eyebrow">OUR COLLECTION</p>
                <h2>Explore our products</h2>
            </div>

            <span class="product-count" id="productCount">
    <?= count($products) ?> products found
</span>
        </div>

        <form action="index.php" method="GET" class="filter-bar" id="filterForm">

            <input
    type="text"
    name="search"
    id="searchInput"
    placeholder="Search products..."
    value="<?= htmlspecialchars($search) ?>"
    class="search-input"
>

            <select name="category" class="filter-select">
                <option value="0">All Categories</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="sort" class="filter-select">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                    Newest
                </option>

                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>
                    Price: Low to High
                </option>

                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>
                    Price: High to Low
                </option>

                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>
                    Name: A to Z
                </option>
            </select>

            <button type="submit" class="filter-button">
                Apply
            </button>

        </form>

        <div id="productResults">
        <?php if (empty($products)): ?>

            <div class="empty-state">
                <h3>No products found</h3>
                <p>Try another search or category.</p>
                <a href="index.php">Clear filters</a>
            </div>

        <?php else: ?>

            <div class="product-grid">

                <?php foreach ($products as $product): ?>

                    <article class="product-card">

                        <a
                            href="product-details.php?id=<?= (int) $product['id'] ?>"
                            class="product-image"
                        >
                            <?php
                            $imageFile = basename($product['image'] ?? '');
                            $imagePath = __DIR__ . '/assets/images/' . $imageFile;
                            ?>

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
                        </a>

                        <div class="product-info">

                            <p class="product-category">
                                <?= htmlspecialchars($product['category_name']) ?>
                            </p>

                            <h3>
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>

                            <p class="product-price">
                                ₱<?= number_format((float) $product['price'], 2) ?>
                            </p>

                            <?php if ((int) $product['stock'] > 0): ?>

                                <span class="stock-status available">
                                    In Stock
                                </span>

                            <?php else: ?>

                                <span class="stock-status unavailable">
                                    Out of Stock
                                </span>

                            <?php endif; ?>

                            <a
                                href="product-details.php?id=<?= (int) $product['id'] ?>"
                                class="product-button"
                            >
                                View Product
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>
        </div>
    </section>

</main>

<footer class="footer">
    <div class="container">
        <p>© <?= date('Y') ?> CubeTech Store. All rights reserved.</p>
    </div>
</footer>
<script src="assets/js/main.js"></script>
</body>
</html>
