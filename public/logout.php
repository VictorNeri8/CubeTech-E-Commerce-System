
<?php

session_start();

// Remove customer session data.
unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name']
);

// Regenerate the session ID.
session_regenerate_id(true);

header('Location: login.php');
exit;
