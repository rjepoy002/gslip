<?php

/*
 * Department Recommenders are configured department-wide. A candidate's area
 * is shown for clarity, but it must not restrict the department candidate
 * pool. A non-admin manager is still verified against their own authorized
 * department and operational area.
 */

function isConfiguredDepartment($conn, $departmentId)
{
    $departmentId = (int) $departmentId;

    if ($departmentId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('SELECT 1 FROM departments WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $configured = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $configured;
}

function canManageDepartmentRecommenders($conn, $managerId, $role, $departmentId)
{
    $managerId = (int) $managerId;
    $departmentId = (int) $departmentId;

    if (!isConfiguredDepartment($conn, $departmentId)) {
        return false;
    }

    if ($role === 'admin') {
        return true;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM department_approvers da
         INNER JOIN users u ON u.id = da.user_id
         WHERE da.department_id = ?
           AND da.user_id = ?
           AND da.is_primary = 1
           AND u.department_id = ?
           AND u.status = 'active'
           AND EXISTS (
               SELECT 1
               FROM department_areas da_scope
               WHERE da_scope.department_id = da.department_id
                 AND da_scope.area_id = u.area_id
           )
         LIMIT 1"
    );
    $stmt->bind_param('iii', $departmentId, $managerId, $departmentId);
    $stmt->execute();
    $authorized = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $authorized;
}

function getEligiblePrimaryRecommenderCandidates($conn, $departmentId)
{
    $departmentId = (int) $departmentId;
    $candidates = [];

    if ($departmentId <= 0) {
        return $candidates;
    }

    $stmt = $conn->prepare(
        "SELECT
            u.id,
            u.first_name,
            u.middle_name,
            u.last_name,
            u.designation,
            a.area_name
         FROM users u
         LEFT JOIN areas a ON a.id = u.area_id
         WHERE u.status = 'active'
           AND u.department_id = ?
           AND NOT EXISTS (
               SELECT 1
               FROM department_recommenders dr
               WHERE dr.department_id = ?
                 AND dr.user_id = u.id
           )
           /* Primary and secondary approvers are department-wide. */
           AND NOT EXISTS (
               SELECT 1
               FROM department_approvers da
               WHERE da.department_id = ?
                 AND da.user_id = u.id
           )
           /* Preserve the current active/date-range delegation rule. */
           AND NOT EXISTS (
               SELECT 1
               FROM recommender_delegations rd
               WHERE rd.secondary_recommender_id = u.id
                 AND rd.department_id = ?
                 AND rd.status = 'active'
                 AND CURDATE() BETWEEN rd.start_date AND rd.end_date
           )
         ORDER BY u.last_name ASC, u.first_name ASC"
    );
    $stmt->bind_param(
        'iiii',
        $departmentId,
        $departmentId,
        $departmentId,
        $departmentId
    );
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }

    $stmt->close();

    return $candidates;
}

function isEligiblePrimaryRecommenderCandidate($conn, $userId, $departmentId)
{
    $userId = (int) $userId;

    foreach (getEligiblePrimaryRecommenderCandidates($conn, $departmentId) as $candidate) {
        if ((int) $candidate['id'] === $userId) {
            return true;
        }
    }

    return false;
}
