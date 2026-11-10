<?php
// Shared session hydration retains the existing workflow/delegation checks.
function establishLoginSession(mysqli $conn, array $row, string $sessionToken): void
{
    session_regenerate_id(true);
    $isPrivateApprover = false;
    $middleInitial = '';

    if (!empty($row['middle_name'])) {
        $middleInitial = strtoupper(substr(trim($row['middle_name']), 0, 1)) . '. ';
    }

    /* =========================================================
      DETERMINE WORKFLOW ACCESS
    ========================================================= */

    $isRecommender = false;
    $isApprover    = false;

    /* ---------- CHECK RECOMMENDER ---------- */

    $recStmt = $conn->prepare("
        SELECT 1
        FROM department_recommenders dr
        INNER JOIN users u
            ON u.id = dr.user_id
        WHERE dr.user_id = ?
          AND dr.department_id = ?
          AND u.area_id = ?
          AND u.status = 'active'

        UNION

        SELECT 1
        FROM recommender_delegations rd
        INNER JOIN department_recommenders dr
            ON dr.user_id = rd.primary_recommender_id
          AND dr.department_id = rd.department_id
        INNER JOIN users primary_user
            ON primary_user.id = rd.primary_recommender_id
        WHERE rd.secondary_recommender_id = ?
          AND rd.department_id = ?
          AND rd.status = 'active'
          AND CURDATE() BETWEEN rd.start_date AND rd.end_date
          AND primary_user.status = 'active'
          AND primary_user.area_id = ?
        LIMIT 1
    ");

    $recStmt->bind_param(
        "iiiiii",
        $row['id'],
        $row['department_id'],
        $row['area_id'],
        $row['id'],
        $row['department_id'],
        $row['area_id']
    );

    $recStmt->execute();

    $recResult = $recStmt->get_result();

    if ($recResult->num_rows > 0) {
        $isRecommender = true;
    }

    $recStmt->close();

    /* ---------- CHECK APPROVER ---------- */

    $appStmt = $conn->prepare("
        SELECT id
        FROM department_approvers
        WHERE user_id = ?
        LIMIT 1
    ");

    $appStmt->bind_param("i", $row['id']);
    $appStmt->execute();

    $appResult = $appStmt->get_result();

    if ($appResult->num_rows > 0) {
        $isApprover = true;
    }

    $appStmt->close();

    /* ---------- CHECK PRIVATE APPROVER ---------- */

    $pappStmt = $conn->prepare("
        SELECT id
        FROM approval_global_settings
        WHERE private_vehicle_approver_user_id = ?
        LIMIT 1
    ");

    $pappStmt->bind_param("i", $row['id']);
    $pappStmt->execute();

    $pappResult = $pappStmt->get_result();

    if ($pappResult->num_rows > 0) {
        $isPrivateApprover = true;
    }

    $pappStmt->close();

    /* =========================================================
      STORE SESSION DATA
    ========================================================= */

    $_SESSION['role'] = $row['role'] ?? ''; // admin/user only

    $_SESSION['is_recommender'] = $isRecommender;
    $_SESSION['is_approver']    = $isApprover;
    $_SESSION['is_private_approver']    = $isPrivateApprover;

    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['fullname'] = $row['first_name'] . ' ' . $middleInitial . ' ' . $row['last_name'];
    $_SESSION['area'] = $row['area_id'];
    $_SESSION['session_token'] = $sessionToken;
    $_SESSION['department_id'] = $row['department_id'];
    $_SESSION['designation'] = $row['designation'] ?? '';

}
