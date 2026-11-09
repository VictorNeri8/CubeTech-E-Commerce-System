
<?php

require_once __DIR__ . '/../../app/middleware/auth.php';
requireAdmin();

$pdo = require __DIR__ . '/../../app/config/database.php';

// Dashboard statistics
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM products) AS total_products,
        (SELECT COUNT(*) FROM orders) AS total_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'Pending') AS pending_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'Completed') AS completed_orders,
        (SELECT COUNT(DISTINCT LOWER(TRIM(customer_email))) FROM orders) AS total_customers,
        (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'Completed') AS total_sales
")->fetch();

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <title>Admin Dashboard</title>
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
                <h1>Dashboard</h1>
                <p>Overview of your store's performance.</p>
            </div>

            <span class="admin-welcome">
                Welcome, <?= e($_SESSION['admin_username']) ?>
            </span>
        </header>

        <section class="admin-stats-grid">

            <div class="admin-stat-card">
                <p>Total Products</p>
                <h2><?= number_format($stats['total_products']) ?></h2>
            </div>

            <div class="admin-stat-card">
                <p>Total Orders</p>
                <h2><?= number_format($stats['total_orders']) ?></h2>
            </div>

            <div class="admin-stat-card">
                <p>Pending Orders</p>
                <h2><?= number_format($stats['pending_orders']) ?></h2>
            </div>

            <div class="admin-stat-card">
                <p>Completed Orders</p>
                <h2><?= number_format($stats['completed_orders']) ?></h2>
            </div>

            <div class="admin-stat-card">
                <p>Total Customers</p>
                <h2><?= number_format($stats['total_customers']) ?></h2>
            </div>

            <div class="admin-stat-card">
                <p>Total Sales</p>
                <h2>₱<?= number_format((float) $stats['total_sales'], 2) ?></h2>
                <small>Completed orders only</small>
            </div>

        </section>

        <section class="admin-welcome-card">
            <h2>Store Management</h2>
            <p>
                Use the navigation menu to manage your products, categories,
                customers, and orders.
            </p>
        </section>

    </main>

</div>

<script src="../assets/js/main.js"></script>


</body>
</html>
