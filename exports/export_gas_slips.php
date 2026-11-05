<?php
require_once __DIR__ . '/../includes/gas_slip_report.php';

$userId = $_SESSION['user_id'];
$role     = $_SESSION['role'] ?? '';
$department = $_SESSION['department_id'];
$area = $_SESSION['area'];

$isAdmin        = ($role === 'admin');
$isRecommender  = !empty($_SESSION['is_recommender']);
$isApprover     = !empty($_SESSION['is_approver']);
$isPrivateApprover     = !empty($_SESSION['is_private_approver']);
$isFullReport         = !empty($_SESSION['is_full_report']);

$fullReportDepartments = [
    4, // FSD
    5, // Audit
];

$canViewAllReports = in_array($department, $fullReportDepartments);
$viewAllDepartments = $canViewAllReports && isset($_GET['view_all']);

gasSlipReportDownloadHeaders();

$output = fopen('php://output', 'w');

/* =========================================================
   CSV HEADERS
========================================================= */

gasSlipReportCsvStart($output);

/* =========================================================
   QUERY
========================================================= */
$sql = gasSlipReportSelectSql() . "
WHERE DATE(gs.date_issued)
    BETWEEN ? AND ?
";

$types = 'ss';
$params = [$dateFrom, $dateTo];

if (
    !$isAdmin &&
    !$isPrivateApprover &&
    !$viewAllDepartments
) {

    $sql .= "
    AND u.department_id = ?
    ";

    $types .= 'i';
    $params[] = $department;
}

$sql .= "
GROUP BY gs.id

ORDER BY gs.date_issued DESC
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();

/* =========================================================
   DATA
========================================================= */

while ($row = $result->fetch_assoc()) {

    gasSlipReportCsvRow($output, $row);
}

fclose($output);
exit;