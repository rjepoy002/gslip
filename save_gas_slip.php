<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/notifications.php';

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

/* =========================================================
   EDIT MODE DETECTION
========================================================= */

$isEdit = !empty($_POST['gas_slip_id']);
$editGasSlipId = (int) ($_POST['gas_slip_id'] ?? 0);

$userId = (int) $_SESSION['user_id'];
$area   = $_SESSION['area'] ?? '';

$GENERIC_ERROR = 'Please fill up the form completely before continuing.';

/* =========================================================
   ROW COUNT
========================================================= */
$rowCount = isset($_POST['vehicle_id'])
    ? count($_POST['vehicle_id'])
    : 0;

if ($rowCount === 0) {
    die('No gas slips submitted');
}

/* =========================================================
   GAS SLIP ID PARTS
========================================================= */
$currentYear = date('Y');
$userPart    = str_pad($userId, 3, '0', STR_PAD_LEFT);

/* =========================================================
   TRANSACTION FLAG (mysqli-safe)
========================================================= */
$transactionStarted = false;

try {

    $conn->begin_transaction();
    $transactionStarted = true;

    for ($i = 0; $i < $rowCount; $i++) {

        /* -----------------------------------------------
           ROW-SCOPED INPUT
        ----------------------------------------------- */
        $vehicle_id     = (int) ($_POST['vehicle_id'][$i] ?? 0);
        $purpose        = trim($_POST['purpose'][$i] ?? '');
        $requested_by   = trim($_POST['requested_by'][$i] ?? 'UNKNOWN');
        $validity_until = $_POST['validity_until'][$i] ?? null;

        $destJson = $_POST['destinations'][$i] ?? '[]';
        $fuelJson = $_POST['fuel_requests'][$i] ?? '[]';

        /* -----------------------------------------------
           EMPTY ROW DETECTION (VALID PLACEHOLDER)
        ----------------------------------------------- */
        $isEmptyRow =
            $vehicle_id === 0 &&
            trim($destJson) === '[]' &&
            trim($fuelJson) === '[]' &&
            $purpose === '';

        if ($isEmptyRow) {
            continue;
        }

        /* -----------------------------------------------
           DECODE ROUTES (REQUIRED)
        ----------------------------------------------- */
        $routeIds = [];
        $routes = json_decode($destJson, true);

        if (!is_array($routes)) {
            throw new Exception($GENERIC_ERROR);
        }

        foreach ($routes as $r) {
            if (!empty($r['route_id'])) {
                $routeIds[] = (int) $r['route_id'];
            }
        }

        /* -----------------------------------------------
           DECODE FUEL (REQUIRED)
        ----------------------------------------------- */
        $fuelRequests = [];
        $items = json_decode($fuelJson, true);

        if (!is_array($items)) {
            throw new Exception($GENERIC_ERROR);
        }

        foreach ($items as $f) {
            if (empty($f['id']) || !isset($f['qty']) || $f['qty'] <= 0) {
                throw new Exception($GENERIC_ERROR);
            }

            $fuelRequests[] = [
                'fuel_item_id' => (int) $f['id'],
                'quantity'     => (float) $f['qty'],
                'container'    => $f['container'] ?? 'No'
            ];
        }

        /* -----------------------------------------------
           FINAL REQUIRED CHECK (ACTIVE ROW)
        ----------------------------------------------- */
        if ($vehicle_id === 0 || empty($routeIds) || empty($fuelRequests)) {
            throw new Exception($GENERIC_ERROR);
        }

        // /* =====================================================
        //    1️⃣ INSERT gas_slips
        // ===================================================== */
        // $stmt = $conn->prepare("
        //     INSERT INTO gas_slips (
        //         user_id,
        //         date_issued,
        //         validity_until,
        //         vehicle_id,
        //         purpose,
        //         requested_by,
        //         area,
        //         status
        //     ) VALUES (
        //         ?,
        //         NOW(),
        //         ?,
        //         ?,
        //         ?,
        //         ?,
        //         ?,
        //         'pending'
        //     )
        // ");

        // $stmt->bind_param(
        //     'isisss',
        //     $userId,
        //     $validity_until,
        //     $vehicle_id,
        //     $purpose,
        //     $requested_by,
        //     $area
        // );
        // $stmt->execute();

        // $savedId = $conn->insert_id;
        // if ($savedId <= 0) {
        //     throw new Exception($GENERIC_ERROR);
        // }

        // /* =====================================================
        //    2️⃣ GENERATE + UPDATE gas_slip_id
        // ===================================================== */
        // $seqPart   = str_pad($savedId, 6, '0', STR_PAD_LEFT);
        // $gasSlipId = $currentYear . $userPart . $seqPart;

        // $stmt = $conn->prepare("
        //     UPDATE gas_slips
        //     SET gas_slip_id = ?
        //     WHERE id = ?
        // ");
        // $stmt->bind_param('si', $gasSlipId, $savedId);
        // $stmt->execute();

        /* =====================================================
        1️⃣ CREATE OR UPDATE gas_slips
        ===================================================== */

        if ($isEdit) {

            $savedId = $editGasSlipId;

            $stmt = $conn->prepare("
                UPDATE gas_slips
                SET
                    date_issued = NOW(),
                    validity_until = ?,
                    vehicle_id = ?,
                    purpose = ?,
                    requested_by = ?,
                    status = 'pending'
                WHERE id = ?
                AND user_id = ?
            ");

            $stmt->bind_param(
                'sissii',
                $validity_until,
                $vehicle_id,
                $purpose,
                $requested_by,
                $savedId,
                $userId
            );

            $stmt->execute();

            /* =====================================================
            DELETE OLD ROUTES
            ===================================================== */
            $stmtDeleteRoutes = $conn->prepare("
                DELETE FROM gas_slip_routes
                WHERE gas_slip_id = ?
            ");

            $stmtDeleteRoutes->bind_param('i', $savedId);
            $stmtDeleteRoutes->execute();

            /* =====================================================
            DELETE OLD FUEL REQUESTS
            ===================================================== */
            $stmtDeleteFuel = $conn->prepare("
                DELETE FROM fuel_requests
                WHERE gas_slip_id = ?
            ");

            $stmtDeleteFuel->bind_param('i', $savedId);
            $stmtDeleteFuel->execute();

            /* =====================================================
            DELETE OLD PENDING NOTIFICATIONS
            ===================================================== */
            $stmtDeleteNotif = $conn->prepare("
                DELETE FROM notifications
                WHERE gas_slip_id = ?
                AND type = 'new_pending'
            ");

            $stmtDeleteNotif->bind_param('i', $savedId);
            $stmtDeleteNotif->execute();


        } else {

            $stmt = $conn->prepare("
                INSERT INTO gas_slips (
                    user_id,
                    date_issued,
                    validity_until,
                    vehicle_id,
                    purpose,
                    requested_by,
                    area_id,
                    status
                ) VALUES (
                    ?,
                    NOW(),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'pending'
                )
            ");

            $stmt->bind_param(
                'isisss',
                $userId,
                $validity_until,
                $vehicle_id,
                $purpose,
                $requested_by,
                $area
            );

            $stmt->execute();

            $savedId = $conn->insert_id;

            if ($savedId <= 0) {
                throw new Exception($GENERIC_ERROR);
            }

            /* =====================================================
            GENERATE gas_slip_id
            ===================================================== */
            $seqPart   = str_pad($savedId, 6, '0', STR_PAD_LEFT);
            $gasSlipId = $currentYear . $userPart . $seqPart;

            $stmt = $conn->prepare("
                UPDATE gas_slips
                SET gas_slip_id = ?
                WHERE id = ?
            ");

            $stmt->bind_param('si', $gasSlipId, $savedId);
            $stmt->execute();

        }

        /* =====================================================
           3️⃣ INSERT gas_slip_routes
        ===================================================== */
        $stmt = $conn->prepare("
            INSERT INTO gas_slip_routes (
                gas_slip_id,
                route_id,
                sequence_no
            ) VALUES (?, ?, ?)
        ");

        $seq = 1;
        foreach ($routeIds as $rid) {
            $stmt->bind_param('iii', $savedId, $rid, $seq);
            $stmt->execute();
            $seq++;
        }

        /* =====================================================
           4️⃣ INSERT fuel_requests
        ===================================================== */
        $stmt = $conn->prepare("
            INSERT INTO fuel_requests (
                gas_slip_id,
                fuel_item_id,
                quantity,
                container
            ) VALUES (?, ?, ?, ?)
        ");

        foreach ($fuelRequests as $f) {
            $stmt->bind_param(
                'iids',
                $savedId,
                $f['fuel_item_id'],
                $f['quantity'],
                $f['container']
            );
            $stmt->execute();
        }

        /* =====================================================
        CREATE NOTIFICATIONS
        ===================================================== */
        notifyGasSlipCreated($conn, $savedId);

    } // end of loop

    
    /* =====================================================
       COMMIT
    ===================================================== */
    $conn->commit();

    $_SESSION['swal_success'] = $isEdit
    ? 'Gas slip successfully updated.'
    : 'Gas slip successfully created.';

    header('Location: pending_gas_slips.php');
    exit;

} catch (Exception $e) {

    if ($transactionStarted) {
        $conn->rollback();
    }

    $_SESSION['swal_error'] = $e->getMessage();
    header('Location: create_gas_slip.php');
    exit;
}
