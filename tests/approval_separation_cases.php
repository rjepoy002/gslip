<?php
// Runs only inside the existing isolated MySQL/HTTP bulk regression sandbox.
foreach(['approve_gas_slip_08272026.php','approve_gas_slip_09022026.php','reject_gas_slip_07132026.php'] as $file)copy(__DIR__.'/../'.$file,$sandbox.'/'.$file);
if(!is_dir($sandbox.'/includes/pending'))mkdir($sandbox.'/includes/pending');
copy(__DIR__.'/../includes/pending/query.php',$sandbox.'/includes/pending/query.php');
$prefix=explode('?>',file_get_contents(__DIR__.'/../pending_gas_slips.php'),2)[0];
file_put_contents($sandbox.'/pending_gas_slips.php',$prefix.' header("Content-Type: application/json"); echo json_encode(["total"=>$totalRecords,"rows"=>$result->fetch_all(MYSQLI_ASSOC)]);');
// Resolve flags from actual fixture assignments, including changes during the test.
$router=file_get_contents($sandbox.'/router.php');
$router=str_replace('session_write_close();return false;', <<<'PHP'
require_once __DIR__.'/includes/config.php';$fixtureConn=getDBConnection();
$fixtureUser=(int)$_SESSION['user_id'];
$fixtureActor=$fixtureConn->query('SELECT department_id,area_id FROM users WHERE id='.$fixtureUser)->fetch_assoc();
$_SESSION['department_id']=$fixtureActor['department_id'];$_SESSION['area']=$fixtureActor['area_id'];
$_SESSION['is_recommender']=$fixtureConn->query('SELECT 1 FROM department_recommenders WHERE user_id='.$fixtureUser.' AND department_id='.(int)$fixtureActor['department_id'])->num_rows>0;
$_SESSION['is_approver']=$fixtureConn->query('SELECT 1 FROM department_approvers WHERE user_id='.$fixtureUser.' AND department_id='.(int)$fixtureActor['department_id'])->num_rows>0;
session_write_close();return false;
PHP
,$router);
file_put_contents($sandbox.'/router.php',$router);
function separationPost($endpoint,$payload,$actor=10) {
    global $address;
    $ctx=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>8,'header'=>"Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=bulktest".$actor,'content'=>http_build_query($payload)]]);
    return file_get_contents('http://'.$address.'/'.$endpoint.'?fixture_user='.$actor,false,$ctx);
}
function separationIds($file,$actor) {return array_map('intval',array_column(historyRows($file,0,$actor)['rows'],'gs_id'));}
$conn->query('DELETE FROM department_approval_settings WHERE department_id=1 AND area_id=1');
addBulkSlip(301,'Separation Test');addBulkSlip(302,'Separation Test');
foreach([301,302] as $id)verifyAccess(json_decode(separationPost('recommend_gas_slip.php',['id'=>$id]),true)['success'],'Original recommender creates persisted recommendation '.$id);
$original=$conn->query('SELECT id,recommended_by,recommended_at FROM gas_slips WHERE id IN (301,302) ORDER BY id')->fetch_all(MYSQLI_ASSOC);
verifyAccess(canUserAccessGasSlip($conn,30,301,'approve'),'Independent department approver can process normal recommendation');
$conn->query('DELETE FROM department_recommenders WHERE user_id=10');
$conn->query('INSERT INTO department_approvers VALUES (9,1,10,0)');
$conn->query('INSERT INTO department_approval_settings VALUES (1,1,1)');
verifyAccess(canUserAccessGasSlip($conn,30,301,'approve') && canUserAccessGasSlip($conn,30,302,'reject'),'Changing author role and current recommender-only setting does not invalidate completed recommendations');
verifyAccess(!canUserAccessGasSlip($conn,10,301,'approve') && !canUserAccessGasSlip($conn,10,301,'reject'),'Persisted original recommender cannot self-approve or approval-stage reject');
verifyAccess(in_array(301,separationIds('pending_gas_slips.php',30),true),'Real independent Approver Pending query retains the previously stuck recommendation');
verifyAccess(!in_array(301,separationIds('pending_gas_slips.php',10),true) && in_array(301,separationIds('recommended_gas_slips.php',10),true),'Approval Only author loses own Pending action but retains Recommended history');
$notificationsBefore=(int)$conn->query('SELECT COUNT(*) FROM notifications WHERE gas_slip_id IN (301,302)')->fetch_row()[0];
foreach(['approve_gas_slip.php','approve_gas_slip_08272026.php','approve_gas_slip_09022026.php'] as $endpoint)verifyAccess(!json_decode(separationPost($endpoint,['id'=>301]),true)['success'],'Direct self-approval denied by '.$endpoint);
foreach(['reject_gas_slip.php','reject_gas_slip_07132026.php'] as $endpoint)separationPost($endpoint,['gas_slip_id'=>302,'rejection_reason'=>'Forbidden self rejection']);
verifyAccess((int)$conn->query('SELECT COUNT(*) FROM notifications WHERE gas_slip_id IN (301,302)')->fetch_row()[0]===$notificationsBefore,'Denied direct actions create no notifications');
verifyAccess($conn->query('SELECT id,recommended_by,recommended_at FROM gas_slips WHERE id IN (301,302) ORDER BY id')->fetch_all(MYSQLI_ASSOC)===$original && (int)$conn->query("SELECT COUNT(*) FROM gas_slips WHERE id IN (301,302) AND status='recommended' AND approved_by IS NULL AND rejected_by IS NULL")->fetch_row()[0]===2,'Denied actions and permission changes preserve status, recommendation identity/timestamps and approval history');
$body=file_get_contents('http://'.$address.'/view_gas_slip_modal.php?fixture_user=10&id=301&mode=body');
verifyAccess(!str_contains($body,'id="approveBtn"') && !str_contains($body,'rejectBtn'),'Own historical detail opens without Approve or Reject controls');
foreach([303,304] as $id){addBulkSlip($id,'Separation Test',20,'recommended');$conn->query('UPDATE gas_slips SET recommended_by=11,recommended_at=NOW() WHERE id='.$id);}
addBulkSlip(305,'Missing author',20,'recommended');
verifyAccess(!canUserAccessGasSlip($conn,30,305,'approve') && !canUserAccessGasSlip($conn,30,305,'reject') && canUserAccessGasSlip($conn,90,305),'Missing original recommender fails closed while existing admin oversight can view it');
$groupA=(int)bulkHttp('create',['name'=>'Separation group'])['group'];bulkHttp('add_name',['group'=>$groupA,'name'=>'Separation Test']);
$scope=bulkHttp('state',['group'=>$groupA]);
verifyAccess(bulkIds($scope['records'])===[303,304] && $scope['counts'][$groupA]===2,'Bulk records, group counts and Select All exclude own recommendations but include independent ones');
verifyAccess(!snapshotBulk('approve',$groupA,[301,303])['success'] && !snapshotBulk('reject',$groupA,[301,303])['success'],'Forged bulk approval and rejection snapshots cannot include an own recommendation');
$snap=snapshotBulk('approve',$groupA,[303,304]);
$done=bulkHttp('process',['token'=>$snap['token'],'acknowledge'=>true]);
verifyAccess(count($done['summary']['successful'])===2,'Approval Only author can bulk approve two independently recommended records');
verifyAccess(json_decode(separationPost('approve_gas_slip.php',['id'=>301],30),true)['success'],'Independent individual approval restores processing despite current recommender-only setting');
separationPost('reject_gas_slip.php',['gas_slip_id'=>302,'rejection_reason'=>'Independent review'],30);
verifyAccess($conn->query('SELECT status FROM gas_slips WHERE id=302')->fetch_row()[0]==='rejected','Independent approval-stage rejection remains functional');
foreach([308,309] as $id){addBulkSlip($id,'Independent rejection',20,'recommended');$conn->query('UPDATE gas_slips SET recommended_by=10,recommended_at=NOW() WHERE id='.$id);}
$groupB=(int)bulkHttp('create',['name'=>'Independent rejection'],30)['group'];bulkHttp('add_name',['group'=>$groupB,'name'=>'Independent rejection'],30);
$reject=snapshotBulk('reject',$groupB,[308,309],30);
$rejected=bulkHttp('process',['token'=>$reject['token'],'acknowledge'=>true,'reason'=>'Independent bulk review'],30);
verifyAccess(count($rejected['summary']['successful'])===2 && (int)$conn->query("SELECT COUNT(*) FROM gas_slips WHERE id IN (308,309) AND status='rejected' AND rejection_reason='Independent bulk review'")->fetch_row()[0]===2,'Independent bulk rejection preserves shared reasons after author permission changes');
foreach([310,311] as $id){addBulkSlip($id,'Separation Test',20,'recommended');$conn->query('UPDATE gas_slips SET recommended_by=11,recommended_at=NOW() WHERE id='.$id);}
$revalidate=snapshotBulk('approve',$groupA,[310,311]);
$conn->query('UPDATE gas_slips SET recommended_by=10 WHERE id=310');
verifyAccess(!bulkHttp('process',['token'=>$revalidate['token'],'acknowledge'=>true])['success'],'Final bulk revalidation consults persisted recommendation identity again');
verifyAccess($conn->query('SELECT status FROM gas_slips WHERE id=311')->fetch_row()[0]==='recommended','Invalidated batch does not transition an unaffected peer');
// No remaining independent approver: keep the recommendation awaiting oversight.
addBulkSlip(307,'Separation Test',20,'recommended');$conn->query('UPDATE gas_slips SET recommended_by=10,recommended_at=NOW() WHERE id=307');
$savedApprovers=$conn->query('SELECT * FROM department_approvers WHERE user_id IN (30,91)')->fetch_all(MYSQLI_ASSOC);
$conn->query('DELETE FROM department_approvers WHERE user_id IN (30,91)');
$independent=[];foreach($conn->query("SELECT id FROM users WHERE status='active'") as $actor)if(canUserAccessGasSlip($conn,(int)$actor['id'],307,'approve'))$independent[]=$actor['id'];
verifyAccess(!$independent && canUserAccessGasSlip($conn,90,307) && $conn->query('SELECT status FROM gas_slips WHERE id=307')->fetch_row()[0]==='recommended','No independent approver leaves recommendation unchanged and visible to existing administrator oversight');
foreach($savedApprovers as $approver)$conn->query('INSERT INTO department_approvers(id,department_id,user_id,is_primary) VALUES('.implode(',',array_map('intval',[$approver['id'],$approver['department_id'],$approver['user_id'],$approver['is_primary']])).')');
$conn->query('UPDATE users SET department_id=2,area_id=4 WHERE id=10');
verifyAccess(canUserAccessGasSlip($conn,30,307,'approve') && !canUserAccessGasSlip($conn,31,307,'approve'),'Author department/area changes do not revoke an independent approver or broaden foreign-department access');
verifyAccess(in_array(307,separationIds('recommended_gas_slips.php',10),true),'Recommendation history remains after all matching workflow role flags are removed');
$conn->query('UPDATE users SET department_id=1,area_id=1 WHERE id=10');
// All inherited oversight/reporting remains read-only; no identity is inferred from notifications.
