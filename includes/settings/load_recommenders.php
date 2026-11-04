<?php
require_once __DIR__ . '/assignment_scope.php';

$userId     = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area       = $_SESSION['area'];

$conn = getDBConnection();

/* Regular approvers manage their own department. Admins may select another
   department from the Department Recommenders card. */
$recommenderDepartment = (int) $department;

if ($role === 'admin' && !empty($_GET['recommender_department_id'])) {
    $recommenderDepartment = (int) $_GET['recommender_department_id'];
}

if (!isConfiguredDepartment($conn, $recommenderDepartment)) {
    $recommenderDepartment = (int) $department;
}

/* =========================================================
   LOAD CURRENT RECOMMENDERS
========================================================= */

$currentRecommenders = [];

$recommenderStmt = $conn->prepare("
    SELECT
        u.id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.designation,
        a.area_name
    FROM department_recommenders dr
    INNER JOIN users u
        ON u.id = dr.user_id
    LEFT JOIN areas a
        ON a.id = u.area_id
    WHERE dr.department_id = ?
    ORDER BY u.last_name ASC, u.first_name ASC
");

$recommenderStmt->bind_param("i", $recommenderDepartment);
$recommenderStmt->execute();

$recommenderResult = $recommenderStmt->get_result();

while ($row = $recommenderResult->fetch_assoc()) {
    $currentRecommenders[] = $row;
}

$recommenderStmt->close();

$canManageRecommenders = canManageDepartmentRecommenders(
    $conn,
    $userId,
    $role,
    $recommenderDepartment
);

$recommenderCandidates = $canManageRecommenders
    ? getEligiblePrimaryRecommenderCandidates($conn, $recommenderDepartment)
    : [];

$multiAreaRecommenders = isMultiAreaDepartment($conn, $recommenderDepartment);
$recommenderAreas = $multiAreaRecommenders ? getDepartmentRecommenderAreas($conn, $recommenderDepartment) : [];
if (empty($_SESSION['recommender_csrf'])) { $_SESSION['recommender_csrf'] = bin2hex(random_bytes(32)); }
$assignedAreas = [];
if ($multiAreaRecommenders) {
    $areasStmt = $conn->prepare('SELECT recommender_id, area_id FROM recommender_area_assignments WHERE department_id = ?');
    $areasStmt->bind_param('i', $recommenderDepartment);
    $areasStmt->execute();
    foreach ($areasStmt->get_result() as $assignment) { $assignedAreas[(int) $assignment['recommender_id']][] = (int) $assignment['area_id']; }
}
?>
