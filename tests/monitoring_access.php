<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/monitoring_access.php';
require_once __DIR__ . '/../includes/monitoring.php';
require_once __DIR__ . '/../includes/gas_slip_access.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$source = getDBConnection();
$sourceName = $source->query('SELECT DATABASE()')->fetch_row()[0];
$db = 'gslip_monitor_access_' . bin2hex(random_bytes(6));
$source->query("CREATE DATABASE `$db`");
$sandbox = sys_get_temp_dir() . '/gslip_monitor_access_' . bin2hex(random_bytes(6));
$server = null; $checks = 0;
function checkMonitorAccess($ok, $message) { global $checks; if (!$ok) throw new RuntimeException($message); ++$checks; }
function accessHttp($path, $actor, $post = null) {
    global $address;
    $http = ['ignore_errors'=>true,'follow_location'=>0,'timeout'=>10];
    if ($post !== null) $http += ['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>http_build_query($post)];
    $url = 'http://' . $address . '/' . $path . (str_contains($path,'?') ? '&' : '?') . 'fixture_actor=' . $actor;
    $body = file_get_contents($url,false,stream_context_create(['http'=>$http]));
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int)$match[1],$body,$http_response_header];
}
try {
    $conn = new mysqli('localhost','root','',$db);
    foreach (['users','departments','areas','department_areas','gas_slips','vehicles','gas_slip_routes','routes','fuel_requests','fuel_items','department_recommenders','department_approvers','approval_global_settings','department_approval_settings','recommender_area_assignments','recommender_delegations','system_settings'] as $table) {
        $conn->query("CREATE TABLE `$table` LIKE `$sourceName`.`$table`");
        $conn->query("INSERT INTO `$table` SELECT * FROM `$sourceName`.`$table`");
    }
    $admin = (int)$conn->query("SELECT id FROM users WHERE role='admin' AND status='active' LIMIT 1")->fetch_row()[0];
    $user = $conn->query("SELECT id, department_id FROM users WHERE role <> 'admin' AND status='active' ORDER BY id LIMIT 1")->fetch_assoc();
    $actor = (int)$user['id']; $department = (int)$user['department_id'];
    $otherDepartment = (int)$conn->query("SELECT id FROM departments WHERE id<>$department LIMIT 1")->fetch_row()[0];
    $conn->query("DELETE FROM system_settings WHERE setting_key='monitoring_departments'");
    checkMonitorAccess(canAccessMonitoring($conn,$admin),'Admin access without settings');
    checkMonitorAccess(!canAccessMonitoring($conn,$actor),'Missing selection denies non-admin');
    saveMonitoringDepartments($conn,$admin,[$department,$otherDepartment,$department]);
    checkMonitorAccess(count(getMonitoringDepartmentIds($conn)) === 2,'Multiple departments persisted and deduplicated');
    checkMonitorAccess(canAccessMonitoring($conn,$actor),'Selected department allowed');
    $conn->query("INSERT INTO system_settings (setting_key,setting_value) VALUES ('monitoring_enabled','0') ON DUPLICATE KEY UPDATE setting_value='0'");
    checkMonitorAccess(canAccessMonitoring($conn,$actor),'Legacy disabled flag ignored');
    saveMonitoringDepartments($conn,$admin,[]);
    $conn->query("UPDATE system_settings SET setting_value='1' WHERE setting_key='monitoring_enabled'");
    checkMonitorAccess(!canAccessMonitoring($conn,$actor) && canAccessMonitoring($conn,$admin),'Empty selections admin-only regardless of global flag');
    foreach ([['999999999'],['1 OR 1=1'],['-1'],['1.0'],[['nested']], 'invalid'] as $bad) {
        try { saveMonitoringDepartments($conn,$admin,$bad); throw new RuntimeException('Invalid departments accepted'); }
        catch (InvalidArgumentException $e) { ++$checks; }
    }
    try { saveMonitoringDepartments($conn,$actor,[$department]); throw new RuntimeException('Nonadmin save allowed'); }
    catch (DomainException $e) { ++$checks; }

    mkdir($sandbox . '/includes/settings/cards',0777,true);
    foreach (['monitoring.php','save_monitoring_access.php','includes/monitoring.php','includes/monitoring_access.php','includes/system_settings.php','includes/gas_slip_access.php','includes/gas_slip_report.php','includes/recommender_assignment.php','includes/recommender_areas.php','includes/sidebar.php','includes/admin_sidebar.php','includes/settings/cards/monitoring_access_card.php'] as $file) copy(__DIR__ . '/../' . $file,$sandbox . '/' . $file);
    file_put_contents($sandbox . '/includes/config.php','<?php date_default_timezone_set("Asia/Manila"); function getDBConnection() { return new mysqli("localhost","root","",' . var_export($db,true) . '); }');
    // Real sessions keep stale admin/department flags intentionally. The policy
    // must instead use each actor's current database profile on every request.
    file_put_contents($sandbox . '/router.php','<?php ini_set("display_errors","0"); session_save_path(__DIR__); session_id("fixture-".(int)($_GET["fixture_actor"]??0)); session_start(); $_SESSION=["user_id"=>(int)($_GET["fixture_actor"]??0),"session_token"=>"fixture","role"=>"admin","department_id"=>999999,"area"=>999999,"monitoring_csrf"=>"test-csrf"]; session_write_close(); return false;');
    file_put_contents($sandbox . '/card.php','<?php session_start(); require "includes/config.php"; require "includes/monitoring_access.php"; $conn=getDBConnection(); if(!monitoringActorIsAdmin($conn,(int)$_SESSION["user_id"])) {http_response_code(403);exit;} include "includes/settings/cards/monitoring_access_card.php";');
    foreach (['sidebar','admin_sidebar'] as $sidebar) file_put_contents($sandbox . '/' . $sidebar . '.php','<?php session_start(); require "includes/config.php"; $conn=getDBConnection(); $_SERVER["PHP_SELF"]="monitoring.php"; include "includes/' . $sidebar . '.php";');
    $socket=stream_socket_server('tcp://127.0.0.1:0'); $address=stream_socket_get_name($socket,false); fclose($socket);
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$sandbox,$sandbox.'/router.php'],[0=>['pipe','r'],1=>['file',$sandbox.'/server.log','a'],2=>['file',$sandbox.'/server.log','a']],$pipes); fclose($pipes[0]);
    for($i=0;$i<30;$i++) { $socket=@stream_socket_client('tcp://'.$address,$errno,$errstr,0.1); if($socket){fclose($socket);break;} usleep(100000); }
    foreach (['monitoring.php','monitoring.php?action=data','monitoring.php?action=options','monitoring.php?action=detail&id=138','monitoring.php?action=export'] as $path) {
        [$status,$body,$headers]=accessHttp($path,$actor);
        checkMonitorAccess($status===403 && !array_filter($headers,fn($h)=>stripos($h,'Content-Disposition:')===0),'Denied page/API/detail/export before content: '.$path);
    }
    foreach (['sidebar','admin_sidebar'] as $sidebar) {
        [, $body]=accessHttp($sidebar.'.php',$actor);
        checkMonitorAccess(!str_contains($body,'href="monitoring.php"'),'Unchecked department has no nav link: '.$sidebar);
        [, $body]=accessHttp($sidebar.'.php',$admin);
        checkMonitorAccess(str_contains($body,'href="monitoring.php"'),'Admin nav always visible: '.$sidebar);
        checkMonitorAccess(substr_count($body,'href="monitoring.php"')===1 && strpos($body,'href="dashboard.php"')<strpos($body,'href="monitoring.php"') && strpos($body,'>Management<')<strpos($body,'href="monitoring.php"') && strpos($body,'href="monitoring.php"')<strpos($body,'href="reports.php"'),'One Monitoring link under Management before Reports: '.$sidebar);
        checkMonitorAccess(str_contains($body, 'href="monitoring.php" class="nav-link active"') && str_contains($body,'fa-solid fa-chart-bar'),'Monitoring active highlight and icon retained: '.$sidebar);
    }
    [$status]=accessHttp('save_monitoring_access.php',$admin,['csrf_token'=>'bad','department_ids'=>[$department]]);
    checkMonitorAccess($status===403,'Invalid CSRF rejected');
    [$status]=accessHttp('save_monitoring_access.php',$actor,['csrf_token'=>'test-csrf','department_ids'=>[$department]]);
    checkMonitorAccess($status===403,'Stale admin session cannot save settings');
    [$status]=accessHttp('save_monitoring_access.php',$admin,['csrf_token'=>'test-csrf','department_ids'=>[$department,$otherDepartment]]);
    checkMonitorAccess($status===303 && count(getMonitoringDepartmentIds($conn))===2,'Actual Save Changes persists selections');
    [$status,$body]=accessHttp('card.php',$admin);
    checkMonitorAccess($status===200 && str_contains($body,'Monitoring Module Access') && !str_contains($body,'Enable Monitoring'),'Department-only card');
    checkMonitorAccess(substr_count($body,'type="checkbox"')===(int)$conn->query('SELECT COUNT(*) FROM departments')->fetch_row()[0] && substr_count($body,'checked')===2,'Dynamic master list and saved checkmarks');
    foreach (['sidebar','admin_sidebar'] as $sidebar) {
        [, $body]=accessHttp($sidebar.'.php',$actor);
        checkMonitorAccess(str_contains($body,'href="monitoring.php"'),'Selected department sees nav: '.$sidebar);
    }
    [$status]=accessHttp('monitoring.php',$actor); checkMonitorAccess($status===200,'Next request grants access without relogin');
    [$status,$body]=accessHttp('monitoring.php?action=data',$actor);
    $data=json_decode($body,true);
    $expected=monitoringData($conn,monitoringFilters([]),$admin);
    checkMonitorAccess($status===200 && $data['counts']===$expected['counts'],'Module permission returns global counts');
    $scope=gasSlipPermissionSql('view',$actor);
    $inaccessible=$conn->query("SELECT id FROM gas_slips gs WHERE status IN ('approved','printed') AND NOT ($scope) LIMIT 1")->fetch_row();
    if($inaccessible) { [$status]=accessHttp('monitoring.php?action=detail&id='.$inaccessible[0],$actor); checkMonitorAccess($status===200,'Authorized Monitoring user can read foreign Approved/Printed record'); }
    [$status,$body]=accessHttp('monitoring.php?action=export',$actor);
    checkMonitorAccess($status===200 && str_starts_with($body,"\xEF\xBB\xBF"),'Selected department can download scoped report');
    $isd=$conn->query("SELECT u.id, u.department_id FROM users u JOIN departments d ON d.id=u.department_id JOIN department_recommenders dr ON dr.user_id=u.id AND dr.department_id=d.id WHERE d.name='ISD' AND u.status='active' LIMIT 1")->fetch_assoc();
    if(!$isd) throw new RuntimeException('ISD recommender fixture required.');
    $isdId=(int)$isd['id']; $isdDepartment=(int)$isd['department_id'];
    saveMonitoringDepartments($conn,$admin,[$isdDepartment]);
    $foreign=$conn->query("SELECT gs.id,gs.gas_slip_id,gs.area_id,u.department_id FROM gas_slips gs JOIN users u ON u.id=gs.user_id WHERE gs.status IN ('approved','printed') AND u.department_id<>$isdDepartment LIMIT 1")->fetch_assoc();
    if(!$foreign) throw new RuntimeException('Cross-department Approved/Printed fixture required.');
    $foreignId=(int)$foreign['id'];
    // Printing classification changes only in the disposable copy.
    $conn->query("UPDATE gas_slips SET status='printed' WHERE id=$foreignId");
    checkMonitorAccess(!canUserAccessGasSlip($conn,$isdId,$foreignId),'ISD operational view still rejects the foreign record');
    [$status,$body]=accessHttp('monitoring.php?action=data',$isdId);
    $isdData=json_decode($body,true); $globalData=monitoringData($conn,monitoringFilters([]),$admin);
    checkMonitorAccess($status===200 && $isdData['counts']===$globalData['counts'] && $isdData['counts']['printed']>0,'ISD recommender gets global Approved/Printed summary');
    checkMonitorAccess($isdData['graph']===$globalData['graph'] && $isdData['options']===$globalData['options'],'ISD graph and reporting choices match global admin dataset');
    checkMonitorAccess(in_array($foreignId,array_column($isdData['rows'],'id')),'ISD table includes foreign record');
    foreach (['&department='.$foreign['department_id'].'&area='.$foreign['area_id'], '&search='.rawurlencode($foreign['gas_slip_id'])] as $filter) {
        [$status,$body]=accessHttp('monitoring.php?action=data'.$filter,$isdId);
        $result=json_decode($body,true);
        checkMonitorAccess($status===200 && in_array($foreignId,array_column($result['rows'],'id')),'ISD reporting filters/search include foreign records');
    }
    [$status,$body]=accessHttp('monitoring.php?action=detail&id='.$foreignId,$isdId);
    checkMonitorAccess($status===200 && json_decode($body,true)['id']===$foreignId,'ISD can open Monitoring-only read-only foreign details');
    [$status,$body]=accessHttp('monitoring.php?action=export',$isdId);
    $csv=fopen('php://temp','w+'); fwrite($csv,substr($body,3)); rewind($csv); $columns=fgetcsv($csv); $exported=[];
    while(($row=fgetcsv($csv))!==false) $exported[]=array_combine($columns,$row);
    fclose($csv);
    checkMonitorAccess($status===200 && count($exported)===$globalData['counts']['total'] && in_array($foreign['gas_slip_id'],array_column($exported,'Gas Slip No.')),'ISD export includes all global rows and foreign slip');
    [$status,$body]=accessHttp('monitoring.php?action=data',$isdId);
    checkMonitorAccess($status===200 && json_decode($body,true)['counts']===$globalData['counts'],'Subsequent ISD refresh retains global visibility');
    checkMonitorAccess(!canUserAccessGasSlip($conn,$isdId,$foreignId) && !canUserAccessGasSlip($conn,$isdId,$foreignId,'approve') && !canUserAccessGasSlip($conn,$isdId,$foreignId,'recommend'),'Monitoring access does not grant operational view or action permissions');
    saveMonitoringDepartments($conn,$admin,[$otherDepartment]);
    [$status]=accessHttp('monitoring.php?action=data',$actor);
    checkMonitorAccess($status===403,'Removing actor department revokes existing session on next request');
    saveMonitoringDepartments($conn,$admin,[$department]);
    [$status]=accessHttp('monitoring.php?action=data',$actor);
    checkMonitorAccess($status===200,'Regrant takes effect in the same session');
    $conn->query("UPDATE users SET department_id=$otherDepartment WHERE id=$actor");
    saveMonitoringDepartments($conn,$admin,[$department]);
    [$status]=accessHttp('monitoring.php?action=data',$actor); checkMonitorAccess($status===403,'Live department change overrides stale session');
    [$status]=accessHttp('save_monitoring_access.php',$admin,['csrf_token'=>'test-csrf']);
    checkMonitorAccess($status===303 && getMonitoringDepartmentIds($conn)===[],'Empty Save Changes persists administrator-only scope');
    [$status]=accessHttp('monitoring.php?action=export',$admin); checkMonitorAccess($status===200,'Admin exports unaffected by empty selection');
    [$status]=accessHttp('monitoring.php?action=export',$actor); checkMonitorAccess($status===403,'Revocation enforced on next export');
    $conn->query("UPDATE users SET status='inactive' WHERE id=$actor");
    checkMonitorAccess(!canAccessMonitoring($conn,$actor),'Inactive user denied');
    echo "$checks monitoring module-access checks passed.\n";
} finally {
    if(is_resource($server)) {proc_terminate($server);proc_close($server);}
    if(isset($conn)) $conn->close();
    $source->query("DROP DATABASE `$db`"); $source->close();
    if(is_dir($sandbox)) {
        $resolved=realpath($sandbox); $temp=realpath(sys_get_temp_dir());
        if(!$resolved || !str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR.'gslip_monitor_access_')) throw new RuntimeException('Unsafe test cleanup path');
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        rmdir($resolved);
    }
}
