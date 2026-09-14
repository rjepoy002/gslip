<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once 'includes/config.php';
require_once 'includes/notifications.php';

header('Content-Type: application/json');

/* =========================================================
   BASIC VALIDATION
========================================================= */

if (!isset($_POST['id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Missing gas slip ID'
    ]);

    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);

    exit;
}

$gasSlipId = (int) $_POST['id'];
$userId    = (int) $_SESSION['user_id'];

/* =========================================================
   VERIFY RECOMMENDER AUTHORITY
   Allows:
   - Permanent Primary Recommender
   - Active Secondary Recommender
========================================================= */

$conn = getDBConnection();

$userStmt = $conn->prepare("
    SELECT
        id,
        department_id,
        area_id,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
");

$userStmt->bind_param('i', $userId);
$userStmt->execute();

$userResult = $userStmt->get_result();
$currentUser = $userResult->fetch_assoc();

$userStmt->close();

if (!$currentUser || $currentUser['status'] !== 'active') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized recommender.'
    ]);
    exit;
}

$userDepartmentId = (int) $currentUser['department_id'];
$userAreaId       = (int) $currentUser['area_id'];

/* ---------------------------------------------------------
   CHECK PERMANENT PRIMARY RECOMMENDER
--------------------------------------------------------- */

$isPrimaryRecommender = false;

$primaryStmt = $conn->prepare("
    SELECT dr.id
    FROM department_recommenders dr
    INNER JOIN users u
        ON u.id = dr.user_id
    WHERE dr.user_id = ?
      AND dr.department_id = ?
      AND u.area_id = ?
      AND u.status = 'active'
    LIMIT 1
");

$primaryStmt->bind_param(
    'iii',
    $userId,
    $userDepartmentId,
    $userAreaId
);

$primaryStmt->execute();

$primaryResult = $primaryStmt->get_result();

if ($primaryResult->fetch_assoc()) {
    $isPrimaryRecommender = true;
}

$primaryStmt->close();

/* ---------------------------------------------------------
   CHECK ACTIVE SECONDARY RECOMMENDER
--------------------------------------------------------- */

$isSecondaryRecommender = false;

if (!$isPrimaryRecommender) {

    $secondaryStmt = $conn->prepare("
        SELECT rd.id
        FROM recommender_delegations rd

        INNER JOIN department_recommenders dr
            ON dr.user_id = rd.primary_recommender_id
           AND dr.department_id = rd.department_id

        INNER JOIN users primary_user
            ON primary_user.id = rd.primary_recommender_id

        INNER JOIN users secondary_user
            ON secondary_user.id = rd.secondary_recommender_id

        WHERE rd.secondary_recommender_id = ?
          AND rd.department_id = ?
          AND secondary_user.area_id = ?
          AND secondary_user.status = 'active'

          AND primary_user.status = 'active'
          AND primary_user.area_id = ?

          AND rd.status = 'active'
          AND CURDATE() BETWEEN rd.start_date AND rd.end_date

        LIMIT 1
    ");

    $secondaryStmt->bind_param(
        'iiii',
        $userId,
        $userDepartmentId,
        $userAreaId,
        $userAreaId
    );

    $secondaryStmt->execute();

    $secondaryResult = $secondaryStmt->get_result();

    if ($secondaryResult->fetch_assoc()) {
        $isSecondaryRecommender = true;
    }

    $secondaryStmt->close();
}

/* ---------------------------------------------------------
   FINAL AUTHORIZATION CHECK
--------------------------------------------------------- */

if (!$isPrimaryRecommender && !$isSecondaryRecommender) {

    /*
     * If the current session identifies this user as a recommender
     * but the database no longer grants recommender authority,
     * the user's recommender session is no longer valid.
     */
    if (!empty($_SESSION['is_recommender'])) {

        session_unset();
        session_destroy();

        echo json_encode([
            'success' => false,
            'logout' => true,
            'message' => 'Your Secondary Recommender delegation has ended. Please log in again.'
        ]);

    } else {

        echo json_encode([
            'success' => false,
            'logout' => false,
            'message' => 'You are not authorized to recommend gas slips.'
        ]);
    }

    exit;
}

/* =========================================================
   GET GAS SLIP CONTEXT
========================================================= */

$ctx = getGasSlipContext(
    $conn,
    $gasSlipId
);

if (!$ctx) {

    echo json_encode([
        'success' => false,
        'message' => 'Gas slip not found.'
    ]);

    exit;
}

$departmentId = (int) $ctx['requester_department_id'];
$areaId       = (int) $ctx['gas_area_id'];
$ownership    = strtolower(
    trim((string) $ctx['ownership'])
);

/* =========================================================
   CHECK RECOMMENDER ONLY APPROVAL

   IMPORTANT:
   Private vehicles are never affected.
========================================================= */

$recommenderOnly = false;

if ($ownership !== 'private') {

    $settingStmt = $conn->prepare("
        SELECT recommender_only
        FROM department_approval_settings
        WHERE department_id = ?
          AND area_id = ?
        LIMIT 1
    ");

    $settingStmt->bind_param(
        'ii',
        $departmentId,
        $areaId
    );

    $settingStmt->execute();

    $settingResult =
        $settingStmt->get_result();

    if ($settingRow =
        $settingResult->fetch_assoc()
    ) {

        $recommenderOnly =
            (int) $settingRow['recommender_only'] === 1;

    }

    $settingStmt->close();
}

/* =========================================================
   RECOMMENDER ONLY APPROVAL
========================================================= */

if ($recommenderOnly) {

    /*
    The recommender becomes the final approver.
    */

    $approverType = 'recommender';

    $stmt = $conn->prepare("
        UPDATE gas_slips
        SET
            status = 'approved',

            recommended_by = ?,
            recommended_at = NOW(),

            approved_by = ?,
            approver_type = ?,
            approved_at = NOW()

        WHERE id = ?
          AND status = 'pending'
    ");

    $stmt->bind_param(
        'iisi',
        $userId,
        $userId,
        $approverType,
        $gasSlipId
    );

    $stmt->execute();

    $affectedRows = $stmt->affected_rows;

    $stmt->close();

    if ($affectedRows > 0) {

        /*
        Final approval notification.
        No approver notification is created.
        */
        notifyGasSlipApproved(
            $conn,
            $gasSlipId
        );

        echo json_encode([
            'success' => true,
            'recommender_only' => true
        ]);

    } else {

        echo json_encode([
            'success' => false,
            'message' =>
                'Gas slip not updated (already processed or invalid state)'
        ]);
    }

    exit;
}

/* =========================================================
   NORMAL RECOMMENDATION WORKFLOW

   This includes:
   - Regular vehicles with Recommender Only OFF
   - ALL private vehicles
========================================================= */

$stmt = $conn->prepare("
    UPDATE gas_slips
    SET
        status = 'recommended',
        recommended_by = ?,
        recommended_at = NOW()
    WHERE id = ?
      AND status = 'pending'
");

$stmt->bind_param(
    'ii',
    $userId,
    $gasSlipId
);

$stmt->execute();

$affectedRows = $stmt->affected_rows;

$stmt->close();

/* =========================================================
   CREATE NOTIFICATIONS
========================================================= */

if ($affectedRows > 0) {

    notifyGasSlipRecommended(
        $conn,
        $gasSlipId
    );

    echo json_encode([
        'success' => true,
        'recommender_only' => false
    ]);

} else {

    echo json_encode([
        'success' => false,
        'message' =>
            'Gas slip not updated (already processed or invalid state)'
    ]);
}
?>