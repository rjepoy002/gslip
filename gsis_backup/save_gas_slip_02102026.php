<?php
session_start();
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

$userId = (int) $_SESSION['user_id'];
$area   = $_SESSION['area'] ?? '';

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
   TRANSACTION
========================================================= */
$conn->begin_transaction();

try {

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

        if ($vehicle_id === 0) {
            continue; // skip empty row
        }

        /* -----------------------------------------------
           DECODE ROUTES (PER ROW)
        ----------------------------------------------- */
        $routeIds = [];
        $routes = json_decode($destJson, true);
        if (is_array($routes)) {
            foreach ($routes as $r) {
                if (!empty($r['route_id'])) {
                    $routeIds[] = (int) $r['route_id'];
                }
            }
        }

        if (empty($routeIds)) {
            continue; // no destinations → skip row
        }

        /* -----------------------------------------------
           DECODE FUEL (PER ROW)
        ----------------------------------------------- */
        $fuelRequests = [];
        $items = json_decode($fuelJson, true);
        if (is_array($items)) {
            foreach ($items as $f) {
                if (!empty($f['id']) && !empty($f['qty'])) {
                    $fuelRequests[] = [
                        'fuel_item_id' => (int) $f['id'],
                        'quantity'     => (float) $f['qty'],
                        'container'    => $f['container'] ?? 'No'
                    ];
                }
            }
        }

        if (empty($fuelRequests)) {
            continue;
        }

        /* =====================================================
           1️⃣ INSERT gas_slips (ONE PER ROW)
        ===================================================== */
        $stmt = $conn->prepare("
            INSERT INTO gas_slips (
                user_id,
                date_issued,
                validity_until,
                vehicle_id,
                purpose,
                requested_by,
                area,
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
            throw new Exception('Failed to save gas_slips');
        }

        /* =====================================================
           2️⃣ GENERATE + UPDATE gas_slip_id
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

        /* =====================================================
           3️⃣ INSERT gas_slip_routes (PER ROW)
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
           4️⃣ INSERT fuel_requests (PER ROW)
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
    }

    /* =====================================================
       COMMIT
    ===================================================== */
    $conn->commit();
    
    $_SESSION['swal_success'] = 'Gas slip successfully created.';
    header('Location: pending_gas_slips.php');
    exit;

} catch (Exception $e) {

    $conn->rollback();
    die('ERROR: ' . $e->getMessage());
}
