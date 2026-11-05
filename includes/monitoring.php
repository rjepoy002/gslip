<?php
require_once __DIR__ . '/monitoring_access.php';
require_once __DIR__ . '/gas_slip_report.php';

function monitoringExportCsv(mysqli $conn, array $f, int $userId)
{
    [$where, $params] = monitoringWhere($f, canAccessMonitoring($conn, $userId));
    $order = monitoringSortColumns()[$f['sort']] . ' ' . strtoupper($f['direction']);
    $output = tmpfile();
    if (!$output) throw new RuntimeException('Unable to prepare report.');
    try {
        gasSlipReportCsvStart($output, true);
        // Batch all matching records inside the caller's consistent snapshot.
        // Pagination parameters affect the UI only, never the exported row set.
        for ($offset = 0; ; $offset += 500) {
            $rows = monitoringQuery($conn, gasSlipReportSelectSql() . " WHERE $where GROUP BY gs.id ORDER BY $order, gs.id DESC LIMIT ? OFFSET ?", array_merge($params, [500, $offset]));
            // Reuse monitoring's lossless batched routes rather than GROUP_CONCAT's
            // server-dependent length limit when exporting long route sequences.
            $routes = monitoringRoutes($conn, array_map(fn($r) => (int)$r['id'], $rows));
            foreach ($rows as $row) {
                $row['route_path'] = $routes[$row['id']] ?? '';
                gasSlipReportCsvRow($output, $row, true);
            }
            if (count($rows) < 500) break;
        }
        rewind($output);
        return $output;
    } catch (Throwable $e) {
        fclose($output);
        throw $e;
    }
}

function monitoringQuery(mysqli $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : 's', $params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function monitoringFilters(array $input): array
{
    $integer = function ($key, $default, $min, $max) use ($input) {
        $value = $input[$key] ?? $default;
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false || $value < $min || $value > $max) {
            throw new InvalidArgumentException('Invalid ' . $key . '.');
        }
        return (int)$value;
    };
    $f = [
        'year' => $integer('year', (int)date('Y'), 1900, 9998),
        'department' => $integer('department', 0, 0, PHP_INT_MAX),
        'area' => $integer('area', 0, 0, PHP_INT_MAX),
        'page' => $integer('page', 1, 1, PHP_INT_MAX),
        'size' => $integer('size', 25, 10, 100),
    ];
    if (!in_array($f['size'], [10, 25, 50, 100], true)) throw new InvalidArgumentException('Invalid page size.');
    foreach (['from', 'to', 'status', 'search', 'sort', 'direction'] as $key) {
        if (isset($input[$key]) && !is_string($input[$key])) throw new InvalidArgumentException('Invalid filter.');
    }
    foreach (['from', 'to'] as $key) {
        $value = $input[$key] ?? '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value)) throw new InvalidArgumentException('Invalid date.');
        $f[$key] = $value;
    }
    if ($f['from'] && $f['to'] && $f['from'] > $f['to']) throw new InvalidArgumentException('From Date must not be later than To Date.');
    $f['status'] = $input['status'] ?? '';
    if (!in_array($f['status'], ['', 'approved', 'printed'], true)) throw new InvalidArgumentException('Invalid status.');
    $f['search'] = trim($input['search'] ?? '');
    if (strlen($f['search']) > 200) throw new InvalidArgumentException('Search must be 200 characters or fewer.');
    $f['sort'] = $input['sort'] ?? 'date';
    if (!isset(monitoringSortColumns()[$f['sort']])) throw new InvalidArgumentException('Invalid sort column.');
    $f['direction'] = $input['direction'] ?? 'desc';
    if (!in_array($f['direction'], ['asc', 'desc'], true)) throw new InvalidArgumentException('Invalid sort direction.');
    return $f;
}

function monitoringSortColumns(): array
{
    return ['number' => 'gs.gas_slip_id', 'date' => 'gs.date_issued', 'department' => 'd.name', 'area' => 'a.area_name', 'requester' => 'gs.requested_by'];
}

function monitoringFrom(): string
{
    return ' FROM gas_slips gs LEFT JOIN users u ON u.id = gs.user_id
        LEFT JOIN departments d ON d.id = u.department_id LEFT JOIN areas a ON a.id = gs.area_id ';
}

// All components share this predicate. Only the graph omits department/area
// selection, so selecting a bar preserves the distribution for comparison.
function monitoringWhere(array $f, bool $moduleAccess, bool $graph = false): array
{
    $parts = ["gs.status IN ('approved','printed')", $moduleAccess ? '1=1' : '0=1', 'gs.date_issued >= ?', 'gs.date_issued < ?'];
    $params = [$f['year'] . '-01-01', ($f['year'] + 1) . '-01-01'];
    if ($f['from']) { $parts[] = 'gs.date_issued >= ?'; $params[] = $f['from']; }
    if ($f['to']) { $parts[] = 'gs.date_issued < ?'; $params[] = (new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d'); }
    if (!$graph) {
        if ($f['department']) { $parts[] = 'u.department_id = ?'; $params[] = $f['department']; }
        if ($f['area']) { $parts[] = 'gs.area_id = ?'; $params[] = $f['area']; }
    }
    if ($f['status']) { $parts[] = 'gs.status = ?'; $params[] = $f['status']; }
    if ($f['search'] !== '') {
        $like = '%' . strtr($f['search'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $parts[] = "(gs.gas_slip_id LIKE ? ESCAPE '!' OR gs.requested_by LIKE ? ESCAPE '!' OR d.name LIKE ? ESCAPE '!' OR a.area_name LIKE ? ESCAPE '!' OR gs.purpose LIKE ? ESCAPE '!'
            OR EXISTS (SELECT 1 FROM gas_slip_routes sr JOIN routes r ON r.id = sr.route_id WHERE sr.gas_slip_id = gs.id AND (r.origin LIKE ? ESCAPE '!' OR r.destination LIKE ? ESCAPE '!')))";
        array_push($params, ...array_fill(0, 7, $like));
    }
    return [implode(' AND ', $parts), $params];
}

function monitoringRoutes(mysqli $conn, array $ids): array
{
    if (!$ids) return [];
    $rows = monitoringQuery($conn, 'SELECT sr.gas_slip_id, r.origin, r.destination FROM gas_slip_routes sr JOIN routes r ON r.id = sr.route_id WHERE sr.gas_slip_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY sr.gas_slip_id, sr.id', $ids);
    $routes = [];
    foreach ($rows as $row) $routes[$row['gas_slip_id']][] = $row['origin'] . ' → ' . $row['destination'];
    return array_map(fn($items) => implode('; ', $items), $routes);
}

function monitoringData(mysqli $conn, array $f, int $userId): array
{
    $moduleAccess = canAccessMonitoring($conn, $userId);
    [$where, $params] = monitoringWhere($f, $moduleAccess);
    $from = monitoringFrom();
    $counts = monitoringQuery($conn, "SELECT COUNT(*) total, COALESCE(SUM(gs.status = 'approved'),0) approved, COALESCE(SUM(gs.status = 'printed'),0) printed $from WHERE $where", $params)[0];
    $counts = array_map('intval', $counts);
    $pages = max(1, (int)ceil($counts['total'] / $f['size']));
    $page = min($f['page'], $pages);
    $offset = ($page - 1) * $f['size'];
    $order = monitoringSortColumns()[$f['sort']] . ' ' . strtoupper($f['direction']);
    $rows = monitoringQuery($conn, "SELECT gs.id, gs.gas_slip_id, gs.date_issued, d.name department, a.area_name area, gs.requested_by, gs.purpose, gs.status $from WHERE $where ORDER BY $order, gs.id DESC LIMIT ? OFFSET ?", array_merge($params, [$f['size'], $offset]));
    $routes = monitoringRoutes($conn, array_map(fn($r) => (int)$r['id'], $rows));
    foreach ($rows as &$row) { $row['routes'] = $routes[$row['id']] ?? ''; $row['date_display'] = date('m/d/Y h:i A', strtotime($row['date_issued'])); }
    unset($row);

    [$graphWhere, $graphParams] = monitoringWhere($f, $moduleAccess, true);
    // Same department/area grouping, prefixes, ordering and DISTINCT counting
    // as includes/dashboard/counts.php. Deduplicate department-area mappings.
    $graph = monitoringQuery($conn, "SELECT cd.id department_id,
        CASE WHEN cd.name IN ('ASOD','ANOD') THEN ca.id ELSE 0 END area_id,
        CASE WHEN cd.name = 'ASOD' THEN CONCAT('S-',ca.area_name) WHEN cd.name = 'ANOD' THEN CONCAT('N-',ca.area_name) ELSE cd.name END label,
        COUNT(DISTINCT scoped.id) total
        FROM departments cd LEFT JOIN (SELECT department_id, area_id FROM department_areas UNION SELECT u.department_id, gs.area_id FROM gas_slips gs JOIN users u ON u.id = gs.user_id WHERE gs.status IN ('approved','printed')) da ON da.department_id = cd.id
        LEFT JOIN areas ca ON ca.id = da.area_id
        LEFT JOIN (SELECT gs.id, u.department_id, gs.area_id $from WHERE $graphWhere) scoped
          ON scoped.department_id = cd.id AND (cd.name NOT IN ('ASOD','ANOD') OR scoped.area_id = ca.id)
        WHERE ?
        GROUP BY cd.id, CASE WHEN cd.name IN ('ASOD','ANOD') THEN ca.id ELSE 0 END
        ORDER BY cd.id, ca.area_name", array_merge($graphParams, [(int)$moduleAccess]));
    foreach ($graph as $index => &$bar) $bar['color_index'] = $index;
    unset($bar);
    return ['counts' => $counts, 'rows' => $rows, 'graph' => $graph, 'options' => monitoringOptions($conn, $userId), 'page' => $page, 'pages' => $pages, 'size' => $f['size'], 'updated' => date('m/d/Y h:i:s A')];
}

function monitoringOptions(mysqli $conn, int $userId): array
{
    if (!canAccessMonitoring($conn, $userId)) return ['departments'=>[], 'areas'=>[], 'years'=>[]];
    $from = monitoringFrom();
    return [
        'departments' => monitoringQuery($conn, 'SELECT id, name FROM departments ORDER BY id'),
        'areas' => monitoringQuery($conn, "SELECT oa.id, oa.area_name name, da.department_id FROM areas oa JOIN department_areas da ON da.area_id = oa.id
            UNION SELECT a.id, a.area_name name, u.department_id $from WHERE gs.status IN ('approved','printed') AND a.id IS NOT NULL ORDER BY name"),
        'years' => monitoringQuery($conn, "SELECT DISTINCT YEAR(gs.date_issued) year $from WHERE gs.status IN ('approved','printed') ORDER BY year DESC"),
    ];
}

function monitoringDetail(mysqli $conn, int $id, int $userId): ?array
{
    if (!canAccessMonitoring($conn, $userId)) return null;
    $rows = monitoringQuery($conn, "SELECT gs.id, gs.gas_slip_id, gs.date_issued, d.name department, a.area_name area, gs.requested_by, gs.purpose, gs.status,
        gs.validity_until, gs.recommended_at, gs.approved_at, gs.printed_at, gs.approver_type,
        CONCAT_WS(' ',ru.first_name,ru.middle_name,ru.last_name) recommended_by,
        CONCAT_WS(' ',au.first_name,au.middle_name,au.last_name) approved_by,
        CONCAT_WS(' ',v.plate_no,v.brand,v.model,v.ownership) vehicle
        " . monitoringFrom() . " LEFT JOIN vehicles v ON v.id = gs.vehicle_id LEFT JOIN users ru ON ru.id = gs.recommended_by LEFT JOIN users au ON au.id = gs.approved_by
        WHERE gs.id = ? AND gs.status IN ('approved','printed')", [$id]);
    if (!$rows) return null;
    $row = $rows[0];
    $row['routes'] = monitoringRoutes($conn, [$id])[$id] ?? '';
    $row['fuel'] = monitoringQuery($conn, 'SELECT fi.name, fr.quantity, fr.container FROM fuel_requests fr LEFT JOIN fuel_items fi ON fi.id = fr.fuel_item_id WHERE fr.gas_slip_id = ? ORDER BY fr.id', [$id]);
    return $row;
}
