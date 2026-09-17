<?php
session_start();

/* =========================================================
   CLEAR DATE FILTERS
========================================================= */

if (isset($_POST['clear_dates'])) {

    unset($_SESSION['date_from'], $_SESSION['date_to']);

    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'approved_slips.php'));
    exit;
}


/* =========================================================
   SAVE FILTERS
========================================================= */

// Include printed gas slips
// Checked by default
$_SESSION['show_printed'] = isset($_POST['show_printed']) ? 1 : 0;


// Search approved gas slips
$_SESSION['approved_slips_search'] = isset($_POST['search'])
    ? trim($_POST['search'])
    : '';


// Approved date range
$_SESSION['date_from'] = !empty($_POST['date_from'])
    ? $_POST['date_from']
    : null;

$_SESSION['date_to'] = !empty($_POST['date_to'])
    ? $_POST['date_to']
    : null;


/* =========================================================
   RETURN
========================================================= */

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'approved_slips.php'));
exit;