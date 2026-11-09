
<?php

require_once __DIR__ . '/../../app/middleware/auth.php';
requireAdmin();

$pdo = require __DIR__ . '/../../app/config/database.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$uploadDirectory = __DIR__ . '/../assets/images/';

$errors = [];
$isEditing = false;

$product = [
    'name' => '',
    'category_id' => '',
    'description' => '',
    'price' => '',
    'stock' => 0,
    'image' => '',
    'is_active' => 1
];

// Load product when editing
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($productId) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute(['id' => $productId]);
    $existingProduct = $stmt->fetch();

    if (!$existingProduct) {
        http_response_code(404);
        exit('Product not found.');
    }

    $product = $existingProduct;
    $isEditing = true;
}

// Load categories
$categories = $pdo->query("
    SELECT id, name FROM categories ORDER BY name ASC
")->fetchAll();

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['admin_csrf'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Invalid form submission. Please refresh the page.';
    }

    $product['name'] = trim($_POST['name'] ?? '');
    $product['category_id'] = $_POST['category_id'] ?? '';
    $product['description'] = trim($_POST['description'] ?? '');
    $product['price'] = trim($_POST['price'] ?? '');
    $product['stock'] = trim($_POST['stock'] ?? '');
    $product['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    // Validate product name
    if ($product['name'] === '') {
        $errors[] = 'Product name is required.';
    } elseif (mb_strlen($product['name']) > 150) {
        $errors[] = 'Product name must not exceed 150 characters.';
    }

    // Validate category
    $categoryId = filter_var($product['category_id'], FILTER_VALIDATE_INT);

    if (!$categoryId || $categoryId < 1) {
        $errors[] = 'Please select a valid category.';
    } else {
        $categoryCheck = $pdo->prepare(
            "SELECT id FROM categories WHERE id = :id"
        );
        $categoryCheck->execute(['id' => $categoryId]);

        if (!$categoryCheck->fetch()) {
            $errors[] = 'The selected category does not exist.';
        }
    }

    // Validate description
    if (mb_strlen($product['description']) > 10000) {
        $errors[] = 'Description is too long.';
    }

    // Validate price
    if (
        !is_numeric($product['price']) ||
        (float) $product['price'] <= 0 ||
        !is_finite((float) $product['price']) ||
        strlen($product['price']) > 20
    ) {
        $errors[] = 'Enter a valid price greater than zero.';
    }

    // Validate stock
    $stock = filter_var($product['stock'], FILTER_VALIDATE_INT);

    if ($stock === false || $stock < 0) {
        $errors[] = 'Stock must be a whole number of zero or higher.';
    }

    // Validate image upload
    $newImageName = $product['image'];

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading the image.';
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Image must not exceed 2 MB.';
        } else {
            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['image']['tmp_name']);

            if (!isset($allowedMimeTypes[$mimeType])) {
                $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
            } else {
                $extension = $allowedMimeTypes[$mimeType];
                $newImageName = bin2hex(random_bytes(16)) . '.' . $extension;
            }
        }
    }

    // Save product
    if (empty($errors)) {

        try {
            if (
                isset($_FILES['image']) &&
                $_FILES['image']['error'] === UPLOAD_ERR_OK
            ) {
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }

                if (!move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $uploadDirectory . $newImageName
                )) {
                    throw new RuntimeException('Unable to save the uploaded image.');
                }
            }

            $data = [
                'category_id' => $categoryId,
                'name' => $product['name'],
                'description' => $product['description'],
                'image' => $newImageName ?: null,
                'price' => $product['price'],
                'stock' => $stock,
                'is_active' => $product['is_active']
            ];

            if ($isEditing) {
                $data['id'] = $productId;

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET category_id = :category_id,
                        name = :name,
                        description = :description,
                        image = :image,
                        price = :price,
                        stock = :stock,
                        is_active = :is_active
                    WHERE id = :id
                ");

                $stmt->execute($data);

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO products
                    (category_id, name, description, image, price, stock, is_active)
                    VALUES
                    (:category_id, :name, :description, :image, :price, :stock, :is_active)
                ");

                $stmt->execute($data);
            }

            header('Location: products.php');
            exit;

        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Unable to save the product. Please try again.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
    <title><?= $isEditing ? 'Edit' : 'Add' ?> Product | CubeTech Admin</title>
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
                <h1><?= $isEditing ? 'Edit Product' : 'Add Product' ?></h1>
                <p>Enter the product information below.</p>
            </div>

            <a href="products.php" class="admin-reset-button">
                Back to Products
            </a>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="admin-form-errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="admin-product-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['admin_csrf']) ?>"
            >

            <div class="admin-form-group">
                <label for="name">Product Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="150"
                    value="<?= e($product['name']) ?>"
                    required
                >
            </div>

            <div class="admin-form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select Category</option>

                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= $category['id'] ?>"
                            <?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>
                        >
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-form-group">
                <label for="description">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                ><?= e($product['description']) ?></textarea>
            </div>

            <div class="admin-form-row">

                <div class="admin-form-group">
                    <label for="price">Price (₱)</label>
                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="0.01"
                        step="0.01"
                        value="<?= e($product['price']) ?>"
                        required
                    >
                </div>

                <div class="admin-form-group">
                    <label for="stock">Stock Quantity</label>
                    <input
                        type="number"
                        id="stock"
                        name="stock"
                        min="0"
                        step="1"
                        value="<?= e($product['stock']) ?>"
                        required
                    >
                </div>

            </div>

            <div class="admin-form-group">
                <label for="image">Product Image</label>
                <input
                    type="file"
                    id="image"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                >
                <small>Accepted formats: JPG, PNG, WEBP. Maximum size: 2 MB.</small>

                <?php if (!empty($product['image'])): ?>
                    <div class="admin-current-image">
                        <p>Current image:</p>
                        <img
                            src="../assets/images/<?= rawurlencode($product['image']) ?>"
                            alt="Current product image"
                        >
                    </div>
                <?php endif; ?>
            </div>

            <label class="admin-checkbox-label">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    <?= $product['is_active'] ? 'checked' : '' ?>
                >
                Active product (visible in the storefront)
            </label>

            <div class="admin-form-actions">
                <button type="submit" class="admin-primary-button">
                    <?= $isEditing ? 'Save Changes' : 'Add Product' ?>
                </button>

                <a href="products.php" class="admin-cancel-button">
                    Cancel
                </a>
            </div>

        </form>

    </main>

</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
