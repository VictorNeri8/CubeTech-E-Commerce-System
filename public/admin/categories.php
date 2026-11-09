
<?php

require_once __DIR__ . '/../../app/middleware/auth.php';
requireAdmin();

$pdo = require __DIR__ . '/../../app/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

$editingCategory = null;

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute(['id' => $editId]);
    $editingCategory = $stmt->fetch();

    if (!$editingCategory) {
        $error = 'Category not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['admin_csrf'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid form submission. Please refresh the page.';
    } else {

        $action = $_POST['action'] ?? '';

        // Add or update category
        if ($action === 'save') {

            $name = trim($_POST['name'] ?? '');
            $categoryId = filter_var(
                $_POST['category_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($name === '') {
                $error = 'Category name is required.';
            } elseif (mb_strlen($name) > 100) {
                $error = 'Category name must not exceed 100 characters.';
            } else {

                $duplicate = $pdo->prepare("
                    SELECT id FROM categories
                    WHERE LOWER(name) = LOWER(:name)
                    AND id != :id
                    LIMIT 1
                ");

                $duplicate->execute([
                    'name' => $name,
                    'id' => $categoryId ?: 0
                ]);

                if ($duplicate->fetch()) {
                    $error = 'A category with this name already exists.';
                } else {

                    if ($categoryId) {
                        $stmt = $pdo->prepare("
                            UPDATE categories
                            SET name = :name
                            WHERE id = :id
                        ");

                        $stmt->execute([
                            'name' => $name,
                            'id' => $categoryId
                        ]);

                        $message = 'Category updated successfully.';

                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO categories (name)
                            VALUES (:name)
                        ");

                        $stmt->execute(['name' => $name]);

                        $message = 'Category added successfully.';
                    }

                    $editingCategory = null;
                }
            }
        }

        // Delete category
        if ($action === 'delete') {

            $categoryId = filter_var(
                $_POST['category_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$categoryId || $categoryId < 1) {
                $error = 'Invalid category.';
            } else {

                $check = $pdo->prepare("
                    SELECT COUNT(*) FROM products
                    WHERE category_id = :id
                ");

                $check->execute(['id' => $categoryId]);

                if ((int) $check->fetchColumn() > 0) {
                    $error = 'Cannot delete this category because products are assigned to it.';
                } else {

                    $stmt = $pdo->prepare("
                        DELETE FROM categories
                        WHERE id = :id
                    ");

                    $stmt->execute(['id' => $categoryId]);

                    $message = 'Category deleted successfully.';
                }
            }
        }
    }
}

// Load categories with product counts
$categories = $pdo->query("
    SELECT
        c.id,
        c.name,
        c.created_at,
        COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id, c.name, c.created_at
    ORDER BY c.name ASC
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <title>Categories | CubeTech Admin</title>
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
                <h1>Categories</h1>
                <p>Organize your store products.</p>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="admin-success-message"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="admin-form-errors">
                <p><?= e($error) ?></p>
            </div>
        <?php endif; ?>

        <section class="admin-category-form-card">

            <h2><?= $editingCategory ? 'Edit Category' : 'Add Category' ?></h2>

            <form method="POST" class="admin-category-form">

                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_csrf']) ?>">
                <input type="hidden" name="action" value="save">

                <?php if ($editingCategory): ?>
                    <input
                        type="hidden"
                        name="category_id"
                        value="<?= (int) $editingCategory['id'] ?>"
                    >
                <?php endif; ?>

                <input
                    type="text"
                    name="name"
                    maxlength="100"
                    placeholder="Enter category name"
                    value="<?= e($editingCategory['name'] ?? ($_POST['name'] ?? '')) ?>"
                    required
                >

                <button type="submit" class="admin-primary-button">
                    <?= $editingCategory ? 'Save Changes' : 'Add Category' ?>
                </button>

                <?php if ($editingCategory): ?>
                    <a href="categories.php" class="admin-cancel-button">
                        Cancel
                    </a>
                <?php endif; ?>

            </form>

        </section>

        <section class="admin-category-list">

            <h2>All Categories</h2>

            <div class="admin-table-container">

                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Products</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="admin-empty">
                                    No categories found.
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($category['name']) ?></strong>
                                    </td>

                                    <td><?= (int) $category['product_count'] ?></td>

                                    <td>
                                        <?= date('M d, Y', strtotime($category['created_at'])) ?>
                                    </td>

                                    <td>
                                        <div class="admin-category-actions">

                                            <a
                                                href="categories.php?edit=<?= (int) $category['id'] ?>"
                                                class="admin-edit-link"
                                            >
                                                Edit
                                            </a>

                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_csrf']) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">

                                                <button
                                                    type="submit"
                                                    class="admin-delete-button"
                                                    onclick="return confirm('Are you sure you want to delete this category?')"
                                                >
                                                    Delete
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </section>

    </main>

</div>


<script src="../assets/js/main.js"></script>
</body>
</html>
