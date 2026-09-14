<?php
$conn = getDBConnection();

$userId     = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area       = $_SESSION['area'];

/* =========================================================
   LOAD AREAS AND RECOMMENDER ONLY SETTINGS
   FOR CURRENT DEPARTMENT
========================================================= */

$departmentAreas = [];

$departmentAreaStmt = $conn->prepare("
    SELECT
        a.id,
        a.area_name,

        COALESCE(
            das.recommender_only,
            0
        ) AS recommender_only

    FROM department_areas da

    INNER JOIN areas a
        ON a.id = da.area_id

    LEFT JOIN department_approval_settings das
        ON das.department_id = da.department_id
        AND das.area_id = da.area_id

    WHERE da.department_id = ?
      AND a.status = 'active'

    ORDER BY a.area_name ASC
");

$departmentAreaStmt->bind_param(
    "i",
    $department
);

$departmentAreaStmt->execute();

$departmentAreaResult =
    $departmentAreaStmt->get_result();

while ($row = $departmentAreaResult->fetch_assoc()) {

    $departmentAreas[] = [
        'id' => (int)$row['id'],

        'area_name' => $row['area_name'],

        'recommender_only' =>
            (int)$row['recommender_only']
    ];
}

$departmentAreaStmt->close();

/* =========================================================
   LOAD CURRENT APPROVERS
========================================================= */

$currentApprovers = [];

$approverStmt = $conn->prepare("
    SELECT
        user_id,
        is_primary
    FROM department_approvers
    WHERE department_id = ?
");

$approverStmt->bind_param("i", $department);
$approverStmt->execute();

$approverResult = $approverStmt->get_result();

while ($row = $approverResult->fetch_assoc()) {
    $currentApprovers[] = [
        'user_id'    => (int)$row['user_id'],
        'is_primary' => (int)$row['is_primary']
    ];
}

$approverStmt->close();
                                    
/* =========================================================
   IDENTIFY PRIMARY AND SECONDARY APPROVERS
========================================================= */

$primaryApproverId = null;
$secondaryApproverId = null;

foreach ($currentApprovers as $approver) {

    if ((int)$approver['is_primary'] === 1) {

        $primaryApproverId = (int)$approver['user_id'];

    } else {

        $secondaryApproverId = (int)$approver['user_id'];

    }
}


/* =========================================================
   LOAD CURRENT PRIVATE VEHICLE APPROVERS
========================================================= */

$privateVehicleApproverId = null;
$privateVehicleApproverName = 'Not Assigned';

$result = $conn->query("
    SELECT 
        ags.private_vehicle_approver_user_id,

        CONCAT(
            u.first_name,
            ' ',
            COALESCE(u.middle_name, ''),
            ' ',
            u.last_name
        ) AS full_name

    FROM approval_global_settings ags

    LEFT JOIN users u
        ON u.id = ags.private_vehicle_approver_user_id

    LIMIT 1
");

if ($result && $row = $result->fetch_assoc()) {
    $privateVehicleApproverId = $row['private_vehicle_approver_user_id'];
    $privateVehicleApproverName = trim($row['full_name']) ?: 'Not Assigned';
}

/* =========================================================
   LOAD ALL SYSTEM APPROVERS
========================================================= */

$allApprovers = [];

$allApproverStmt = $conn->prepare("
    SELECT DISTINCT
        u.*

    FROM users u

    INNER JOIN department_approvers da
        ON da.user_id = u.id

    ORDER BY
        u.first_name ASC,
        u.last_name ASC
");

$allApproverStmt->execute();

$allApproverResult = $allApproverStmt->get_result();

while ($row = $allApproverResult->fetch_assoc()) {

    $allApprovers[] = $row;
}

$allApproverStmt->close();

/* =========================================================
   LOAD ALL DEPARTMENT APPROVERS
========================================================= */

$departmentApprovers = [];

$departmentApproverStmt = $conn->prepare("
    SELECT
        da.department_id,
        da.user_id,

        u.first_name,
        u.middle_name,
        u.last_name,
        u.designation

    FROM department_approvers da

    INNER JOIN users u
        ON u.id = da.user_id

    WHERE da.department_id IS NOT NULL
");

$departmentApproverStmt->execute();

$departmentApproverResult =
    $departmentApproverStmt->get_result();

while ($row = $departmentApproverResult->fetch_assoc()) {

    $departmentApprovers[
        $row['department_id']
    ] = [

        'user_id' => $row['user_id'],

        'full_name' => formatUserDisplayName($row),

        'designation' => $row['designation']

    ];
}

$departmentApproverStmt->close();

/* =========================================================
   LOAD ALL USERS FOR ADMIN APPROVER SETTINGS
========================================================= */

$allUsers = [];

$allUsersStmt = $conn->prepare("
    SELECT
        u.id,
        u.department_id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.designation,
        d.name AS department_name

    FROM users u

    LEFT JOIN departments d
        ON d.id = u.department_id

    WHERE u.status = 'active'

    ORDER BY
        d.name ASC,
        u.last_name ASC,
        u.first_name ASC
");

$allUsersStmt->execute();

$allUsersResult =
    $allUsersStmt->get_result();

while ($row = $allUsersResult->fetch_assoc()) {

    $allUsers[] = $row;
}

$allUsersStmt->close();

?>