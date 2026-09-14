<?php 
$conn = getDBConnection();
// 🔐 AUTH GUARD
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id']) ||
    !isset($_SESSION['area'])
) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role     = $_SESSION['role'] ?? '';
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);
/* =========================================================
   FETCH PENDING GAS SLIPS (FIXED)
========================================================= */
$conn->begin_transaction();

if($isRecommender){

    /* TOTAL RECORDS */
    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM gas_slips gs
        LEFT JOIN users u ON u.id = gs.user_id
        LEFT JOIN vehicles v ON v.id = gs.vehicle_id
        WHERE u.department_id = ?
        AND u.area_id = ?
        AND gs.status = 'pending'
        AND v.ownership <> 'private'
    ");
    $countStmt->bind_param('ii', $department, $area);
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

    /* MAIN QUERY */
    $stmt = $conn->prepare("
      SELECT 
          gs.id AS gs_id,
          gs.gas_slip_id,
          gs.date_issued,
          gs.requested_by,
          gs.purpose,
          gs.status,
          gs.validity_until,
          gs.printed_at,
          CONCAT(
            MIN(r.origin),
            ' → ',
            GROUP_CONCAT(
              r.destination
              ORDER BY gsr.id
              SEPARATOR ' → '
            )
          ) AS route_path
          
      FROM gas_slips gs 
      LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
      LEFT JOIN routes r ON r.id = gsr.route_id
      LEFT JOIN users u ON u.id = gs.user_id
      LEFT JOIN vehicles v ON v.id = gs.vehicle_id

      WHERE u.department_id = ? 
      AND u.area_id = ?
      AND gs.status = 'pending'
      AND (
            v.ownership <> 'private'
            OR gs.user_id = ?
        )

      GROUP BY gs.id
      ORDER BY gs.date_issued DESC
      LIMIT ?, ?;
    ");

    $stmt->bind_param(
        'iiiii',
        $department,
        $area,
        $userId,
        $offset,
        $recordsPerPage
    );


}elseif($isApprover){

    if($isPrivateApprover){

        /* PRIVATE APPROVER
           private + recommended
        */

        $countStmt = $conn->prepare("
            SELECT COUNT(DISTINCT gs.id) AS total

            FROM gas_slips gs

            LEFT JOIN vehicles v
                ON v.id = gs.vehicle_id

            WHERE
                v.ownership = 'private'
                AND gs.status = 'recommended'
        ");

        $countStmt->execute();
        $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

        $stmt = $conn->prepare("
            SELECT
                gs.id AS gs_id,
                gs.gas_slip_id,
                gs.date_issued,
                gs.requested_by,
                gs.purpose,
                gs.status,
                gs.validity_until,
                gs.printed_at,
                v.ownership,

                CONCAT(
                    MIN(r.origin),
                    ' → ',
                    GROUP_CONCAT(
                        r.destination
                        ORDER BY gsr.id
                        SEPARATOR ' → '
                    )
                ) AS route_path

            FROM gas_slips gs

            LEFT JOIN gas_slip_routes gsr
                ON gsr.gas_slip_id = gs.id

            LEFT JOIN routes r
                ON r.id = gsr.route_id

            LEFT JOIN vehicles v
                ON v.id = gs.vehicle_id

            WHERE
                v.ownership = 'private'
                AND gs.status = 'recommended'

            GROUP BY gs.id
            ORDER BY gs.date_issued DESC
            LIMIT ?, ?
        ");

        $stmt->bind_param(
            'ii',
            $offset,
            $recordsPerPage
        );

    }else{

        /* NORMAL APPROVER
           private + pending
           coop-owned + recommended
        */

        $countStmt = $conn->prepare("
            SELECT COUNT(DISTINCT gs.id) AS total

            FROM gas_slips gs

            LEFT JOIN users u
                ON u.id = gs.user_id

            LEFT JOIN users a
                ON a.id = ?

            LEFT JOIN vehicles v
                ON v.id = gs.vehicle_id

            WHERE (

                (
                    v.ownership = 'private'
                    AND gs.status = 'pending'
                    AND u.department_id = a.department_id
                )

                OR

                (
                    v.ownership = 'coop-owned'
                    AND gs.status = 'recommended'

                    AND EXISTS (
                        SELECT 1
                        FROM department_areas da
                        WHERE da.department_id = a.department_id
                        AND da.area_id = u.area_id
                    )
                )

            )
        ");

        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

        $stmt = $conn->prepare("
            SELECT
                gs.id AS gs_id,
                gs.gas_slip_id,
                gs.date_issued,
                gs.requested_by,
                gs.purpose,
                gs.status,
                gs.validity_until,
                gs.printed_at,
                v.ownership,

                CONCAT(
                    MIN(r.origin),
                    ' → ',
                    GROUP_CONCAT(
                        r.destination
                        ORDER BY gsr.id
                        SEPARATOR ' → '
                    )
                ) AS route_path

            FROM gas_slips gs

            LEFT JOIN gas_slip_routes gsr
                ON gsr.gas_slip_id = gs.id

            LEFT JOIN routes r
                ON r.id = gsr.route_id

            LEFT JOIN users u
                ON u.id = gs.user_id

            LEFT JOIN users a
                ON a.id = ?

            LEFT JOIN vehicles v
                ON v.id = gs.vehicle_id

            WHERE (

                (
                    v.ownership = 'private'
                    AND gs.status = 'pending'
                    AND u.department_id = a.department_id
                )

                OR

                (
                    v.ownership = 'coop-owned'
                    AND gs.status = 'recommended'

                    AND EXISTS (
                        SELECT 1
                        FROM department_areas da
                        WHERE da.department_id = a.department_id
                        AND da.area_id = u.area_id
                    )
                )

            )

            GROUP BY gs.id
            ORDER BY gs.date_issued DESC
            LIMIT ?, ?
        ");

        $stmt->bind_param(
            'iii',
            $userId,
            $offset,
            $recordsPerPage
        );
    }

}else{

    /* TOTAL RECORDS */
    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM gas_slips
        WHERE user_id = ?
        AND status IN ('pending','recommended','rejected')
    ");

    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

    /* MAIN QUERY */
    $stmt = $conn->prepare("
      SELECT 
          gs.id AS gs_id,
          gs.gas_slip_id,
          gs.date_issued,
          gs.requested_by,
          gs.purpose,
          gs.status,
          gs.validity_until,
          gs.printed_at,
          gs.approved_at,
          CONCAT(
            MIN(r.origin),
            ' → ',
            GROUP_CONCAT(
              r.destination
              ORDER BY gsr.id
              SEPARATOR ' → '
            )
          ) AS route_path
          
      FROM gas_slips gs 
      LEFT JOIN gas_slip_routes gsr ON gsr.gas_slip_id = gs.id
      LEFT JOIN routes r ON r.id = gsr.route_id

      WHERE gs.user_id = ?
      AND gs.status IN('pending','recommended', 'rejected')

      GROUP BY gs.id
      ORDER BY gs.date_issued DESC
      LIMIT ?, ?;

    ");

    $stmt->bind_param(
        'iii',
        $userId,
        $offset,
        $recordsPerPage
    );
}

$stmt->execute();
$result = $stmt->get_result();
$totalPages = max(1, ceil($totalRecords / $recordsPerPage));
?>