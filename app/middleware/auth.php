<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}