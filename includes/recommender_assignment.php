<?php
require_once __DIR__ . "/recommender_areas.php";

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

    $areaEligibility = recommenderAreaSql("dr.department_id", "u.id", (string) $areaId);
    $directSql = "
        SELECT DISTINCT u.id,
            TRIM(CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name)) AS full_name
        FROM department_recommenders dr
        INNER JOIN users u ON u.id = dr.user_id
        WHERE dr.department_id = ?
          AND u.status = 'active'
          AND u.department_id = dr.department_id
          AND $areaEligibility
    ";
    $directSql .= ' ORDER BY u.last_name, u.first_name';

    $directStmt = $conn->prepare($directSql);
    $directStmt->bind_param('i', $departmentId);
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

/*
 * Returns the preparer's assigned primary recommender's currently active
 * delegated secondary recommender. The delegation must still match the
 * preparer's department and area, just as it does for recommendation access.
 */
function getActiveSecondaryRecommenderId($conn, $preparerId, $primaryRecommenderId)
{
    $preparerId = (int) $preparerId;
    $primaryRecommenderId = (int) $primaryRecommenderId;

    if ($preparerId <= 0 || $primaryRecommenderId <= 0) {
        return 0;
    }

    $preparerStmt = $conn->prepare(
        'SELECT department_id, area_id, assigned_recommender_id FROM users WHERE id = ? LIMIT 1'
    );
    $preparerStmt->bind_param('i', $preparerId);
    $preparerStmt->execute();
    $preparer = $preparerStmt->get_result()->fetch_assoc();
    $preparerStmt->close();

    if (!$preparer
        || (int) $preparer['assigned_recommender_id'] !== $primaryRecommenderId
        || !isEligibleAssignedRecommender($conn, $preparerId, $primaryRecommenderId)) {
        return 0;
    }

    $departmentId = (int) $preparer['department_id'];
    $areaId = (int) $preparer['area_id'];
    $delegationStmt = $conn->prepare(
        "SELECT rd.secondary_recommender_id
         FROM recommender_delegations rd
         INNER JOIN department_recommenders dr
             ON dr.user_id = rd.primary_recommender_id
            AND dr.department_id = rd.department_id
         INNER JOIN users pu ON pu.id = rd.primary_recommender_id
         INNER JOIN users su ON su.id = rd.secondary_recommender_id
         WHERE rd.primary_recommender_id = ?
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
        'iiiii',
        $primaryRecommenderId,
        $departmentId,
        $areaId,
        $departmentId,
        $areaId
    );
    $delegationStmt->execute();
    $delegation = $delegationStmt->get_result()->fetch_assoc();
    $delegationStmt->close();

    return $delegation ? (int) $delegation['secondary_recommender_id'] : 0;
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
    $scope = assignedRecommenderAccessSql($recommenderId);
    $preparerStmt = $conn->prepare("SELECT 1 FROM users u WHERE u.id = ? AND $scope LIMIT 1");
    $preparerStmt->bind_param('i', $preparerId);
    $preparerStmt->execute();
    $authorized = $preparerStmt->get_result()->num_rows > 0;
    $preparerStmt->close();
    return $authorized;
}

/* Same primary eligibility as the preparer dropdown; delegation keeps its
 * original same-area restrictions. IDs are cast before SQL interpolation. */
function assignedRecommenderAccessSql($recommenderId, $preparer = 'u')
{
    $id = (int) $recommenderId;
    $area = recommenderAreaSql('access_dr.department_id', 'access_primary.id', "$preparer.area_id", 'access_primary.area_id');
    return "EXISTS (SELECT 1 FROM department_recommenders access_dr
        JOIN users access_primary ON access_primary.id = access_dr.user_id
        WHERE access_dr.department_id = $preparer.department_id
          AND access_primary.id = $preparer.assigned_recommender_id
          AND access_primary.department_id = $preparer.department_id
          AND access_primary.status = 'active' AND $area
          AND (access_primary.id = $id OR $id = (
              SELECT access_rd.secondary_recommender_id FROM recommender_delegations access_rd
              JOIN users access_secondary ON access_secondary.id = access_rd.secondary_recommender_id
              WHERE access_rd.primary_recommender_id = access_primary.id
                AND access_rd.department_id = $preparer.department_id
                AND access_rd.status = 'active'
                AND CURDATE() BETWEEN access_rd.start_date AND access_rd.end_date
                AND access_primary.area_id = $preparer.area_id
                AND access_secondary.department_id = $preparer.department_id
                AND access_secondary.area_id = $preparer.area_id
                AND access_secondary.status = 'active'
              LIMIT 1
          )))";
}
