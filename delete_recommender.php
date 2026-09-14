<?php
session_start();
require_once 'includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

$conn = getDBConnection();

/* =========================================================
   AUTH GUARD
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id'])
) {

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

$approverId  = (int) $_SESSION['user_id'];
$departmentId = (int) $_SESSION['department_id'];

/* =========================================================
   VALIDATE REQUEST
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}

/* =========================================================
   FORM VALUES
========================================================= */

$userId = !empty($_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;

/* =========================================================
   VALIDATE INPUT
========================================================= */

if ($userId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid user.'
    ]);

    exit;
}

/* =========================================================
   REMOVE RECOMMENDER + CANCEL DELEGATION
========================================================= */

try {

    $conn->begin_transaction();

    /* -----------------------------------------------------
       1. REMOVE PRIMARY RECOMMENDER
    ----------------------------------------------------- */

    $stmt = $conn->prepare("
        DELETE FROM department_recommenders
        WHERE department_id = ?
        AND user_id = ?
    ");

    $stmt->bind_param(
        "ii",
        $departmentId,
        $userId
    );

    $stmt->execute();

    $removed = $stmt->affected_rows;

    $stmt->close();

    /* -----------------------------------------------------
       2. IF REMOVED, CANCEL ACTIVE/FUTURE DELEGATIONS
          CREATED BY THIS PRIMARY RECOMMENDER
    ----------------------------------------------------- */

    if ($removed > 0) {

        $stmt = $conn->prepare("
            UPDATE recommender_delegations
            SET
                status = 'cancelled',
                cancelled_by = ?,
                cancelled_at = NOW()
            WHERE primary_recommender_id = ?
            AND department_id = ?
            AND status = 'active'
            AND end_date >= CURDATE()
        ");

        $stmt->bind_param(
            "iii",
            $approverId,
            $userId,
            $departmentId
        );

        $stmt->execute();

        $stmt->close();
    }

    /* -----------------------------------------------------
       3. COMMIT
    ----------------------------------------------------- */

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Recommender removed successfully.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>