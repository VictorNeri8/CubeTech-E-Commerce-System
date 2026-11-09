
<?php

require_once __DIR__ . '/../../app/middleware/auth.php';
requireAdmin();

$pdo = require __DIR__ . '/../../app/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$status = $_GET['status'] ?? 'all';

$sql = "
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= " AND p.name LIKE :search";
    $params['search'] = "%$search%";
}

if ($categoryId !== false && $categoryId !== null && $categoryId > 0) {
    $sql .= " AND p.category_id = :category_id";
    $params['category_id'] = $categoryId;
}

if ($status === 'active') {
    $sql .= " AND p.is_active = 1";
} elseif ($status === 'inactive') {
    $sql .= " AND p.is_active = 0";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("
    SELECT id, name FROM categories ORDER BY name ASC
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <title>Products | CubeTech Admin</title>
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

        <header class="admin-header">
            <div>
                <h1>Products</h1>
                <p>Manage your store products and inventory.</p>
            </div>

            <a href="product-form.php" class="admin-primary-button">
                + Add Product
            </a>
        </header>

        <form method="GET" class="admin-filter-form">

            <input
                type="text"
                name="search"
                placeholder="Search product name..."
                value="<?= e($search) ?>"
            >

            <select name="category">
                <option value="">All Categories</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= $category['id'] ?>"
                        <?= $categoryId == $category['id'] ? 'selected' : '' ?>
                    >
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>
                    All Status
                </option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                    Active
                </option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>

            <button type="submit" class="admin-filter-button">Filter</button>
            <a href="products.php" class="admin-reset-button">Reset</a>

        </form>

        <div class="admin-table-container">

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="admin-empty">
                                No products found.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <strong><?= e($product['name']) ?></strong>
                                </td>

                                <td><?= e($product['category_name']) ?></td>

                                <td>₱<?= number_format((float) $product['price'], 2) ?></td>

                                <td><?= number_format((int) $product['stock']) ?></td>

                                <td>
                                    <?php if ($product['is_active']): ?>
                                        <span class="admin-status active">Active</span>
                                    <?php else: ?>
                                        <span class="admin-status inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a
                                        href="product-form.php?id=<?= $product['id'] ?>"
                                        class="admin-edit-link"
                                    >
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>
            </table>

        </div>

        <p class="admin-result-count">
            Showing <?= count($products) ?> product(s)
        </p>

    </main>

</div>


<script src="../assets/js/main.js"></script>
</body>
</html>
