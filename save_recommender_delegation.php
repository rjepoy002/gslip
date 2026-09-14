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

/* =========================================================
   GET FORM VALUES
========================================================= */

$secondaryId = isset($_POST['secondary_recommender_id'])
    ? (int) $_POST['secondary_recommender_id']
    : 0;

$startDate = isset($_POST['start_date'])
    ? trim($_POST['start_date'])
    : '';

$endDate = isset($_POST['end_date'])
    ? trim($_POST['end_date'])
    : '';

/* =========================================================
   BASIC VALIDATION
========================================================= */

if ($secondaryId <= 0 || empty($startDate) || empty($endDate)) {
    die('Invalid delegation request.');
}

/* =========================================================
   DATE VALIDATION
========================================================= */

$startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
$endDateObj   = DateTime::createFromFormat('Y-m-d', $endDate);

if (
    !$startDateObj ||
    !$endDateObj ||
    $startDateObj->format('Y-m-d') !== $startDate ||
    $endDateObj->format('Y-m-d') !== $endDate
) {
    die('Invalid delegation dates.');
}

/* Start date must not be after end date */

if ($startDate > $endDate) {
    die('Leave From date cannot be later than Leave Until date.');
}

/* Start date cannot be in the past */

$today = date('Y-m-d');

if ($startDate < $today) {
    die('Leave From date cannot be in the past.');
}

/* =========================================================
   TRANSACTION
========================================================= */

$conn->begin_transaction();

try {

    /* =====================================================
       VERIFY CURRENT USER IS A PRIMARY RECOMMENDER
       IN THE SAME DEPARTMENT + AREA
    ====================================================== */

    $primaryStmt = $conn->prepare("
        SELECT
            dr.id
        FROM department_recommenders dr
        INNER JOIN users u
            ON u.id = dr.user_id
        WHERE dr.user_id = ?
          AND dr.department_id = ?
          AND u.area_id = ?
          AND u.status = 'active'
        LIMIT 1
        FOR UPDATE
    ");

    if (!$primaryStmt) {
        throw new Exception('Unable to verify recommender.');
    }

    $primaryStmt->bind_param(
        "iii",
        $userId,
        $department,
        $area
    );

    $primaryStmt->execute();

    $primaryResult = $primaryStmt->get_result();
    $primary = $primaryResult->fetch_assoc();

    $primaryStmt->close();

    if (!$primary) {
        throw new Exception(
            'You are not authorized to create a Secondary Recommender delegation.'
        );
    }

    /* =====================================================
       CHECK IF PRIMARY ALREADY HAS ACTIVE/FUTURE DELEGATION

       This prevents multiple delegations from being created
       for the same Primary Recommender.
    ====================================================== */

    $existingStmt = $conn->prepare("
        SELECT
            id,
            start_date,
            end_date,
            status
        FROM recommender_delegations
        WHERE primary_recommender_id = ?
          AND department_id = ?
          AND status = 'active'
          AND end_date >= ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$existingStmt) {
        throw new Exception(
            'Unable to check existing delegation.'
        );
    }

    $existingStmt->bind_param(
        "iis",
        $userId,
        $department,
        $today
    );

    $existingStmt->execute();

    $existingResult = $existingStmt->get_result();
    $existingDelegation = $existingResult->fetch_assoc();

    $existingStmt->close();

    if ($existingDelegation) {
        throw new Exception(
            'You already have an active or scheduled Secondary Recommender delegation.'
        );
    }

    /* =====================================================
       VERIFY SECONDARY USER

       Requirements:
       - Active user
       - Same department
       - Same area
       - Cannot be the Primary
    ====================================================== */

    $secondaryStmt = $conn->prepare("
        SELECT
            u.id,
            u.department_id,
            u.area_id,
            u.status
        FROM users u
        WHERE u.id = ?
          AND u.department_id = ?
          AND u.area_id = ?
          AND u.status = 'active'
        LIMIT 1
        FOR UPDATE
    ");

    if (!$secondaryStmt) {
        throw new Exception(
            'Unable to verify Secondary Recommender.'
        );
    }

    $secondaryStmt->bind_param(
        "iii",
        $secondaryId,
        $department,
        $area
    );

    $secondaryStmt->execute();

    $secondaryResult = $secondaryStmt->get_result();
    $secondary = $secondaryResult->fetch_assoc();

    $secondaryStmt->close();

    if (!$secondary) {
        throw new Exception(
            'Selected user is not an eligible Secondary Recommender.'
        );
    }

    if ($secondaryId === $userId) {
        throw new Exception(
            'You cannot assign yourself as Secondary Recommender.'
        );
    }

    /* =====================================================
       SECONDARY MUST NOT ALREADY BE A PRIMARY RECOMMENDER
    ====================================================== */

    $recommenderStmt = $conn->prepare("
        SELECT id
        FROM department_recommenders
        WHERE user_id = ?
          AND department_id = ?
        LIMIT 1
    ");

    if (!$recommenderStmt) {
        throw new Exception(
            'Unable to check recommender assignment.'
        );
    }

    $recommenderStmt->bind_param(
        "ii",
        $secondaryId,
        $department
    );

    $recommenderStmt->execute();

    $recommenderResult = $recommenderStmt->get_result();
    $existingRecommender = $recommenderResult->fetch_assoc();

    $recommenderStmt->close();

    if ($existingRecommender) {
        throw new Exception(
            'The selected user is already a Primary Recommender.'
        );
    }

    /* =====================================================
       SECONDARY MUST NOT BE AN APPROVER
    ====================================================== */

    $approverStmt = $conn->prepare("
        SELECT id
        FROM department_approvers
        WHERE user_id = ?
          AND department_id = ?
        LIMIT 1
    ");

    if (!$approverStmt) {
        throw new Exception(
            'Unable to check approver assignment.'
        );
    }

    $approverStmt->bind_param(
        "ii",
        $secondaryId,
        $department
    );

    $approverStmt->execute();

    $approverResult = $approverStmt->get_result();
    $existingApprover = $approverResult->fetch_assoc();

    $approverStmt->close();

    if ($existingApprover) {
        throw new Exception(
            'The selected user is an Approver and cannot be a Secondary Recommender.'
        );
    }

    /* =====================================================
       CHECK SECONDARY'S EXISTING ACTIVE/FUTURE DELEGATION

       Prevent the same user from being delegated to during
       an overlapping period.
    ====================================================== */

    $overlapStmt = $conn->prepare("
        SELECT
            id,
            start_date,
            end_date,
            status
        FROM recommender_delegations
        WHERE secondary_recommender_id = ?
          AND status = 'active'
          AND end_date >= ?
          AND start_date <= ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$overlapStmt) {
        throw new Exception(
            'Unable to check Secondary Recommender availability.'
        );
    }

    $overlapStmt->bind_param(
        "iss",
        $secondaryId,
        $startDate,
        $endDate
    );

    $overlapStmt->execute();

    $overlapResult = $overlapStmt->get_result();
    $overlap = $overlapResult->fetch_assoc();

    $overlapStmt->close();

    if ($overlap) {
        throw new Exception(
            'The selected user already has a Secondary Recommender delegation during the selected period.'
        );
    }

    /* =====================================================
       INSERT DELEGATION
    ====================================================== */

    $insertStmt = $conn->prepare("
        INSERT INTO recommender_delegations (
            department_id,
            primary_recommender_id,
            secondary_recommender_id,
            start_date,
            end_date,
            status,
            created_by,
            created_at
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'active',
            ?,
            NOW()
        )
    ");

    if (!$insertStmt) {
        throw new Exception(
            'Unable to create delegation.'
        );
    }

    $insertStmt->bind_param(
        "iiissi",
        $department,
        $userId,
        $secondaryId,
        $startDate,
        $endDate,
        $userId
    );

    if (!$insertStmt->execute()) {
        throw new Exception(
            'Failed to save Secondary Recommender delegation.'
        );
    }

    $insertStmt->close();

    /* =====================================================
       COMMIT
    ====================================================== */

    $conn->commit();

    $conn->close();

    header(
        'Location: recommender_settings.php?delegation=success'
    );
    exit;

} catch (Throwable $e) {

    /* =====================================================
       ROLLBACK
    ====================================================== */

    $conn->rollback();
    $conn->close();

    /*
       For now, display the error directly.
       We can replace this with a Bootstrap alert/flash
       message after the save function is confirmed working.
    */

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
    echo '<strong>Unable to create delegation.</strong><br>';
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