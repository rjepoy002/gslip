<?php
session_start();
require_once 'includes/config.php';

/*
 |----------------------------------------
 | Invalidate session token in database
 |----------------------------------------
 */

if (!empty($_SESSION['user_id'])) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        UPDATE users 
        SET session_token = NULL 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    $conn->close();
}

/*
 |----------------------------------------
 | Destroy PHP session completely
 |----------------------------------------
 */

// Unset all session variables
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

/*
 |----------------------------------------
 | Redirect to login page
 |----------------------------------------
 */

header('Location: index.php');
exit;
