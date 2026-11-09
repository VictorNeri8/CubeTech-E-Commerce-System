
<?php

session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

if (!empty($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (empty($_SESSION['register_csrf'])) {
    $_SESSION['register_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');

    if (!hash_equals(
        $_SESSION['register_csrf'],
        $_POST['csrf_token'] ?? ''
    )) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif (
        $name === '' ||
        $email === '' ||
        $password === '' ||
        $contactNumber === '' ||
        $deliveryAddress === ''
    ) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO customers (
                    name,
                    email,
                    password_hash,
                    contact_number,
                    delivery_address
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $email,
                $passwordHash,
                $contactNumber,
                $deliveryAddress
            ]);

            session_regenerate_id(true);

            $_SESSION['customer_id'] = $pdo->lastInsertId();
            $_SESSION['customer_name'] = $name;
            unset($_SESSION['register_csrf']);

            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'An account with this email address already exists.';
            } else {
                error_log($e->getMessage());
                $error = 'Registration failed. Please try again.';
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
    <title>Create Account | CubeTech</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main class="account-container">
    <div class="account-card">
        <h1>Create an Account</h1>
        <p class="account-description">
            Register to save your checkout details and manage your orders.
        </p>

        <?php if ($error): ?>
            <div class="account-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['register_csrf']) ?>"
            >

            <div class="account-form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="150"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
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
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
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
                    value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
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
                ><?= htmlspecialchars($_POST['delivery_address'] ?? '') ?></textarea>
            </div>

            <div class="account-form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    required
                >
            </div>

            <div class="account-form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="account-submit">
                Create Account
            </button>
        </form>

        <p class="account-footer">
            Already have an account?
            <a href="login.php">Log in</a>
        </p>
    </div>
</main>

</body>
</html>
