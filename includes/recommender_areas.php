<?php
/* Only these existing operational departments use multi-area assignment. */
function recommenderAreaSql($department = 'dr.department_id', $user = 'u.id', $area = '?', $primaryArea = 'u.area_id')
{
    return "((EXISTS (SELECT 1 FROM departments mad WHERE mad.id = $department AND UPPER(TRIM(mad.name)) IN ('ANOD','ASOD'))
        AND EXISTS (SELECT 1 FROM recommender_area_assignments maa WHERE maa.department_id = $department AND maa.recommender_id = $user AND maa.area_id = $area))
        OR (NOT EXISTS (SELECT 1 FROM departments mad WHERE mad.id = $department AND UPPER(TRIM(mad.name)) IN ('ANOD','ASOD')) AND $primaryArea = $area))";
}

function isMultiAreaDepartment($conn, $departmentId)
{
    $stmt = $conn->prepare("SELECT 1 FROM departments WHERE id = ? AND UPPER(TRIM(name)) IN ('ANOD','ASOD')");
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function getDepartmentRecommenderAreas($conn, $departmentId)
{
    $stmt = $conn->prepare('SELECT DISTINCT a.id, a.area_name FROM department_areas da JOIN areas a ON a.id = da.area_id WHERE da.department_id = ? ORDER BY a.area_name');
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* Serializes area changes with submission/recommendation authorization. */
function lockRecommenderDepartment($conn, $departmentId)
{
    $stmt = $conn->prepare('SELECT id FROM departments WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $stmt->get_result();
    $stmt->close();
}

function requireRecommenderCsrf()
{
    if (empty($_SESSION['recommender_csrf']) || !is_string($_POST['csrf_token'] ?? null)
        || !hash_equals($_SESSION['recommender_csrf'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Reload Settings.']);
        exit;
    }
}

/* Caller owns the department lock and has verified approver/recommender scope. */
function replaceRecommenderAreas($conn, $departmentId, $recommenderId, $submitted)
{
    if (!is_array($submitted)) { throw new RuntimeException('Invalid areas.'); }
    $areas = getDepartmentRecommenderAreas($conn, $departmentId);
    $allowed = array_column($areas, 'id');
    $selected = [];
    foreach ($submitted as $id) {
        if (!is_scalar($id) || !ctype_digit((string) $id) || !in_array((int) $id, $allowed)) {
            throw new RuntimeException('An area does not belong to this department.');
        }
        $selected[(int) $id] = (int) $id;
    }
    $restored = !$selected;
    if ($restored) {
        $stmt = $conn->prepare('SELECT area_id FROM users WHERE id = ? AND department_id = ? FOR UPDATE');
        $stmt->bind_param('ii', $recommenderId, $departmentId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $primaryArea = (int) ($user['area_id'] ?? 0);
        if ($primaryArea <= 0 || !in_array($primaryArea, $allowed)) {
            throw new RuntimeException('The recommender has no valid primary area for this department. Select at least one authorized area or update their primary area before saving.');
        }
        $selected[$primaryArea] = $primaryArea;
    }
    $stmt = $conn->prepare('DELETE FROM recommender_area_assignments WHERE department_id = ? AND recommender_id = ?');
    $stmt->bind_param('ii', $departmentId, $recommenderId);
    $stmt->execute();
    $stmt = $conn->prepare('INSERT INTO recommender_area_assignments (department_id, recommender_id, area_id) VALUES (?, ?, ?)');
    foreach ($selected as $areaId) {
        $stmt->bind_param('iii', $departmentId, $recommenderId, $areaId);
        $stmt->execute();
    }
    $savedAreas = array_values(array_filter($areas, function ($area) use ($selected) {
        return isset($selected[(int) $area['id']]);
    }));
    return [
        'areas' => $savedAreas,
        'default_area_restored' => $restored,
        'message' => $restored
            ? 'Primary area ' . $savedAreas[0]['area_name'] . " retained as the recommender's default area."
            : 'Assigned areas updated.'
    ];
}
