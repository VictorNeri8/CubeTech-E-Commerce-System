
<?php

$host = '127.0.0.1';
$dbname = 'cubetech_ecommerce';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    return $pdo;

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Database connection failed. Please check your configuration.");
}
