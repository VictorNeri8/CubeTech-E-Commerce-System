
<?php

require_once __DIR__ . '/../../app/middleware/auth.php';
requireAdmin();

$pdo = require __DIR__ . '/../../app/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        grouped.customer_email,

        (
            SELECT o2.customer_name
            FROM orders o2
            WHERE LOWER(TRIM(o2.customer_email)) = grouped.customer_email
            ORDER BY o2.created_at DESC, o2.id DESC
            LIMIT 1
        ) AS customer_name,

        (
            SELECT o2.contact_number
            FROM orders o2
            WHERE LOWER(TRIM(o2.customer_email)) = grouped.customer_email
            ORDER BY o2.created_at DESC, o2.id DESC
            LIMIT 1
        ) AS contact_number,

        grouped.order_count,
        grouped.completed_value,
        grouped.latest_order

    FROM (
        SELECT
            LOWER(TRIM(customer_email)) AS customer_email,
            COUNT(*) AS order_count,
            SUM(
                CASE
                    WHEN status = 'Completed' THEN total
                    ELSE 0
                END
            ) AS completed_value,
            MAX(created_at) AS latest_order

        FROM orders
        WHERE customer_email IS NOT NULL
          AND TRIM(customer_email) != ''
        GROUP BY LOWER(TRIM(customer_email))
    ) AS grouped
";

$params = [];

if ($search !== '') {
    $sql .= "
        WHERE grouped.customer_email LIKE :search
        OR EXISTS (
            SELECT 1
            FROM orders search_orders
            WHERE LOWER(TRIM(search_orders.customer_email)) = grouped.customer_email
            AND search_orders.customer_name LIKE :search_name
        )
    ";

    $params['search'] = "%$search%";
    $params['search_name'] = "%$search%";
}

$sql .= " ORDER BY grouped.latest_order DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$customers = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <title>Customers | CubeTech Admin</title>
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
                <h1>Customers</h1>
                <p>View customer information and order history summaries.</p>
            </div>
        </header>

        <form method="GET" class="admin-filter-form">
            <input
                type="text"
                name="search"
                placeholder="Search customer name or email..."
                value="<?= e($search) ?>"
            >

            <button type="submit" class="admin-filter-button">
                Search
            </button>

            <a href="customers.php" class="admin-reset-button">
                Reset
            </a>
        </form>

        <div class="admin-table-container">

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Contact Number</th>
                        <th>Total Orders</th>
                        <th>Completed Order Value</th>
                        <th>Latest Order</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="admin-empty">
                                No customers found.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td>
                                    <strong><?= e($customer['customer_name']) ?></strong>
                                </td>

                                <td><?= e($customer['customer_email']) ?></td>

                                <td><?= e($customer['contact_number']) ?></td>

                                <td>
                                    <?= number_format((int) $customer['order_count']) ?>
                                </td>

                                <td>
                                    ₱<?= number_format((float) $customer['completed_value'], 2) ?>
                                </td>

                                <td>
                                    <?= date('M d, Y', strtotime($customer['latest_order'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>
            </table>

        </div>

        <p class="admin-result-count">
            Showing <?= count($customers) ?> customer(s)
        </p>

    </main>

</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
