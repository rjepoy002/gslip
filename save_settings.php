<?php
session_start();
require_once 'includes/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = getDBConnection();

/* =========================================================
   AUTH GUARD
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['session_token']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['department_id'])
) {
    header('Location: index.php');
    exit;
}

$departmentId = (int) $_SESSION['department_id'];

/* =========================================================
   FORM VALUES
========================================================= */

$recommenderUserIds = isset($_POST['recommender_user_ids'])
    ? array_unique(array_map('intval', $_POST['recommender_user_ids']))
    : [];

$primaryApproverId = !empty($_POST['primary_approver_id'])
    ? (int) $_POST['primary_approver_id']
    : null;

$backupApproverId = !empty($_POST['backup_approver_id'])
    ? (int) $_POST['backup_approver_id']
    : null;

/* =========================================================
   CLEAN VALUES
========================================================= */

$recommenderUserIds = array_filter($recommenderUserIds);

/* =========================================================
   VALIDATIONS
========================================================= */

if (
    $primaryApproverId &&
    in_array($primaryApproverId, $recommenderUserIds)
) {
    die('Primary approver cannot be recommender.');
}

if (
    $backupApproverId &&
    in_array($backupApproverId, $recommenderUserIds)
) {
    die('Backup approver cannot be recommender.');
}

if (
    $primaryApproverId &&
    $backupApproverId &&
    $primaryApproverId === $backupApproverId
) {
    die('Duplicate approver.');
}

/* =========================================================
   START TRANSACTION
========================================================= */

$conn->begin_transaction();

try {

    /* =====================================================
       DELETE OLD RECOMMENDERS
    ===================================================== */

    $stmt = $conn->prepare("
        DELETE FROM department_recommenders
        WHERE department_id = ?
    ");

    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $stmt->close();

    /* =====================================================
       INSERT RECOMMENDERS
    ===================================================== */

    $stmt = $conn->prepare("
        INSERT INTO department_recommenders (
            department_id,
            user_id
        )
        VALUES (?, ?)
    ");

    foreach ($recommenderUserIds as $userId) {

        $stmt->bind_param(
            "ii",
            $departmentId,
            $userId
        );

        $stmt->execute();
    }

    $stmt->close();

    /* =====================================================
       DELETE OLD APPROVERS
    ===================================================== */

    $stmt = $conn->prepare("
        DELETE FROM department_approvers
        WHERE department_id = ?
    ");

    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $stmt->close();

    /* =====================================================
       INSERT PRIMARY APPROVER
    ===================================================== */

    if ($primaryApproverId) {

        $isPrimary = 1;

        $stmt = $conn->prepare("
            INSERT INTO department_approvers (
                department_id,
                user_id,
                is_primary
            )
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param(
            "iii",
            $departmentId,
            $primaryApproverId,
            $isPrimary
        );

        $stmt->execute();

        /* =================================================
           INSERT BACKUP APPROVER
        ================================================= */

        if ($backupApproverId) {

            $isPrimary = 0;

            $stmt->bind_param(
                "iii",
                $departmentId,
                $backupApproverId,
                $isPrimary
            );

            $stmt->execute();
        }

        $stmt->close();
    }

    /* =====================================================
       COMMIT
    ===================================================== */

    $conn->commit();

    $_SESSION['swal_success'] =
        'Settings saved successfully.';

} catch (Exception $e) {

    $conn->rollback();

    die($e->getMessage());
}

/* =========================================================
   REDIRECT
========================================================= */

header('Location: settings.php');
exit;
?>