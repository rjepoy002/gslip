<?php

/*
 * Returns the primary recommenders a preparer may select. Secondary
 * recommenders receive delegated access but are never stored as assignments.
 */
function getEligibleRecommendersForPreparer($conn, $preparerId)
{
    $preparerId = (int) $preparerId;

    $preparerStmt = $conn->prepare(
        "SELECT u.department_id, u.area_id
         FROM users u
         WHERE u.id = ?
         LIMIT 1"
    );
    $preparerStmt->bind_param('i', $preparerId);
    $preparerStmt->execute();
    $preparer = $preparerStmt->get_result()->fetch_assoc();
    $preparerStmt->close();

    if (!$preparer) {
        return [];
    }

    $departmentId = (int) $preparer['department_id'];
    $areaId = (int) $preparer['area_id'];
    $recommenders = [];

    $directSql = "
        SELECT DISTINCT u.id,
            TRIM(CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name)) AS full_name
        FROM department_recommenders dr
        INNER JOIN users u ON u.id = dr.user_id
        WHERE dr.department_id = ?
          AND u.status = 'active'
          AND u.area_id = ?
    ";
    $directSql .= ' ORDER BY u.last_name, u.first_name';

    $directStmt = $conn->prepare($directSql);
    $directStmt->bind_param('ii', $departmentId, $areaId);
    $directStmt->execute();
    $directResult = $directStmt->get_result();
    while ($row = $directResult->fetch_assoc()) {
        $recommenders[(int) $row['id']] = $row;
    }
    $directStmt->close();

    return array_values($recommenders);
}

function isEligibleAssignedRecommender($conn, $preparerId, $recommenderId)
{
    $recommenderId = (int) $recommenderId;
    if ($recommenderId <= 0) {
        return false;
    }

    foreach (getEligibleRecommendersForPreparer($conn, $preparerId) as $recommender) {
        if ((int) $recommender['id'] === $recommenderId) {
            return true;
        }
    }

    return false;
}

/* Matches the existing Create Gas Slip navigation rule: department approvers
 * do not have gas-slip creation capability. */
function canUserCreateGasSlip($conn, $userId)
{
    $userId = (int) $userId;

    $stmt = $conn->prepare(
        'SELECT id FROM department_approvers WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $canCreate = $stmt->get_result()->num_rows === 0;
    $stmt->close();

    return $canCreate;
}

/*
 * A slip may be handled by its assigned primary recommender or by that
 * primary's currently active secondary recommender.
 */
function canAccessAssignedRecommender($conn, $preparerId, $recommenderId)
{
    $preparerId = (int) $preparerId;
    $recommenderId = (int) $recommenderId;

    $preparerStmt = $conn->prepare(
        'SELECT department_id, area_id, assigned_recommender_id FROM users WHERE id = ? LIMIT 1'
    );
    $preparerStmt->bind_param('i', $preparerId);
    $preparerStmt->execute();
    $preparer = $preparerStmt->get_result()->fetch_assoc();
    $preparerStmt->close();

    if (!$preparer || empty($preparer['assigned_recommender_id'])) {
        return false;
    }

    $primaryId = (int) $preparer['assigned_recommender_id'];
    if ($primaryId === $recommenderId) {
        return isEligibleAssignedRecommender($conn, $preparerId, $recommenderId);
    }

    $departmentId = (int) $preparer['department_id'];
    $areaId = (int) $preparer['area_id'];
    $delegationStmt = $conn->prepare(
        "SELECT rd.id
         FROM recommender_delegations rd
         INNER JOIN department_recommenders dr
             ON dr.user_id = rd.primary_recommender_id
            AND dr.department_id = rd.department_id
         INNER JOIN users pu ON pu.id = rd.primary_recommender_id
         INNER JOIN users su ON su.id = rd.secondary_recommender_id
         WHERE rd.primary_recommender_id = ?
           AND rd.secondary_recommender_id = ?
           AND rd.department_id = ?
           AND rd.status = 'active'
           AND CURDATE() BETWEEN rd.start_date AND rd.end_date
           AND pu.status = 'active'
           AND pu.area_id = ?
           AND su.status = 'active'
           AND su.department_id = ?
           AND su.area_id = ?
         LIMIT 1"
    );
    $delegationStmt->bind_param(
        'iiiiii',
        $primaryId,
        $recommenderId,
        $departmentId,
        $areaId,
        $departmentId,
        $areaId
    );
    $delegationStmt->execute();
    $hasAccess = $delegationStmt->get_result()->num_rows > 0;
    $delegationStmt->close();

    return $hasAccess;
}
