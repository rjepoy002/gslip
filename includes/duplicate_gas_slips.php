<?php

function normalizeDuplicateRouteIds($routes)
{
    $routeIds = [];
    foreach ((array) $routes as $route) {
        $routeId = is_array($route) ? (int) ($route['route_id'] ?? 0) : (int) $route;
        if ($routeId > 0) {
            $routeIds[$routeId] = $routeId;
        }
    }
    ksort($routeIds, SORT_NUMERIC);
    return array_values($routeIds);
}

function getDuplicateProposalsFromPost($post)
{
    $proposals = [];
    $vehicleIds = $post['vehicle_id'] ?? [];

    foreach ((array) $vehicleIds as $index => $vehicleId) {
        $vehicleId = (int) $vehicleId;
        $validityUntil = trim((string) ($post['validity_until'][$index] ?? ''));
        $routes = json_decode($post['destinations'][$index] ?? '[]', true);
        $routeIds = normalizeDuplicateRouteIds(is_array($routes) ? $routes : []);

        if ($vehicleId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $validityUntil) && $routeIds) {
            $proposals[] = [
                'row' => (int) $index + 1,
                'vehicle_id' => $vehicleId,
                'validity_until' => $validityUntil,
                'route_ids' => $routeIds
            ];
        }
    }

    return $proposals;
}

function findPossibleDuplicateGasSlips($conn, $proposals, $excludeGasSlipId = 0)
{
    $duplicates = [];
    $seen = [];
    $excludeGasSlipId = (int) $excludeGasSlipId;

    $candidateStmt = $conn->prepare("\n        SELECT\n            gs.id, gs.gas_slip_id, gs.status, gs.vehicle_id, gs.validity_until, gs.user_id,\n            v.plate_no,\n            TRIM(CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name)) AS created_by\n        FROM gas_slips gs\n        INNER JOIN vehicles v ON v.id = gs.vehicle_id\n        INNER JOIN users u ON u.id = gs.user_id\n        WHERE gs.vehicle_id = ?\n          AND gs.validity_until = ?\n          AND DATE(gs.date_issued) = CURDATE()\n          AND gs.id <> ?\n    ");
    $routeStmt = $conn->prepare("\n        SELECT r.id, r.destination\n        FROM gas_slip_routes gsr\n        INNER JOIN routes r ON r.id = gsr.route_id\n        WHERE gsr.gas_slip_id = ?\n        ORDER BY r.destination\n    ");

    foreach ($proposals as $proposal) {
        $vehicleId = (int) $proposal['vehicle_id'];
        $validityUntil = $proposal['validity_until'];
        $candidateStmt->bind_param('isi', $vehicleId, $validityUntil, $excludeGasSlipId);
        $candidateStmt->execute();
        $candidates = $candidateStmt->get_result();

        while ($candidate = $candidates->fetch_assoc()) {
            $candidateId = (int) $candidate['id'];
            $routeStmt->bind_param('i', $candidateId);
            $routeStmt->execute();
            $routeResult = $routeStmt->get_result();
            $candidateRouteIds = [];
            $destinations = [];
            while ($route = $routeResult->fetch_assoc()) {
                $candidateRouteIds[] = (int) $route['id'];
                $destinations[] = $route['destination'];
            }

            if (normalizeDuplicateRouteIds($candidateRouteIds) !== $proposal['route_ids']) {
                continue;
            }

            $key = 'db-' . $candidateId . '-row-' . $proposal['row'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $duplicates[] = [
                'source' => 'database',
                'row' => $proposal['row'],
                'id' => $candidateId,
                'gas_slip_id' => $candidate['gas_slip_id'] ?: ('#' . $candidateId),
                'plate_no' => $candidate['plate_no'],
                'destinations' => $destinations,
                'validity_until' => $candidate['validity_until'],
                'created_by' => $candidate['created_by'],
                'status' => $candidate['status'],
                'created_by_id' => (int) $candidate['user_id']
            ];
        }
    }

    $batchGroups = [];
    foreach ($proposals as $proposal) {
        $key = $proposal['vehicle_id'] . '|' . $proposal['validity_until'] . '|' . implode(',', $proposal['route_ids']);
        $batchGroups[$key][] = $proposal;
    }
    foreach ($batchGroups as $group) {
        if (count($group) < 2) {
            continue;
        }
        foreach ($group as $proposal) {
            $duplicates[] = [
                'source' => 'batch',
                'row' => $proposal['row'],
                'duplicate_rows' => array_values(array_diff(array_column($group, 'row'), [$proposal['row']]))
            ];
        }
    }

    $candidateStmt->close();
    $routeStmt->close();
    return $duplicates;
}
