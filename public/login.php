
<?php

session_start();

$pdo = require __DIR__ . '/../app/config/database.php';

if (!empty($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!hash_equals(
        $_SESSION['login_csrf'],
        $_POST['csrf_token'] ?? ''
    )) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, name, email, password_hash
            FROM customers
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];

            unset($_SESSION['login_csrf']);

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
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
    <title>Customer Login | CubeTech</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main class="account-container">
    <div class="account-card">
        <h1>Customer Login</h1>
        <p class="account-description">
            Log in to access your account and orders.
        </p>

        <?php if ($error): ?>
            <div class="account-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['login_csrf']) ?>"
            >

            <div class="account-form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <div class="account-form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit" class="account-submit">
                Log In
            </button>
        </form>
            
        <p class="account-footer">
            Don't have an account?
            <a href="register.php">Create an account</a>
        </p>

        <p class="back-to-store">
    <a href="index.php">← Back to Store</a>
</p>
    </div>
</main>

</body>
</html>
