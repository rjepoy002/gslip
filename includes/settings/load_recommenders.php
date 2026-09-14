<?php
$userId     = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$department = $_SESSION['department_id'];
$area       = $_SESSION['area'];

$conn = getDBConnection();
/* =========================================================
   LOAD CURRENT RECOMMENDERS
========================================================= */

$currentRecommenders = [];

$recommenderStmt = $conn->prepare("
    SELECT user_id
    FROM department_recommenders
    WHERE department_id = ?
");

$recommenderStmt->bind_param("i", $department);
$recommenderStmt->execute();

$recommenderResult = $recommenderStmt->get_result();

while ($row = $recommenderResult->fetch_assoc()) {
    $currentRecommenders[] = (int)$row['user_id'];
}

$recommenderStmt->close();


?>