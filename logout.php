<?php
session_start();
require_once 'includes/config.php';

require_once __DIR__.'/includes/auth_security.php';
require_once __DIR__.'/includes/remember_login.php';
header('Cache-Control: no-store');
// Existing links still reach this route; GET cannot change authentication state.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $csrf = htmlspecialchars(authCsrf(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logout</title><link rel="stylesheet" href="assets/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/style.css"></head><body><main class="login-card"><h4>Logout Confirmation</h4><form method="post"><input type="hidden" name="csrf" value="'.$csrf.'"><p>Are you sure you want to log out?</p><button class="btn btn-primary" type="submit">Yes, logout</button> <a class="btn btn-secondary" href="dashboard.php">Cancel</a></form></main></body></html>';
    exit;
}
authRequireCsrf();
$conn = getDBConnection();
rememberMaintenanceLock($conn);
try {
    $conn->begin_transaction();
    if (!empty($_SESSION['user_id'])) {
        $lock = $conn->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
        $lock->bind_param('i', $_SESSION['user_id']); $lock->execute(); $lock->close();
    }
    rememberRevokeBrowser($conn);
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['session_token'])) {
        // A stale tab must not invalidate a newer login on another device.
        $stmt = $conn->prepare('UPDATE users SET session_token=NULL WHERE id=? AND session_token=?');
        $stmt->bind_param('is', $_SESSION['user_id'], $_SESSION['session_token']);
        $stmt->execute(); $stmt->close();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Logout revocation failed: '.$e->getMessage());
    http_response_code(503); exit('Unable to log out securely. Please try again.');
} finally { rememberUnlock($conn); }
$conn->close();

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
