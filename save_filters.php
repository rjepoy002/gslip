<?php
session_start();

if (isset($_POST['clear_dates'])) {
    unset($_SESSION['date_from'], $_SESSION['date_to']);

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$_SESSION['show_printed'] = isset($_POST['show_printed']) ? 1 : 0;

$_SESSION['date_from'] = !empty($_POST['date_from']) ? $_POST['date_from'] : null;
$_SESSION['date_to']   = !empty($_POST['date_to'])   ? $_POST['date_to']   : null;

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
