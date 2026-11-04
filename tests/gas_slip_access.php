<?php
/* Isolated MySQL + HTTP regression suite; never writes the application DB. */
require_once __DIR__ . '/../includes/gas_slip_access.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '');
$db = 'gslip_access_test_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$db`");
$conn->select_db($db);
$GLOBALS['accessTestConnection'] = $conn;
function getDBConnection() { return $GLOBALS['accessTestConnection']; }
function verifyAccess($ok, $description) {
    if (!$ok) { throw new RuntimeException($description); }
    echo "PASS: $description\n";
}
function permissionIds($conn, $user, $action = 'view') {
    $scope = gasSlipPermissionSql($action, $user);
    return array_map('intval', array_column($conn->query("SELECT gs.id FROM gas_slips gs WHERE $scope ORDER BY gs.id")->fetch_all(MYSQLI_ASSOC), 'id'));
}
$server = null;
$sandbox = sys_get_temp_dir() . '/gslip_access_http_' . bin2hex(random_bytes(6));
try {
    foreach ([
        "users (id INT PRIMARY KEY, department_id INT, area_id INT, assigned_recommender_id INT, status VARCHAR(20) DEFAULT 'active', role VARCHAR(20) DEFAULT 'user', first_name VARCHAR(50) DEFAULT 'First', middle_name VARCHAR(50) DEFAULT '', last_name VARCHAR(50) DEFAULT 'Last', designation VARCHAR(50) DEFAULT '')",
        'departments (id INT PRIMARY KEY, name VARCHAR(50))',
        'areas (id INT PRIMARY KEY, area_name VARCHAR(100))',
        'department_areas (department_id INT, area_id INT)',
        'department_recommenders (id INT PRIMARY KEY, department_id INT, user_id INT)',
        'department_approvers (id INT PRIMARY KEY, department_id INT, user_id INT, is_primary TINYINT)',
        'recommender_area_assignments (department_id INT, recommender_id INT, area_id INT, UNIQUE KEY (department_id,recommender_id,area_id))',
        'recommender_delegations (id INT PRIMARY KEY, department_id INT, primary_recommender_id INT, secondary_recommender_id INT, status VARCHAR(20), start_date DATE, end_date DATE)',
        'department_approval_settings (department_id INT, area_id INT, recommender_only TINYINT)',
        'approval_global_settings (id INT PRIMARY KEY, private_vehicle_approver_user_id INT)',
        "vehicles (id INT PRIMARY KEY, ownership VARCHAR(30), plate_no VARCHAR(20) DEFAULT 'TEST', brand VARCHAR(50) DEFAULT 'Test', model VARCHAR(50) DEFAULT 'Test')",
        "gas_slips (id INT PRIMARY KEY, gas_slip_id VARCHAR(30), user_id INT, area_id INT, vehicle_id INT DEFAULT 1, status VARCHAR(20), recommended_by INT, approved_by INT, rejected_by INT, date_issued DATETIME DEFAULT CURRENT_TIMESTAMP, validity_until DATE DEFAULT '2099-01-01', purpose VARCHAR(255) DEFAULT 'Test', requested_by VARCHAR(100) DEFAULT 'Test', recommended_at DATETIME, approved_at DATETIME, rejected_at DATETIME, printed_at DATETIME, approver_type VARCHAR(20), rejection_reason TEXT)",
        'routes (id INT PRIMARY KEY, origin VARCHAR(100), destination VARCHAR(100))',
        'gas_slip_routes (id INT PRIMARY KEY, gas_slip_id INT, route_id INT)',
        'fuel_items (id INT PRIMARY KEY, name VARCHAR(50))',
        'fuel_requests (id INT PRIMARY KEY, gas_slip_id INT, fuel_item_id INT, quantity DECIMAL(10,2), container VARCHAR(50))',
        'notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, gas_slip_id INT, type VARCHAR(20), title VARCHAR(100), message VARCHAR(255), created_at DATETIME DEFAULT CURRENT_TIMESTAMP, is_displayed TINYINT DEFAULT 0, is_read TINYINT DEFAULT 0)',
        'system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value VARCHAR(100))'
    ] as $table) { $conn->query('CREATE TABLE ' . $table . ' ENGINE=InnoDB'); }
    $conn->query("INSERT INTO departments VALUES (1,'ANOD'),(2,'ASOD'),(3,'ISD')");
    $conn->query("INSERT INTO areas VALUES (1,'Roxas'),(2,'San Vicente'),(3,'Taytay'),(4,'Narra')");
    $conn->query('INSERT INTO department_areas VALUES (1,1),(1,2),(1,3),(2,4),(3,1)');
    $conn->query("INSERT INTO vehicles (id,ownership) VALUES (1,'coop-owned'),(2,'private')");
    foreach ([[10,1,1,0],[11,1,2,0],[20,1,1,10],[21,1,2,10],[22,1,3,10],[23,2,4,10],[24,1,2,11],[30,1,1,0],[31,2,4,0],[40,1,1,0],[50,3,1,0],[90,1,1,0],[91,1,1,0]] as $user) {
        $conn->query('INSERT INTO users (id,department_id,area_id,assigned_recommender_id) VALUES (' . implode(',', $user) . ')');
    }
    $conn->query("UPDATE users SET role='admin' WHERE id=90");
    $conn->query('INSERT INTO department_recommenders VALUES (1,1,10),(2,1,11),(3,3,50)');
    $conn->query('INSERT INTO department_approvers VALUES (1,1,30,1),(2,2,31,1),(3,1,91,0)');
    $conn->query('INSERT INTO approval_global_settings VALUES (1,91)');
    $conn->query('INSERT INTO recommender_area_assignments VALUES (1,10,1),(1,11,2)');
    foreach ([[101,20,'pending',null,null],[102,21,'pending',null,null],[103,22,'pending',null,null],[104,23,'pending',null,null],[105,24,'pending',null,null],[106,20,'draft',null,null],[107,21,'recommended',10,null],[108,21,'approved',10,30],[109,21,'approved',11,30],[110,23,'approved',11,31]] as $slip) {
        [$id,$owner,$status,$rec,$app] = $slip;
        $area = $conn->query('SELECT area_id FROM users WHERE id=' . $owner)->fetch_row()[0];
        $conn->query("INSERT INTO gas_slips (id,gas_slip_id,user_id,area_id,status,recommended_by,approved_by) VALUES ($id,'TEST$id',$owner,$area,'$status'," . ($rec ?? 'NULL') . ',' . ($app ?? 'NULL') . ')');
    }
    verifyAccess(canUserAccessGasSlip($conn,10,101) && !canUserAccessGasSlip($conn,10,102), 'One-area recommender can open only the assigned primary area');
    $conn->query('INSERT INTO recommender_area_assignments VALUES (1,10,2)');
    foreach ([101,102] as $id) {
        verifyAccess(canUserAccessGasSlip($conn,10,$id) && canUserAccessGasSlip($conn,10,$id,'recommend'), 'Each assigned-area pending slip can be opened and recommended: ' . $id);
    }
    verifyAccess(!canUserAccessGasSlip($conn,10,103) && !canUserAccessGasSlip($conn,10,104), 'Unassigned area and foreign department denied');
    verifyAccess(!canUserAccessGasSlip($conn,10,105) && !canUserAccessGasSlip($conn,10,105,'recommend'), 'Area membership does not bypass preparer selection');
    verifyAccess(!canUserAccessGasSlip($conn,10,106) && canUserAccessGasSlip($conn,20,106,'edit'), 'Draft view/edit remain owner-specific');
    verifyAccess(!canUserAccessGasSlip($conn,10,108,'approve') && !canUserAccessGasSlip($conn,90,107,'approve'), 'Recommenders and admins acquire no approval authority');
    verifyAccess(canUserAccessGasSlip($conn,30,107,'approve') && !canUserAccessGasSlip($conn,31,107,'approve'), 'Approval requires the configured department authority');
    verifyAccess(canUserAccessGasSlip($conn,10,109) && !canUserAccessGasSlip($conn,10,109,'recommend'), 'Assigned-area approved records allow read access without action access');
    $conn->query('DELETE FROM recommender_area_assignments WHERE recommender_id=10 AND area_id=2');
    verifyAccess(canUserAccessGasSlip($conn,10,107) && canUserAccessGasSlip($conn,10,108) && !canUserAccessGasSlip($conn,10,102), 'Historical recommendation/approval participation survives area removal; pending access is revoked');
    $conn->query('DELETE FROM department_recommenders WHERE user_id=10');
    verifyAccess(canUserAccessGasSlip($conn,10,108), 'Historical participation survives recommender deregistration');
    $conn->query('INSERT INTO department_recommenders VALUES (1,1,10)');
    $conn->query('INSERT INTO recommender_area_assignments VALUES (1,10,2)');
    $conn->query("INSERT INTO recommender_delegations VALUES (1,1,10,40,'active',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 DAY))");
    verifyAccess(canUserAccessGasSlip($conn,40,101,'recommend') && !canUserAccessGasSlip($conn,40,102,'recommend'), 'Delegation retains original same-area scope');
    $conn->query('INSERT INTO department_approval_settings VALUES (1,2,1)');
    verifyAccess(!canUserAccessGasSlip($conn,30,107,'approve'), 'Recommender-only areas do not grant ordinary approver final authority');
    $conn->query('DELETE FROM department_approval_settings');
    $conn->query("INSERT INTO gas_slips (id,gas_slip_id,user_id,area_id,vehicle_id,status) VALUES (111,'PRIVATE',21,2,2,'pending')");
    verifyAccess(!canUserAccessGasSlip($conn,10,111,'recommend') && canUserAccessGasSlip($conn,30,111,'recommend') && !canUserAccessGasSlip($conn,91,111,'recommend'), 'Private recommendation remains department-approver-only');
    $conn->query("UPDATE gas_slips SET status='recommended',recommended_by=30 WHERE id=111");
    verifyAccess(canUserAccessGasSlip($conn,91,111,'approve') && !canUserAccessGasSlip($conn,30,111,'approve'), 'Private approval remains configured-private-approver-only');
    $scope = gasSlipPermissionSql('approve',10);
    $conn->query("UPDATE gas_slips gs SET status='approved' WHERE gs.id=107 AND $scope");
    verifyAccess($conn->affected_rows === 0, 'Guarded approval UPDATE rejects recommender even via direct action');
    $scope = gasSlipPermissionSql('recommend',10);
    $conn->query("UPDATE gas_slips gs SET status='recommended' WHERE gs.id=105 AND $scope");
    verifyAccess($conn->affected_rows === 0, 'Guarded recommendation UPDATE rejects an unrelated preparer');
    $queueScope = recommenderPendingSlipSql(10);
    $queue = array_column($conn->query("SELECT gs.id FROM gas_slips gs WHERE $queueScope")->fetch_all(MYSQLI_ASSOC),'id');
    foreach ($queue as $id) { verifyAccess(canUserAccessGasSlip($conn,10,(int)$id), 'Every listed pending slip can be viewed: ' . $id); }
    $_SESSION = ['user_id'=>10,'role'=>'user','session_token'=>'fixture','department_id'=>1,'area'=>1,'is_recommender'=>true];
    $offset=0; $recordsPerPage=100;
    include __DIR__ . '/../includes/pending/query.php';
    verifyAccess((int)$totalRecords === count($queue) && $result->num_rows === count($queue), 'Actual pending module count and query agree with shared queue policy');
    $conn->rollback();
    include __DIR__ . '/../includes/dashboard/counts.php';
    verifyAccess((int)$total === count(permissionIds($conn,10)), 'Actual dashboard total matches accessible records');
    include __DIR__ . '/../includes/dashboard/recent.php';
    foreach ($recentResult as $row) { verifyAccess(canUserAccessGasSlip($conn,10,(int)$row['id']), 'Actual dashboard Recent row can open: ' . $row['id']); }
    $conn->query("INSERT INTO fuel_items VALUES (1,'Diesel')");
    $conn->query("INSERT INTO fuel_requests (id,gas_slip_id,fuel_item_id,quantity) VALUES (1,108,1,1),(2,109,1,1),(3,110,1,1)");
    foreach (['approved','fuel','vehicles','routes','drivers','fuel_items'] as $module) {
        include __DIR__ . '/../includes/dashboard/' . $module . '.php';
        verifyAccess(true, 'Dashboard query executes with shared permissions: ' . $module);
    }
    verifyAccess(array_sum($dieselData)===2.0 && array_sum($topVehicleData)===2.0, 'Dashboard fuel/vehicle totals exclude inaccessible foreign records');
    $conn->query("INSERT INTO notifications (id,user_id,gas_slip_id,type,title,message) VALUES (1,10,102,'new_pending','Test','Test'),(2,31,104,'new_pending','Other','Other')");

    // Exercise the real detail endpoint in an isolated HTTP sandbox, including
    // PHP filter_input() and JSON/body modes. Only its config is replaced.
    mkdir($sandbox . '/includes',0777,true);
    mkdir($sandbox . '/ajax');
    foreach (['view_gas_slip_modal.php','recommend_gas_slip.php','approve_gas_slip.php','reject_gas_slip.php','includes/notifications.php','includes/pagination_setup.php','includes/system_settings.php','includes/gas_slip_access.php','includes/recommender_assignment.php','includes/recommender_areas.php','ajax/get_notifications.php'] as $file) {
        copy(__DIR__ . '/../' . $file, $sandbox . '/' . $file);
    }
    file_put_contents($sandbox . '/includes/config.php', '<?php function getDBConnection() { return new mysqli("localhost","root","",' . var_export($db,true) . '); }');
    file_put_contents($sandbox . '/includes/web_push.php', '<?php function sendWebPushToUser(...$args) { return false; }');
    foreach (['recommended_gas_slips.php','approved_slips.php'] as $list) {
        // Run the real query/controller prefix without unrelated page rendering.
        $prefix = explode('?>', file_get_contents(__DIR__ . '/../' . $list), 2)[0];
        file_put_contents($sandbox . '/' . $list, $prefix . PHP_EOL . 'header("Content-Type: application/json"); echo json_encode(["total"=>$totalRecords,"rows"=>$result->fetch_all(MYSQLI_ASSOC)]);');
    }
    file_put_contents($sandbox . '/router.php', '<?php ini_set("display_errors","0"); session_save_path(__DIR__); session_start(); $_SESSION=["user_id"=>(int)($_GET["fixture_user"]??10),"role"=>"user","session_token"=>"fixture","department_id"=>999,"area"=>999,"is_recommender"=>true]; if(isset($_GET["fixture_profile"])) { require_once __DIR__."/includes/config.php"; $c=getDBConnection(); $actor=$c->query("SELECT department_id,area_id FROM users WHERE id=".(int)$_SESSION["user_id"])->fetch_assoc(); $_SESSION["department_id"]=$actor["department_id"]; $_SESSION["area"]=$actor["area_id"]; $_SESSION["is_recommender"]=in_array($_SESSION["user_id"],[10,11,40],true); $_SESSION["is_approver"]=in_array($_SESSION["user_id"],[30,31,91],true); $_SESSION["is_private_approver"]=$_SESSION["user_id"]===91; } session_write_close(); return false;');
    $socket=stream_socket_server('tcp://127.0.0.1:0');
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$sandbox,$sandbox.'/router.php'], [0=>['pipe','r'],1=>['file',$sandbox.'/server.log','a'],2=>['file',$sandbox.'/server.log','a']], $pipes);
    fclose($pipes[0]);
    $context=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>5]]);
    for($attempt=0;$attempt<30;$attempt++) {
        $body=@file_get_contents('http://'.$address.'/view_gas_slip_modal.php?id=102&mode=json',false,$context);
        if($body!==false) break;
        usleep(100000);
    }
    verifyAccess(str_contains($http_response_header[0] ?? '', '200') && (int)json_decode($body,true)['id']===102, 'Real detail JSON opens secondary-area slip despite stale session area/department');
    $body=file_get_contents('http://'.$address.'/view_gas_slip_modal.php?id=102&mode=body',false,$context);
    verifyAccess(str_contains($http_response_header[0],'200') && str_contains($body,'recommendBtn'), 'Real detail HTML opens and exposes authorized recommendation');
    $body=file_get_contents('http://'.$address.'/view_gas_slip_modal.php?id=105&mode=json',false,$context);
    verifyAccess(str_contains($http_response_header[0],'403') && isset(json_decode($body,true)['error']), 'Real direct URL rejects an unrelated preparer');
    $body=file_get_contents('http://'.$address.'/ajax/get_notifications.php',false,$context);
    $notifications=json_decode($body,true);
    verifyAccess(is_array($notifications) && count($notifications)===1 && canUserAccessGasSlip($conn,10,(int)$notifications[0]['gas_slip_id']), 'Actual notification endpoint returns only recipient records and their slip can open');
    $conn->query('DELETE FROM recommender_area_assignments WHERE recommender_id=10 AND area_id=2');
    $body=file_get_contents('http://'.$address.'/view_gas_slip_modal.php?id=107&mode=json',false,$context);
    verifyAccess(str_contains($http_response_header[0],'200'), 'Real historical detail remains accessible after reassignment');
    $body=file_get_contents('http://'.$address.'/view_gas_slip_modal.php?id=102&mode=json',false,$context);
    verifyAccess(str_contains($http_response_header[0],'403'), 'Stale pending notification/direct URL cannot bypass revoked area access');
    foreach (['recommended_gas_slips.php'=>107,'approved_slips.php'=>108] as $list=>$expected) {
        $body=file_get_contents('http://'.$address.'/'.$list,false,$context);
        $data=json_decode($body,true);
        verifyAccess(is_array($data) && (int)$data['total']===1 && (int)$data['rows'][0]['gs_id']===$expected, 'Actual tab query preserves historical visibility after reassignment: '.$list);
    }
    $body=file_get_contents('http://'.$address.'/approved_slips.php?fixture_profile=actual&fixture_user=30',false,$context);
    $data=json_decode($body,true);
    verifyAccess(is_array($data) && (int)$data['total']===2 && count($data['rows'])===2, 'Actual approved tab retains configured approver scope');
    $conn->query('INSERT INTO recommender_area_assignments VALUES (1,10,2)');
    $post=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>5,'header'=>'Content-Type: application/x-www-form-urlencoded','content'=>'id=102']]);
    $body=file_get_contents('http://'.$address.'/recommend_gas_slip.php?fixture_profile=actual',false,$post);
    verifyAccess((json_decode($body,true)['success'] ?? false) && $conn->query('SELECT status FROM gas_slips WHERE id=102')->fetch_row()[0]==='recommended', 'Actual recommendation endpoint processes assigned secondary-area slip');
    $post=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>5,'header'=>'Content-Type: application/x-www-form-urlencoded','content'=>'id=107']]);
    $body=file_get_contents('http://'.$address.'/approve_gas_slip.php?fixture_profile=actual',false,$post);
    verifyAccess((json_decode($body,true)['success'] ?? true)===false, 'Actual approval endpoint rejects a recommender');
    $body=file_get_contents('http://'.$address.'/approve_gas_slip.php?fixture_profile=actual&fixture_user=31',false,$post);
    verifyAccess((json_decode($body,true)['success'] ?? true)===false && $conn->query('SELECT status FROM gas_slips WHERE id=107')->fetch_row()[0]==='recommended', 'Actual approval endpoint rejects a foreign-department approver without state changes');
    $post=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>5,'header'=>'Content-Type: application/x-www-form-urlencoded','content'=>'id=102']]);
    $body=file_get_contents('http://'.$address.'/approve_gas_slip.php?fixture_profile=actual&fixture_user=30',false,$post);
    verifyAccess((json_decode($body,true)['success'] ?? false) && (int)$conn->query('SELECT approved_by FROM gas_slips WHERE id=102')->fetch_row()[0]===30, 'Actual configured approver can approve without recommender area constraints');
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $conn->rollback();
    $conn->query("DROP DATABASE `$db`");
    if (is_dir($sandbox)) {
        // Only this suite's freshly created, absolute sandbox is traversed.
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($sandbox);
    }
}
