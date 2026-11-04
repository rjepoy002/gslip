<?php
require_once __DIR__ . '/recommender_assignment.php';

/* SQL policies require only the outer gas_slips alias "gs". They can be used
 * in lists, counts and single-record checks without trusting session role flags.
 * Area eligibility never replaces a preparer's selected primary recommender. */
function gasSlipPermissionSql($permission, $userId)
{
    $id = (int) $userId;
    $assigned = assignedRecommenderAccessSql($id, 'policy_preparer');
    $privateApprover = "EXISTS (SELECT 1 FROM approval_global_settings policy_global WHERE policy_global.private_vehicle_approver_user_id = policy_actor.id)";
    $departmentApprover = "EXISTS (SELECT 1 FROM department_approvers policy_da
        WHERE policy_da.user_id = policy_actor.id AND policy_da.department_id = policy_preparer.department_id
          AND policy_actor.department_id = policy_da.department_id)";
    $operationalApprover = "($departmentApprover AND EXISTS (SELECT 1 FROM department_areas policy_scope
        WHERE policy_scope.department_id = policy_preparer.department_id AND policy_scope.area_id = policy_preparer.area_id))";
    $recommend = "(gs.status = 'pending' AND (
        (policy_vehicle.ownership <> 'private' AND $assigned)
        OR (policy_vehicle.ownership = 'private' AND $departmentApprover AND NOT $privateApprover)))";
    $approve = "(gs.status = 'recommended' AND (
        (policy_vehicle.ownership = 'private' AND $privateApprover
            AND EXISTS (SELECT 1 FROM department_approvers policy_private_da
                WHERE policy_private_da.user_id = policy_actor.id AND policy_private_da.department_id = policy_actor.department_id))
        OR (policy_vehicle.ownership = 'coop-owned' AND NOT $privateApprover AND $operationalApprover
            AND NOT EXISTS (SELECT 1 FROM department_approval_settings policy_setting
                WHERE policy_setting.department_id = policy_preparer.department_id AND policy_setting.area_id = gs.area_id AND policy_setting.recommender_only = 1))))";
    $area = recommenderAreaSql('policy_dr.department_id', 'policy_actor.id', 'gs.area_id', 'policy_actor.area_id');
    $currentRecommender = "EXISTS (SELECT 1 FROM department_recommenders policy_dr
        WHERE policy_dr.user_id = policy_actor.id AND policy_dr.department_id = policy_preparer.department_id
          AND policy_actor.department_id = policy_preparer.department_id
          AND ($area OR EXISTS (SELECT 1 FROM departments policy_department
              WHERE policy_department.id = policy_dr.department_id AND UPPER(TRIM(policy_department.name)) NOT IN ('ANOD','ASOD'))))";
    switch ($permission) {
        case 'recommend': $condition = $recommend; break;
        case 'approve': $condition = $approve; break;
        case 'reject': $condition = "($recommend OR $approve)"; break;
        case 'edit': $condition = "(gs.status = 'draft' AND gs.user_id = policy_actor.id)"; break;
        case 'view':
            $condition = "(policy_actor.role = 'admin' OR gs.user_id = policy_actor.id OR (gs.status <> 'draft' AND (
                gs.recommended_by = policy_actor.id OR gs.approved_by = policy_actor.id OR gs.rejected_by = policy_actor.id
                OR $recommend OR $approve
                OR (policy_vehicle.ownership = 'private' AND ($departmentApprover OR $privateApprover))
                OR (policy_vehicle.ownership <> 'private' AND $operationalApprover)
                OR (gs.status IN ('approved','printed') AND $currentRecommender))))";
            break;
        default: throw new InvalidArgumentException('Unknown gas slip permission.');
    }
    return "EXISTS (SELECT 1 FROM users policy_actor
        LEFT JOIN users policy_preparer ON policy_preparer.id = gs.user_id
        LEFT JOIN vehicles policy_vehicle ON policy_vehicle.id = gs.vehicle_id
        WHERE policy_actor.id = $id AND policy_actor.status = 'active' AND $condition)";
}

function canUserAccessGasSlip($conn, $userId, $gasSlipId, $permission = 'view')
{
    $scope = gasSlipPermissionSql($permission, $userId);
    $stmt = $conn->prepare("SELECT 1 FROM gas_slips gs WHERE gs.id = ? AND $scope");
    $stmt->bind_param('i', $gasSlipId);
    $stmt->execute();
    $authorized = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $authorized;
}

function recommenderPendingSlipSql($userId)
{
    $id = (int) $userId;
    $recommend = gasSlipPermissionSql('recommend', $id);
    return "((gs.status = 'pending' AND $recommend)
        OR (gs.status = 'rejected' AND gs.user_id = $id))";
}
