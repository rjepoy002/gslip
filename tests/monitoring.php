<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Copies only to a uniquely named disposable database. Application data stays read-only.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/monitoring.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$source = getDBConnection();
$sourceName = $source->query('SELECT DATABASE()')->fetch_row()[0];
$db = 'gslip_monitor_test_' . bin2hex(random_bytes(6));
$source->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4");
$checks = 0;
function checkMonitoring($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    ++$checks;
}
try {
    $conn = new mysqli('localhost', 'root', '', $db);
    $conn->set_charset('utf8mb4');
    foreach (['users','departments','areas','department_areas','gas_slips','vehicles','gas_slip_routes','routes','fuel_requests','fuel_items','department_recommenders','department_approvers','approval_global_settings','department_approval_settings','recommender_area_assignments','recommender_delegations','system_settings'] as $table) {
        $conn->query("CREATE TABLE `$table` LIKE `$sourceName`.`$table`");
        $conn->query("INSERT INTO `$table` SELECT * FROM `$sourceName`.`$table`");
    }
    $admin = (int)$conn->query("SELECT id FROM users WHERE role='admin' AND status='active' LIMIT 1")->fetch_row()[0];
    saveMonitoringDepartments($conn,$admin,array_map('intval',array_column($conn->query('SELECT id FROM departments')->fetch_all(MYSQLI_ASSOC),'id')));
    $template = $conn->query('SELECT * FROM gas_slips ORDER BY id DESC LIMIT 1')->fetch_assoc();
    if (!$admin || !$template) throw new RuntimeException('Tests require an existing active admin and sample slip.');
    $conn->query('DELETE FROM gas_slip_routes'); $conn->query('DELETE FROM fuel_requests'); $conn->query('DELETE FROM gas_slips');
    $columns = array_keys($template);
    $insert = $conn->prepare('INSERT INTO gas_slips (`' . implode('`,`',$columns) . '`) VALUES (' . implode(',',array_fill(0,count($columns),'?')) . ')');
    foreach (['approved','printed','pending','recommended','declined','approved'] as $index => $status) {
        $row = $template; $row['id'] = 900001 + $index; $row['gas_slip_id'] = 'MONITOR-' . ($index+1);
        $row['status'] = $status; $row['date_issued'] = $index === 5 ? '2025-12-31 23:59:59' : '2026-11-05 23:59:59';
        $row['requested_by'] = 'Monitoring Requester'; $row['purpose'] = 'Inspection 100% safe <script>';
        $values = array_values($row); $insert->bind_param(str_repeat('s',count($values)), ...$values); $insert->execute();
    }
    $routeId = (int)$conn->query('SELECT id FROM routes LIMIT 1')->fetch_row()[0];
    $conn->query("INSERT INTO gas_slip_routes (gas_slip_id,route_id,sequence_no) VALUES (900001,$routeId,1),(900001,$routeId,2)");
    $f = monitoringFilters(['year'=>2026]);
    $data = monitoringData($conn,$f,$admin);
    checkMonitoring($data['counts'] === ['total'=>2,'approved'=>1,'printed'=>1], 'Mutually exclusive counts and excluded statuses');
    checkMonitoring(count($data['rows']) === 2 && count(array_unique(array_column($data['rows'],'id'))) === 2, 'No duplicate rows with multiple routes');
    checkMonitoring(array_sum(array_column($data['graph'],'total')) === 2, 'Graph reconciles with summary');
    foreach ($data['rows'] as $displayRow) {
        checkMonitoring($displayRow['requested_by']==='Monitoring Requester' && $displayRow['purpose']==='Inspection 100% safe <script>', 'AJAX retains raw fields');
        checkMonitoring($displayRow['display']['requested_by']==='MONITORING REQUESTER' && $displayRow['display']['purpose']==='Inspection 100% Safe <Script>', 'AJAX provides formatted display fields');
        checkMonitoring($displayRow['display']['routes']===formatRoute($displayRow['routes']), 'Route display preserves original route payload');
    }

    checkMonitoring(str_contains(monitoringDetail($conn,900001,$admin)['routes'],'; '), 'Details retain multiple routes');
    checkMonitoring(monitoringDetail($conn,900003,$admin) === null, 'Pending details excluded');
    checkMonitoring(monitoringDetail($conn,900001,999999) === null, 'Inaccessible detail denied');
    foreach (['approved','printed'] as $status) {
        $filtered = monitoringData($conn,monitoringFilters(['year'=>2026,'status'=>$status]),$admin);
        checkMonitoring($filtered['counts']['total'] === 1 && $filtered['rows'][0]['status'] === $status, 'Status filter ' . $status);
    }
    foreach (['MONITOR-1','Monitoring Requester','monitoring requester','MONITORING REQUESTER','100% safe','<script>'] as $search) {
        $d = monitoringData($conn,monitoringFilters(['year'=>2026,'search'=>$search]),$admin);
        checkMonitoring($d['counts']['total'] === ($search === 'MONITOR-1' ? 1 : 2), 'Literal keyword search ' . $search);
    }
    checkMonitoring(monitoringData($conn,monitoringFilters(['year'=>2026,'from'=>'2026-11-05','to'=>'2026-11-05']),$admin)['counts']['total'] === 2, 'Inclusive date range includes last second');
    checkMonitoring(monitoringData($conn,monitoringFilters(['year'=>2026,'to'=>'2026-11-04']),$admin)['counts']['total'] === 0, 'Empty date result');
    checkMonitoring(monitoringData($conn,monitoringFilters(['year'=>2025]),$admin)['counts']['total'] === 1, 'Year filter');
    $department = (int)$conn->query('SELECT department_id FROM users WHERE id=' . (int)$template['user_id'])->fetch_row()[0];
    checkMonitoring(monitoringData($conn,monitoringFilters(['year'=>2026,'department'=>$department,'area'=>$template['area_id']]),$admin)['counts']['total'] === 2, 'Department and area filters');
    checkMonitoring(monitoringData($conn,monitoringFilters(['year'=>2026,'department'=>999999]),$admin)['counts']['total'] === 0, 'Foreign department filter empty');
    foreach (array_keys(monitoringSortColumns()) as $sort) foreach (['asc','desc'] as $direction) {
        $d = monitoringData($conn,monitoringFilters(['year'=>2026,'sort'=>$sort,'direction'=>$direction]),$admin);
        checkMonitoring(count($d['rows']) === 2, 'Sorting ' . $sort . ' ' . $direction);
    }
    $d = monitoringData($conn,monitoringFilters(['year'=>2026,'page'=>999,'size'=>10]),$admin);
    checkMonitoring($d['page'] === 1 && $d['pages'] === 1,'Out-of-range page clamped');
    foreach ($conn->query('SELECT id FROM users')->fetch_all(MYSQLI_ASSOC) as $actor) {
        $id = (int)$actor['id'];
        $expected = canAccessMonitoring($conn,$id) ? 2 : 0;
        checkMonitoring(monitoringData($conn,$f,$id)['counts']['total'] === $expected,'Global monitoring scope for authorized actor ' . $id);
    }
    foreach ([['from'=>'2026-11-06','to'=>'2026-11-05'], ['sort'=>'id; DROP TABLE gas_slips'], ['size'=>11], ['status'=>'pending'], ['from'=>'2026-02-30'], ['year'=>[]]] as $bad) {
        try { monitoringFilters($bad); throw new RuntimeException('Invalid input accepted'); }
        catch (InvalidArgumentException $e) { ++$checks; }
    }
    $options = monitoringOptions($conn,$admin);
    checkMonitoring(count($options['departments']) > 0 && count($options['areas']) > 0,'Database filter options');
    checkMonitoring(monitoringData($conn,$f,999999)['counts']['total'] === 0,'Unknown actor gets no records');
    $destination = $conn->query('SELECT destination FROM routes WHERE id=' . $routeId)->fetch_row()[0];
    checkMonitoring(monitoringData($conn,monitoringFilters(['year'=>2026,'search'=>$destination]),$admin)['counts']['total'] === 1, 'Route keyword uses EXISTS without duplicated counts');
    for ($i=0; $i<30; $i++) {
        $row = $template; $row['id'] = 901000+$i; $row['gas_slip_id'] = 'PAGE-' . $i;
        $row['status'] = 'approved'; $row['date_issued'] = '2026-11-05 23:59:59';
        $values = array_values($row); $insert->bind_param(str_repeat('s',count($values)), ...$values); $insert->execute();
    }
    $seen = [];
    for ($p=1; $p<=4; $p++) {
        $paged = monitoringData($conn,monitoringFilters(['year'=>2026,'page'=>$p,'size'=>10]),$admin);
        checkMonitoring($paged['counts']['total'] === 32 && $paged['pages'] === 4,'Paginated counts stay global');
        array_push($seen,...array_column($paged['rows'],'id'));
    }
    checkMonitoring(count($seen) === 32 && count(array_unique($seen)) === 32,'Stable tie sorting across pages has no skipped/duplicate records');
    foreach ([10,25,50,100] as $size) {
        checkMonitoring(count(monitoringData($conn,monitoringFilters(['year'=>2026,'size'=>$size]),$admin)['rows']) === min(32,$size),'Page size ' . $size);
    }
    function exportMonitoringRows($conn, $f, $actor) {
        $file = monitoringExportCsv($conn,$f,$actor);
        checkMonitoring(fread($file,3) === "\xEF\xBB\xBF",'Export preserves Excel UTF-8 BOM');
        $headers = fgetcsv($file); $rows = [];
        while (($row = fgetcsv($file)) !== false) $rows[] = array_combine($headers,$row);
        fclose($file);
        return [$headers,$rows];
    }
    [$headers,$exported] = exportMonitoringRows($conn,monitoringFilters(['year'=>2026,'page'=>3,'size'=>10,'sort'=>'number','direction'=>'asc']),$admin);
    checkMonitoring(array_slice($headers,0,8) === ['Gas Slip No.','Date Issued','Department','Area','Requested By','Purpose','Routes','Status'],'Monitoring export required columns');
    $formattedExport=array_values(array_filter($exported,fn($r)=>$r['Gas Slip No.']==='MONITOR-1'))[0];
    checkMonitoring($formattedExport['Requested By']==='MONITORING REQUESTER' && $formattedExport['Purpose']==='Inspection 100% Safe <Script>', 'Generated CSV uses shared display formatting');
    $unchanged=$conn->query('SELECT requested_by,purpose FROM gas_slips WHERE id=900001')->fetch_assoc();
    checkMonitoring($unchanged===['requested_by'=>'Monitoring Requester','purpose'=>'Inspection 100% safe <script>'],'Display and export leave stored values unchanged');
    checkMonitoring(count($exported) === 32 && count(array_unique(array_column($exported,'Gas Slip No.'))) === 32,'Export ignores pagination and never duplicates printed/routes');
    $numbers = array_column($exported,'Gas Slip No.'); $sorted = $numbers; sort($sorted);
    checkMonitoring($numbers === $sorted,'Export preserves selected sorting');
    checkMonitoring(count($headers) === count(gasSlipReportHeaders())+2,'Existing additional report fields retained');
    foreach ([['status'=>'printed'],['status'=>'approved'],['search'=>'MONITOR-1'],['search'=>$destination],['department'=>$department,'area'=>$template['area_id']],['from'=>'2026-11-05','to'=>'2026-11-05'],['year'=>2025],['search'=>'NO-MATCH-EXPECTED'],['department'=>999999]] as $filter) {
        $ef = monitoringFilters(array_merge(['year'=>2026],$filter));
        [, $erows] = exportMonitoringRows($conn,$ef,$admin);
        $expected = monitoringData($conn,$ef,$admin)['counts']['total'];
        checkMonitoring(count($erows) === $expected,'Export and dashboard use identical filters: ' . json_encode($filter));
        checkMonitoring(!array_diff(array_unique(array_column($erows,'Status')),['Approved','Printed']),'Only approved/printed exported');
    }
    foreach ($conn->query('SELECT id FROM users')->fetch_all(MYSQLI_ASSOC) as $actor) {
        [, $erows] = exportMonitoringRows($conn,$f,(int)$actor['id']);
        checkMonitoring(count($erows) === monitoringData($conn,$f,(int)$actor['id'])['counts']['total'],'Export enforces current actor permissions ' . $actor['id']);
    }
    foreach (['=SUM(1,1)','+CMD','-CMD','@SUM(1,1)',"  =1+1","\t=1+1","\rCMD","\nCMD","\xEF\xBB\xBF=1+1"] as $dangerous) {
        checkMonitoring(gasSlipReportSafeCell($dangerous) === "'".$dangerous,'Formula injection escaped');
    }
    checkMonitoring(gasSlipReportSafeCell('Ordinary text') === 'Ordinary text','Safe text preserved');
    $conn->query("UPDATE gas_slips SET purpose='=1+1' WHERE id=900001");
    [, $injection] = exportMonitoringRows($conn,monitoringFilters(['year'=>2026,'search'=>'MONITOR-1']),$admin);
    checkMonitoring($injection[0]['Purpose'] === "'=1+1",'Formula injection protected in generated file');
    for ($i=0; $i<480; $i++) {
        $row = $template; $row['id'] = 902000+$i; $row['gas_slip_id'] = 'BATCH-' . $i;
        $row['status'] = 'printed'; $row['date_issued'] = '2026-11-05 23:59:59';
        $values = array_values($row); $insert->bind_param(str_repeat('s',count($values)), ...$values); $insert->execute();
    }
    [, $batchRows] = exportMonitoringRows($conn,$f,$admin);
    checkMonitoring(count($batchRows) === 512 && count(array_unique(array_column($batchRows,'Gas Slip No.'))) === 512,'Export crosses 500-row batch boundary without missing or duplicated records');
    echo "$checks monitoring checks passed.\n";
} finally {
    if (isset($conn)) $conn->close();
    $source->query("DROP DATABASE `$db`");
    $source->close();
}
