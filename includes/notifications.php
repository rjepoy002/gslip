<?php
require_once 'includes/config.php';

$conn = getDBConnection();

/* =========================================================
   BASIC GUARDS
========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

if (!isset($_SESSION['user_id'])) {
    die('Not logged in');
}

if (!function_exists('createNotification')) {

    function createNotification($conn, $userId, $gasSlipId, $type, $title, $message)
    {
        $userId    = (int)$userId;
        $gasSlipId = (int)$gasSlipId;

        if ($userId <= 0 || $gasSlipId <= 0 || $type === '') {
            return false;
        }

        // Prevent duplicate notifications for the same event
        $checkSql = "SELECT id
                     FROM notifications
                     WHERE user_id = ? AND gas_slip_id = ? AND type = ?
                     LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            return false;
        }

        $checkStmt->bind_param("iis", $userId, $gasSlipId, $type);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult && $checkResult->num_rows > 0) {
            $checkStmt->close();
            return true;
        }
        $checkStmt->close();

        $insertSql = "INSERT INTO notifications
                        (user_id, gas_slip_id, type, title, message, is_read, created_at)
                      VALUES
                        (?, ?, ?, ?, ?, 0, NOW())";

        $stmt = $conn->prepare($insertSql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("iisss", $userId, $gasSlipId, $type, $title, $message);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('getGasSlipContext')) {

    function getGasSlipContext($conn, $gasSlipId)
    {
        $gasSlipId = (int)$gasSlipId;

        $sql = "
            SELECT
                gs.id,
                gs.gas_slip_id AS slip_code,
                gs.user_id AS requester_id,
                gs.area_id AS gas_area_id,
                gs.vehicle_id,
                COALESCE(v.ownership, '') AS ownership,
                COALESCE(u.department_id, 0) AS requester_department_id,
                COALESCE(d.name, '') AS requester_department_name
            FROM gas_slips gs
            LEFT JOIN vehicles v ON v.id = gs.vehicle_id
            LEFT JOIN users u ON u.id = gs.user_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE gs.id = {$gasSlipId}
            LIMIT 1
        ";

        $result = $conn->query($sql);
        if (!$result || $result->num_rows === 0) {
            return false;
        }

        return $result->fetch_assoc();
    }
}

if (!function_exists('getDepartmentRecommenders')) {

    function getDepartmentRecommenders($conn, $departmentId)
    {
        $departmentId = (int)$departmentId;

        $sql = "
            SELECT DISTINCT dr.user_id
            FROM department_recommenders dr
            INNER JOIN users u ON u.id = dr.user_id
            WHERE dr.department_id = {$departmentId}
        ";

        $result = $conn->query($sql);
        if (!$result) {
            return array();
        }

        $ids = array();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['user_id'];
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('getAreaRecommenders')) {

    function getAreaRecommenders($conn, $departmentId, $areaId)
    {
        $departmentId = (int)$departmentId;
        $areaId       = (int)$areaId;

        $sql = "
            SELECT DISTINCT dr.user_id
            FROM department_recommenders dr
            INNER JOIN users u ON u.id = dr.user_id
            WHERE dr.department_id = {$departmentId}
              AND u.area_id = {$areaId}
        ";

        $result = $conn->query($sql);
        if (!$result) {
            return array();
        }

        $ids = array();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['user_id'];
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('getDepartmentApprovers')) {

    function getDepartmentApprovers($conn, $departmentId)
    {
        $departmentId = (int)$departmentId;

        $sql = "
            SELECT DISTINCT da.user_id
            FROM department_approvers da
            WHERE da.department_id = {$departmentId}
        ";

        $result = $conn->query($sql);
        if (!$result) {
            return array();
        }

        $ids = array();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['user_id'];
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('getPrivateVehicleApproverId')) {

    function getPrivateVehicleApproverId($conn)
    {
        $sql = "
            SELECT private_vehicle_approver_user_id
            FROM approval_global_settings
            ORDER BY id ASC
            LIMIT 1
        ";

        $result = $conn->query($sql);
        if (!$result || $result->num_rows === 0) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['private_vehicle_approver_user_id'] ?? 0);
    }
}

if (!function_exists('notifyGasSlipCreated')) {

    function notifyGasSlipCreated($conn, $gasSlipId)
    {
        $ctx = getGasSlipContext($conn, $gasSlipId);
        if (!$ctx) {
            return false;
        }

        $gasSlipId      = (int)$ctx['id'];
        $slipCode       = trim((string)($ctx['slip_code'] ?: ('#' . $gasSlipId)));
        $requesterId    = (int)$ctx['requester_id'];
        $deptId         = (int)$ctx['requester_department_id'];
        $deptName       = strtoupper(trim((string)$ctx['requester_department_name']));
        $areaId         = (int)$ctx['gas_area_id'];
        $ownership      = strtolower(trim((string)$ctx['ownership']));

        $recipients = array();
        $title      = '';
        $message    = '';
        $type       = 'new_pending';

        if ($ownership === 'private') {
            // Private vehicle: department approvers become the first recipients/recommenders
            $recipients = getDepartmentApprovers($conn, $deptId);
            $title   = 'Private Vehicle Gas Slip';
            $message = 'Gas Slip ' . $slipCode . ' is awaiting your recommendation.';
        } else {
            // Coop vehicle: recommender routing depends on department
            if ($deptName === 'ASOD' || $deptName === 'ANOD') {
                $recipients = getAreaRecommenders($conn, $deptId, $areaId);
            } else {
                $recipients = getDepartmentRecommenders($conn, $deptId);
            }

            $title   = 'New Gas Slip Pending Recommendation';
            $message = 'Gas Slip ' . $slipCode . ' is awaiting your recommendation.';
        }

        if (empty($recipients)) {
            return true;
        }

        foreach ($recipients as $userId) {
            createNotification(
                $conn,
                $userId,
                $gasSlipId,
                $type,
                $title,
                $message
            );
        }

        return true;
    }
}

if (!function_exists('notifyGasSlipRecommended')) {

    function notifyGasSlipRecommended($conn, $gasSlipId)
    {
        $ctx = getGasSlipContext($conn, $gasSlipId);
        if (!$ctx) {
            return false;
        }

        $gasSlipId   = (int)$ctx['id'];
        $slipCode    = trim((string)($ctx['slip_code'] ?: ('#' . $gasSlipId)));
        $deptId      = (int)$ctx['requester_department_id'];
        $ownership   = strtolower(trim((string)$ctx['ownership']));

        $recipients = array();
        $title      = '';
        $message    = '';
        $type       = 'recommended';

        if ($ownership === 'private') {
            $approverId = getPrivateVehicleApproverId($conn);
            if ($approverId > 0) {
                $recipients[] = $approverId;
            }
            $title   = 'Private Vehicle Awaiting Approval';
            $message = 'Gas Slip ' . $slipCode . ' is awaiting final approval.';
        } else {
            $recipients = getDepartmentApprovers($conn, $deptId);
            $title   = 'Gas Slip Awaiting Approval';
            $message = 'Gas Slip ' . $slipCode . ' is awaiting your approval.';
        }

        if (empty($recipients)) {
            return true;
        }

        foreach ($recipients as $userId) {
            createNotification(
                $conn,
                $userId,
                $gasSlipId,
                $type,
                $title,
                $message
            );
        }

        return true;
    }
}

if (!function_exists('notifyGasSlipApproved')) {

    function notifyGasSlipApproved($conn, $gasSlipId)
    {
        $ctx = getGasSlipContext($conn, $gasSlipId);
        if (!$ctx) {
            return false;
        }

        $gasSlipId  = (int)$ctx['id'];
        $slipCode   = trim((string)($ctx['slip_code'] ?: ('#' . $gasSlipId)));
        $requesterId = (int)$ctx['requester_id'];

        return createNotification(
            $conn,
            $requesterId,
            $gasSlipId,
            'approved',
            'Gas Slip Approved',
            'Gas Slip ' . $slipCode . ' has been approved.'
        );
    }
}

if (!function_exists('notifyGasSlipRejected')) {

    function notifyGasSlipRejected($conn, $gasSlipId)
    {
        $ctx = getGasSlipContext($conn, $gasSlipId);
        if (!$ctx) {
            return false;
        }

        $gasSlipId   = (int)$ctx['id'];
        $slipCode    = trim((string)($ctx['slip_code'] ?: ('#' . $gasSlipId)));
        $requesterId = (int)$ctx['requester_id'];

        return createNotification(
            $conn,
            $requesterId,
            $gasSlipId,
            'rejected',
            'Gas Slip Rejected',
            'Gas Slip ' . $slipCode . ' has been rejected.'
        );
    }
}