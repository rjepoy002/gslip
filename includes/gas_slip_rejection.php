<?php
require_once __DIR__ . '/gas_slip_access.php';
function rejectGasSlipWorkflow($conn, $gasSlipId, $userId, $rejectionReason) {
    if (trim($rejectionReason) === '') { return ['success'=>false,'message'=>'Please provide a reason for rejecting the gas slip.']; }
    $rejectScope = gasSlipPermissionSql('reject', $userId);
    $update = $conn->prepare("UPDATE gas_slips gs SET status='rejected', rejected_by=?, rejected_at=NOW(), rejection_reason=? WHERE id=? AND $rejectScope");
    $update->bind_param('isi',$userId,$rejectionReason,$gasSlipId);
    if (!$update->execute() || $update->affected_rows === 0) { return ['success'=>false,'message'=>'Failed to reject gas slip.']; }
    notifyGasSlipRejected($conn,$gasSlipId);
    return ['success'=>true];
}
