<?php

session_start();

require_once 'includes/config.php';

header('Content-Type: text/html; charset=UTF-8');

/* =========================================================
   AUTHENTICATION
========================================================= */

if (
    empty($_SESSION['user_id']) ||
    empty($_SESSION['session_token']) ||
    empty($_SESSION['department_id']) ||
    !isset($_SESSION['area'])
) {
    header('Location: index.php');
    exit;
}

$conn = getDBConnection();

$userId     = (int) $_SESSION['user_id'];
$department = (int) $_SESSION['department_id'];
$area       = (int) $_SESSION['area'];

/* =========================================================
   POST ONLY
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: recommender_settings.php');
    exit;
}

$delegationId = isset($_POST['delegation_id'])
    ? (int) $_POST['delegation_id']
    : 0;

if ($delegationId <= 0) {
    die('Invalid delegation.');
}

/* =========================================================
   TRANSACTION
========================================================= */

$conn->begin_transaction();

try {

    /* =====================================================
       FIND DELEGATION

       IMPORTANT:
       primary_recommender_id MUST match the logged-in user.
       This prevents a Secondary Recommender or another user
       from cancelling someone else's delegation.
    ====================================================== */

    $stmt = $conn->prepare("
        SELECT
            rd.id,
            rd.primary_recommender_id,
            rd.secondary_recommender_id,
            rd.start_date,
            rd.end_date,
            rd.status
        FROM recommender_delegations rd
        INNER JOIN users u
            ON u.id = rd.primary_recommender_id
        WHERE rd.id = ?
          AND rd.primary_recommender_id = ?
          AND rd.department_id = ?
          AND u.area_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception(
            'Unable to verify delegation.'
        );
    }

    $stmt->bind_param(
        "iiii",
        $delegationId,
        $userId,
        $department,
        $area
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $delegation = $result->fetch_assoc();

    $stmt->close();

    /* =====================================================
       AUTHORIZATION CHECK
    ====================================================== */

    if (!$delegation) {
        throw new Exception(
            'You are not authorized to cancel this delegation.'
        );
    }

    /* =====================================================
       CHECK STATUS
    ====================================================== */

    if ($delegation['status'] !== 'active') {
        throw new Exception(
            'This delegation has already been cancelled or expired.'
        );
    }

    /* =====================================================
       CANCEL DELEGATION
    ===================================================== */

    $cancelStmt = $conn->prepare("
        UPDATE recommender_delegations
        SET
            status = 'cancelled',
            cancelled_by = ?,
            cancelled_at = NOW()
        WHERE id = ?
          AND primary_recommender_id = ?
          AND status = 'active'
    ");

    if (!$cancelStmt) {
        throw new Exception(
            'Unable to cancel delegation.'
        );
    }

    $cancelStmt->bind_param(
        "iii",
        $userId,
        $delegationId,
        $userId
    );

    if (!$cancelStmt->execute()) {
        throw new Exception(
            'Failed to cancel delegation.'
        );
    }

    if ($cancelStmt->affected_rows !== 1) {
        throw new Exception(
            'The delegation could not be cancelled.'
        );
    }

    $cancelStmt->close();

    /* =====================================================
       COMMIT
    ====================================================== */

    $conn->commit();

    $conn->close();

    header(
        'Location: recommender_settings.php?delegation=cancelled'
    );
    exit;

} catch (Throwable $e) {

    $conn->rollback();
    $conn->close();

    http_response_code(400);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Delegation Error</title>';
    echo '<link rel="stylesheet" href="assets/css/bootstrap.min.css">';
    echo '</head>';
    echo '<body class="bg-light">';

    echo '<div class="container py-5">';
    echo '<div class="alert alert-danger">';
    echo '<strong>Unable to cancel delegation.</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';

    echo '<a href="recommender_settings.php" class="btn btn-secondary">';
    echo 'Back to Settings';
    echo '</a>';

    echo '</div>';
    echo '</body>';
    echo '</html>';

    exit;
}