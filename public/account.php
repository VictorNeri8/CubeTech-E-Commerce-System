
<?php

session_start();

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = require __DIR__ . '/../app/config/database.php';

$customerId = $_SESSION['customer_id'];
$error = '';
$success = '';

if (empty($_SESSION['account_csrf'])) {
    $_SESSION['account_csrf'] = bin2hex(random_bytes(32));
}

// Load current customer details.
$stmt = $pdo->prepare("
    SELECT name, email, contact_number, delivery_address
    FROM customers
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');

    if (!hash_equals(
        $_SESSION['account_csrf'],
        $_POST['csrf_token'] ?? ''
    )) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif (
        $name === '' ||
        $email === '' ||
        $contactNumber === '' ||
        $deliveryAddress === ''
    ) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE customers
                SET name = ?,
                    email = ?,
                    contact_number = ?,
                    delivery_address = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $email,
                $contactNumber,
                $deliveryAddress,
                $customerId
            ]);

            $_SESSION['customer_name'] = $name;

            $customer = [
                'name' => $name,
                'email' => $email,
                'contact_number' => $contactNumber,
                'delivery_address' => $deliveryAddress
            ];

            $success = 'Your account details have been updated.';

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'This email address is already registered.';
            } else {
                error_log($e->getMessage());
                $error = 'Unable to update your details. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <title>My Account | CubeTech</title>
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

<main class="account-container">
    <div class="account-card">
        <h1>My Account</h1>

        <p class="account-description">
            Manage your saved customer and checkout information.
        </p>

        <?php if ($error): ?>
            <div class="account-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="account-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="account.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['account_csrf']) ?>"
            >

            <div class="account-form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="150"
                    value="<?= htmlspecialchars($customer['name']) ?>"
                    required
                >
            </div>

            <div class="account-form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= htmlspecialchars($customer['email']) ?>"
                    required
                >
            </div>

            <div class="account-form-group">
                <label for="contact_number">Contact Number</label>
                <input
                    type="tel"
                    id="contact_number"
                    name="contact_number"
                    maxlength="30"
                    value="<?= htmlspecialchars($customer['contact_number']) ?>"
                    required
                >
            </div>

            <div class="account-form-group">
                <label for="delivery_address">Delivery Address</label>
                <textarea
                    id="delivery_address"
                    name="delivery_address"
                    rows="3"
                    required
                ><?= htmlspecialchars($customer['delivery_address']) ?></textarea>
            </div>

            <button type="submit" class="account-submit">
                Save Changes
            </button>
        </form>

        <p class="account-footer">
            <a href="index.php">Back to Store</a>
            |
            <a href="logout.php">Log Out</a>
        </p>
    </div>
</main>


<script src="assets/js/main.js"></script>
</body>
</html>
