<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__.'/config.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token'])
) {
    header('Location: ' . LOGIN_PAGE);
    exit;
}
