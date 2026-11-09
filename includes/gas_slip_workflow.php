<?php
// Shared workflow implementations; HTTP guards and individual UI remain in their original endpoints.
require_once __DIR__ . '/gas_slip_access.php';

function recommendGasSlipWorkflow($conn, $gasSlipId, $userId, $departmentId = 0, $bulkTransaction = false) {
$recommendScope = gasSlipPermissionSql('recommend', $userId);
if (!$bulkTransaction) { $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); }
if (!$bulkTransaction) { $conn->begin_transaction(); }
if (!$bulkTransaction) { register_shutdown_function(function () use ($conn) { try { $conn->rollback(); } catch (Throwable $e) {} }); }
$lockStmt = $conn->prepare('SELECT u.department_id FROM gas_slips gs JOIN users u ON u.id = gs.user_id WHERE gs.id = ?');
$lockStmt->bind_param('i', $gasSlipId);
$lockStmt->execute();
$lockDepartment = (int) ($lockStmt->get_result()->fetch_assoc()['department_id'] ?? 0);
lockRecommenderDepartment($conn, $lockDepartment);

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
    return [
        'success' => false,
        'message' => 'Unauthorized recommender.'
    ];
    
}

$userDepartmentId = (int) $currentUser['department_id'];
$userAreaId       = (int) $currentUser['area_id'];

/* =========================================================
   GET GAS SLIP CONTEXT BEFORE CHOOSING THE WORKFLOW
========================================================= */

$ctx = getGasSlipContext(
    $conn,
    $gasSlipId
);

if (!$ctx) {

    return [
        'success' => false,
        'message' => 'Gas slip not found.'
    ];

    
}

$departmentId = (int) $ctx['requester_department_id'];
$areaId       = (int) $ctx['gas_area_id'];
$ownership    = strtolower(
    trim((string) $ctx['ownership'])
);

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

   Private vehicles use the Approver -> Private Approver workflow.
   Coop-owned vehicles retain the assigned recommender workflow.
--------------------------------------------------------- */

if ($ownership === 'private') {

    $isAuthorizedPrivateRecommender = false;

    /*
     * A private-vehicle recommendation must come from a current,
     * database-assigned department approver. The configured Private
     * Approver is reserved for the final approval step.
     */
    if ($userDepartmentId === $departmentId) {

        $privateWorkflowStmt = $conn->prepare("
            SELECT da.id
            FROM department_approvers da
            WHERE da.user_id = ?
              AND da.department_id = ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM approval_global_settings ags
                  WHERE ags.private_vehicle_approver_user_id = da.user_id
              )
            LIMIT 1
        ");

        $privateWorkflowStmt->bind_param(
            'ii',
            $userId,
            $departmentId
        );

        $privateWorkflowStmt->execute();
        $isAuthorizedPrivateRecommender =
            $privateWorkflowStmt->get_result()->num_rows > 0;
        $privateWorkflowStmt->close();
    }

    if (!$isAuthorizedPrivateRecommender) {
        return [
            'success' => false,
            'logout' => false,
            'message' => 'You are not authorized to recommend private vehicle gas slips.'
        ];
        
    }

} elseif (!$isPrimaryRecommender && !$isSecondaryRecommender) {

    /*
     * If the current session identifies this user as a recommender
     * but the database no longer grants recommender authority,
     * the user's recommender session is no longer valid.
     */
    if (!empty($_SESSION['is_recommender'])) {

        session_unset();
        session_destroy();

        return [
            'success' => false,
            'logout' => true,
            'message' => 'Your Secondary Recommender delegation has ended. Please log in again.'
        ];

    } else {

        return [
            'success' => false,
            'logout' => false,
            'message' => 'You are not authorized to recommend gas slips.'
        ];
    }

    
}

if (
    $ownership !== 'private'
    && !canAccessAssignedRecommender($conn, (int) $ctx['requester_id'], $userId)
) {
    return [
        'success' => false,
        'message' => 'You are not the assigned recommender for this gas slip.'
    ];
    
}

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
        UPDATE gas_slips gs
        SET
            status = 'approved',

            recommended_by = ?,
            recommended_at = NOW(),

            approved_by = ?,
            approver_type = ?,
            approved_at = NOW()

        WHERE id = ?
          AND status = 'pending' AND $recommendScope
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

    if (!$bulkTransaction) { $conn->commit(); }

    if ($affectedRows > 0) {

        /*
        Final approval notification.
        No approver notification is created.
        */
        notifyGasSlipApproved(
            $conn,
            $gasSlipId
        );

        return [
            'success' => true,
            'recommender_only' => true
        ];

    } else {

        return [
            'success' => false,
            'message' =>
                'Gas slip not updated (already processed or invalid state)'
        ];
    }

    
}

/* =========================================================
   NORMAL RECOMMENDATION WORKFLOW

   This includes:
   - Regular vehicles with Recommender Only OFF
   - ALL private vehicles
========================================================= */

$stmt = $conn->prepare("
    UPDATE gas_slips gs
    SET
        status = 'recommended',
        recommended_by = ?,
        recommended_at = NOW()
    WHERE id = ?
      AND status = 'pending' AND $recommendScope
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

if (!$bulkTransaction) { $conn->commit(); }

if ($affectedRows > 0) {

    notifyGasSlipRecommended(
        $conn,
        $gasSlipId
    );

    return [
        'success' => true,
        'recommender_only' => false
    ];

} else {

    return [
        'success' => false,
        'message' =>
            'Gas slip not updated (already processed or invalid state)'
    ];
}

}

function approveGasSlipWorkflow($conn, $gasSlipId, $userId, $departmentId = 0, $bulkTransaction = false) {
$approveScope = gasSlipPermissionSql('approve', $userId);

// The persisted recommended stage is authoritative. Current recommender-only
// settings apply when creating recommendations, not retroactively to this stage.
if (!canUserAccessGasSlip($conn, $userId, $gasSlipId, 'approve')) {
    return ['success'=>false,'message'=>'You are not authorized to approve this recommendation. An independent approver is required.'];
}

/* =========================================================
   DETERMINE APPROVER TYPE
========================================================= */

$stmt = $conn->prepare("
    SELECT is_primary
    FROM department_approvers
    WHERE department_id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->bind_param(
    'ii',
    $departmentId,
    $userId
);

$stmt->execute();

$result = $stmt->get_result();

if (!$approver = $result->fetch_assoc()) {

    $stmt->close();

    return [
        'success' => false,
        'message' => 'You are not authorized to approve this gas slip.'
    ];

    
}

$stmt->close();

/* =========================================================
   SET APPROVER TYPE
========================================================= */

$approverType =
    ((int) $approver['is_primary'] === 1)
        ? 'primary'
        : 'secondary';

/* =========================================================
   UPDATE GAS SLIP STATUS
========================================================= */

$stmt = $conn->prepare("
    UPDATE gas_slips gs
    SET
        status = 'approved',
        approved_by = ?,
        approver_type = ?,
        approved_at = NOW()
    WHERE id = ?
      AND status = 'recommended' AND $approveScope
");

$stmt->bind_param(
    'isi',
    $userId,
    $approverType,
    $gasSlipId
);

$stmt->execute();

$affectedRows = $stmt->affected_rows;

$stmt->close();

/* =========================================================
   CREATE NOTIFICATIONS
========================================================= */

if ($affectedRows > 0) {

    notifyGasSlipApproved(
        $conn,
        $gasSlipId
    );

    return [
        'success' => true
    ];

} else {

    return [
        'success' => false,
        'message' =>
            'Gas slip not updated (already processed or invalid state)'
    ];
}


}
