<?php
// upload_excel_api.php
header("Content-Type: application/json");
require_once 'includes/config.php';

$conn = getDBConnection();
$conn->begin_transaction(); // ✅ start transaction

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data || !isset($data['records'])) {
        throw new Exception("Invalid JSON input");
    }

    $origin = $conn->real_escape_string($data['origin'] ?? '');
    $user_id_enc = $conn->real_escape_string($data['user_id'] ?? '');
    $user_id = decodeUserId($user_id_enc);

    $count = 0;

    // get last id from gas_slips
    $last_insert_id = 0;
    $sql_last_id = "SELECT MAX(id) AS last_id FROM gas_slips";
    $res_last_id = $conn->query($sql_last_id);
    if ($res_last_id && $res_last_id->num_rows > 0) {
        $row_last_id = $res_last_id->fetch_assoc();
        $last_insert_id = intval($row_last_id['last_id']);
    }
    $current_id = $last_insert_id;

    $slip_groups = [];

    // First pass: group by slip
    foreach ($data['records'] as $index => $rec) {
        if ($index === 0 && (
            stripos($rec['requested_by'], "requested") !== false ||
            stripos($rec['plate_no'], "plate") !== false
        )) continue;

        $requested_by   = $conn->real_escape_string($rec['requested_by']);
        $plate_no       = $conn->real_escape_string($rec['plate_no']);
        $purpose        = $conn->real_escape_string($rec['purpose']);
        $sequence_no    = intval($rec['sequence_no']);
        $destination    = $conn->real_escape_string($rec['destination']);
        $route_id       = $conn->real_escape_string($rec['route_id']);
        $date_issued    = $conn->real_escape_string($rec['date_issued']);
        $validity_until = $conn->real_escape_string($rec['validity_until']);
        $status         = 'pending';
        $fuel_item_id   = 2;
        $container      = 'no';
        $vehicle_id     = 1;

        if (empty($requested_by) && empty($plate_no) && empty($purpose)) continue;

        // vehicle lookup
        $sql_vehicle = "SELECT id FROM vehicles WHERE plate_no = ? LIMIT 1";
        $stmt_vehicle = $conn->prepare($sql_vehicle);
        $stmt_vehicle->bind_param("s", $plate_no);
        $stmt_vehicle->execute();
        $res_vehicle = $stmt_vehicle->get_result();
        if ($res_vehicle && $res_vehicle->num_rows > 0) {
            $row_vehicle = $res_vehicle->fetch_assoc();
            $vehicle_id = intval($row_vehicle['id']);
        }
        $stmt_vehicle->close();

        // generate custom slip id
        if ($sequence_no === 1) $current_id++;
        $formatted_user_id = str_pad($user_id, 2, '0', STR_PAD_LEFT);
        $formatted_id = str_pad($current_id, 5, '0', STR_PAD_LEFT);
        $year = date('Y');
        $gas_slip_custom_id = "$year-$formatted_user_id-$formatted_id";

        // route info
        $route_distance = 0;
        $route_allocation = 0;
        $sql_route = "SELECT id, distance_km, fuel_allocation FROM routes WHERE route = ? LIMIT 1";
        $stmt_route = $conn->prepare($sql_route);
        $stmt_route->bind_param("i", $route_id);
        $stmt_route->execute();
        $res_route = $stmt_route->get_result();
        if ($res_route && $res_route->num_rows > 0) {
            $row_route = $res_route->fetch_assoc();
            $route_id_val = floatval($row_route['id']);
            $route_distance = floatval($row_route['distance_km']);
            $route_allocation = floatval($row_route['fuel_allocation']);
        }
        $stmt_route->close();

        $slip_groups[$gas_slip_custom_id][] = [
            'requested_by'     => $requested_by,
            'plate_no'         => $plate_no,
            'purpose'          => $purpose,
            'sequence_no'      => $sequence_no,
            'origin'           => $origin,
            'destination'      => $destination,
            'route_id'         => $route_id,
            'date_issued'      => $date_issued,
            'validity_until'   => $validity_until,
            'vehicle_id'       => $vehicle_id,
            'status'           => $status,
            'fuel_item_id'     => $fuel_item_id,
            'container'        => $container,
            'user_id'          => $user_id,
            'route_id_val'     => $route_id_val,
            'route_distance'   => $route_distance,
            'route_allocation' => $route_allocation,
            'gas_slip_id'      => $gas_slip_custom_id,
            'quantity'         => $rec['quantity'] ?? null
        ];
    }

    // prepare stmts
    $stmt_gas_slip = $conn->prepare("
        INSERT INTO gas_slips 
            (date_issued, gas_slip_id, purpose, requested_by, area, route_id, status, validity_until, vehicle_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_gas_slip_routes = $conn->prepare("
        INSERT INTO gas_slip_routes (gas_slip_id, route_id, sequence_no) VALUES (?, ?, ?)
    ");
    $stmt_fuel_requests = $conn->prepare("
        INSERT INTO fuel_requests (container, fuel_item_id, gas_slip_id, quantity) VALUES (?, ?, ?, ?)
    ");

    $inserted_ids = []; // ✅ keep track of uploaded slip IDs

    foreach ($slip_groups as $gas_slip_custom_id => $rows) {
        $max_distance = 0;
        $fuel_base = 0;
        foreach ($rows as $row) {
            if ($row['route_distance'] > $max_distance) {
                $max_distance = $row['route_distance'];
                $fuel_base = $row['route_allocation'];
            }
        }
        $sequence_count = count($rows);
        $final_fuel = $fuel_base + (0.6 * ($sequence_count - 1));
        $quantity = (string)$final_fuel;
        foreach ($rows as $row) {
            if (isset($row['quantity']) && strtoupper(trim($row['quantity'])) === 'FT') {
                $quantity = 'FT'; break;
            }
        }

        $first = $rows[0];

        // insert main slip
        $stmt_gas_slip->bind_param(
            "sssssissi",
            $first['date_issued'], $gas_slip_custom_id, $first['purpose'], $first['requested_by'], $first['origin'],
            $first['route_id_val'], $first['status'], $first['validity_until'], $first['vehicle_id']
        );
        if (!$stmt_gas_slip->execute()) {
            throw new Exception("Insert gas_slips failed: ".$stmt_gas_slip->error);
        }
        
        $gas_slip_db_id = $stmt_gas_slip->insert_id; // ✅ integer FK
        $inserted_ids[] = $gas_slip_db_id;

        // insert routes
        foreach ($rows as $row) {
            $stmt_gas_slip_routes->bind_param("iii", $gas_slip_db_id, $row['route_id_val'], $row['sequence_no']);
            if (!$stmt_gas_slip_routes->execute()) {
                throw new Exception("Insert gas_slip_routes failed: ".$stmt_gas_slip_routes->error);
            }
        }

        // insert fuel request
        $stmt_fuel_requests->bind_param("siss", $first['container'], $first['fuel_item_id'], $gas_slip_custom_id, $quantity);
        if (!$stmt_fuel_requests->execute()) {
            throw new Exception("Insert fuel_requests failed: ".$stmt_fuel_requests->error);
        }

        $count++;
    }

    $conn->commit();
    // echo json_encode(["status"=>"success","message"=>"$count gas slips inserted successfully."]);

    echo json_encode([
        "status" => "success",
        "message" => "$count gas slips inserted successfully.",
        "ids" => $inserted_ids
    ]);
    exit;


} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
}
$conn->close();

function decodeUserId($encoded) {
    return intval((intval($encoded) - 1) / 1234);
}
