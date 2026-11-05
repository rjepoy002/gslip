<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Read-only HTTP smoke test against local XAMPP. Creates/removes one test session.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/monitoring_access.php';
require_once __DIR__ . '/../includes/gas_slip_access.php';
$conn = getDBConnection();
$isdTest = ($argv[2] ?? '') === 'isd';
$actor = $isdTest
    ? $conn->query("SELECT u.id,u.role,u.department_id,u.area_id FROM users u JOIN departments d ON d.id=u.department_id JOIN department_recommenders dr ON dr.user_id=u.id AND dr.department_id=d.id WHERE d.name='ISD' AND u.status='active' LIMIT 1")->fetch_assoc()
    : $conn->query("SELECT id, role, department_id, area_id FROM users WHERE role='admin' AND status='active' LIMIT 1")->fetch_assoc();
if (!$actor) throw new RuntimeException('An active local admin is required.');
if (!canAccessMonitoring($conn,(int)$actor['id'])) throw new RuntimeException('Test actor must already have Monitoring module access.');
$sessionId = 'monitor-test-' . bin2hex(random_bytes(12));
session_id($sessionId); session_start();
$_SESSION = ['user_id'=>$actor['id'],'role'=>$actor['role'],'department_id'=>$actor['department_id'],'area'=>$actor['area_id'],'session_token'=>bin2hex(random_bytes(20))];
if ($isdTest) $_SESSION['is_recommender'] = true;
session_write_close();
$base = $argv[1] ?? 'http://localhost/gslip/monitoring.php';
$checks = 0;
function monitoringHttp($url, $cookie = '') {
    $context = stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'header'=>$cookie ? 'Cookie: PHPSESSID=' . $cookie : '','timeout'=>15]]);
    $body = file_get_contents($url,false,$context);
    return [$http_response_header,$body];
}
function httpCheck($ok, $label) { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
try {
    [$headers,$body] = monitoringHttp($base);
    httpCheck(str_contains($headers[0],'302') && (bool)array_filter($headers,fn($h)=>stripos($h,'Location: index.php')===0),'Login redirect');
    [$headers,$body] = monitoringHttp($base . '?action=data');
    httpCheck(str_contains($headers[0],'401') && isset(json_decode($body,true)['error']),'Anonymous data denied');
    [$headers,$body] = monitoringHttp($base,$sessionId);
    httpCheck(str_contains($headers[0],'200') && str_contains($body,'Monitoring Dashboard'),'Authenticated page loads');
    [$headers,$body] = monitoringHttp($base . '?action=data&year=' . date('Y'),$sessionId);
    $data = json_decode($body,true,512,JSON_THROW_ON_ERROR);
    httpCheck(str_contains($headers[0],'200') && isset($data['counts'],$data['options']),'Real data endpoint returns JSON');
    httpCheck($data['counts']['total'] === $data['counts']['approved']+$data['counts']['printed'],'Real counts reconcile');
    if ($isdTest) {
        $expected=(int)$conn->query("SELECT COUNT(*) FROM gas_slips WHERE status IN ('approved','printed') AND YEAR(date_issued)=".(int)date('Y'))->fetch_row()[0];
        httpCheck($data['counts']['total']===$expected,'Real ISD summary is organization-wide');
        $scope=gasSlipPermissionSql('view',(int)$actor['id']);
        $foreign=$conn->query("SELECT gs.id FROM gas_slips gs JOIN users u ON u.id=gs.user_id WHERE gs.status IN ('approved','printed') AND u.department_id<>".(int)$actor['department_id']." AND NOT ($scope) LIMIT 1")->fetch_row();
        if(!$foreign) throw new RuntimeException('A foreign operationally inaccessible record is required.');
        [$foreignHeaders,$foreignBody]=monitoringHttp($base.'?action=detail&id='.$foreign[0],$sessionId);
        httpCheck(str_contains($foreignHeaders[0],'200') && (int)json_decode($foreignBody,true)['id']===(int)$foreign[0],'Real ISD Monitoring opens foreign details');
        [$operationalHeaders]=monitoringHttp(dirname($base).'/view_gas_slip_modal.php?mode=json&id='.$foreign[0],$sessionId);
        httpCheck(str_contains($operationalHeaders[0],'403'),'Real operational endpoint still denies the same foreign slip');
    }
    if ($data['rows']) {
        [$headers,$body] = monitoringHttp($base . '?action=detail&id=' . $data['rows'][0]['id'],$sessionId);
        $detail = json_decode($body,true,512,JSON_THROW_ON_ERROR);
        httpCheck(str_contains($headers[0],'200') && $detail['id'] === $data['rows'][0]['id'] && isset($detail['fuel']),'Real record details');
    }
    [$headers,$body] = monitoringHttp($base . '?action=detail&id=999999999',$sessionId);
    httpCheck(str_contains($headers[0],'404'),'Missing detail handled');
    [$headers,$body] = monitoringHttp($base . '?action=data&sort=not-a-column',$sessionId);
    httpCheck(str_contains($headers[0],'400'),'Invalid sort rejected');
    [$headers,$body] = monitoringHttp($base . '?action=export');
    httpCheck(str_contains($headers[0],'401') && !array_filter($headers,fn($h)=>stripos($h,'Content-Disposition:')===0),'Anonymous export denied before download');
    [$headers,$body] = monitoringHttp($base . '?action=export&year=' . date('Y') . '&size=10&page=999',$sessionId);
    httpCheck(str_contains($headers[0],'200') && substr($body,0,3) === "\xEF\xBB\xBF" && (bool)array_filter($headers,fn($h)=>preg_match('/Content-Disposition: attachment; filename="gas_slips_\d{8}_\d{6}\.csv"/i',$h)),'Real CSV download headers and BOM');
    $file = fopen('php://temp','w+'); fwrite($file,substr($body,3)); rewind($file); $csvHeaders=fgetcsv($file); $csvCount=0;
    while (fgetcsv($file)!==false) $csvCount++;
    fclose($file);
    httpCheck($csvCount === $data['counts']['total'] && in_array('Department',$csvHeaders) && in_array('Area',$csvHeaders),'Real export matches dashboard count');
    [$headers,$body] = monitoringHttp($base . '?action=export&search=NO-MATCH-EXPECTED',$sessionId);
    httpCheck(str_contains($headers[0],'200') && substr_count(trim($body),"\n") === 0,'Empty export still downloads column headers');
    [$headers,$body] = monitoringHttp($base . '?action=export&status=pending',$sessionId);
    httpCheck(str_contains($headers[0],'400') && !array_filter($headers,fn($h)=>stripos($h,'Content-Disposition:')===0),'Invalid export filters rejected before attachment');
} finally {
    session_id($sessionId); session_start(); $_SESSION=[]; session_destroy(); $conn->close();
}
echo "$checks monitoring HTTP checks passed" . ($isdTest ? ' for the ISD recommender (global Monitoring; operational denial preserved)' : '') . ".\n";
