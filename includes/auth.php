<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token'])
) {
    header('Location: ' . LOGIN_PAGE);
    exit;
}
