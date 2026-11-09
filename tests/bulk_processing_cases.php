<?php
require_once __DIR__.'/../includes/bulk_processing.php';
$conn->query('ALTER TABLE users ADD session_token VARCHAR(64)');
$conn->query("UPDATE users SET session_token='fixture'");
foreach ([1,2] as $repeat) {
    $conn->multi_query(file_get_contents(__DIR__.'/../migrations/20261106_bulk_processing.sql'));
    do { if ($r=$conn->store_result()) { $r->free(); } } while ($conn->more_results() && $conn->next_result());
}
verifyAccess(true,'Additive group migration remains repeatable');
foreach (['bulk_processing.php','includes/bulk_processing.php','includes/bulk_table_query.php'] as $file) {copy(__DIR__.'/../'.$file,$sandbox.'/'.$file);}
file_put_contents($sandbox.'/router.php', <<<'PHP'
<?php
ini_set('display_errors','1'); error_reporting(E_ALL); session_save_path(__DIR__); session_start();
$id=(int)($_GET['fixture_user']??10);
if(!$id) {$_SESSION=[];session_write_close();return false;}
$_SESSION=array_replace($_SESSION,['user_id'=>$id,'role'=>'user','session_token'=>'fixture','bulk_csrf_pending'=>'fixture-csrf','bulk_csrf_recommended'=>'history-rec','bulk_csrf_approved'=>'history-app','department_id'=>1,'area'=>1,'is_recommender'=>in_array($id,[10,11,40]),'is_approver'=>in_array($id,[30,31,91]),'is_private_approver'=>$id===91]);
session_write_close();return false;
PHP
);
function bulkHttp($op,$data=[],$user=10) {
    global $address,$bulkLastHttp;
    $payload=array_replace(['op'=>$op,'page'=>'pending','csrf'=>'fixture-csrf','group'=>0],$data);
    $context=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'timeout'=>10,'header'=>"Content-Type: application/json\r\nCookie: PHPSESSID=bulktest$user",'content'=>json_encode($payload)]]);
    $body=file_get_contents('http://'.$address.'/bulk_processing.php?fixture_user='.$user,false,$context);
    $bulkLastHttp=['headers'=>$http_response_header,'body'=>$body];
    $result=json_decode($body,true);
    if(!is_array($result))throw new RuntimeException('Invalid bulk response: '.$body);
    return $result;
}
function addBulkSlip($id,$name='R. Socrates',$owner=20,$status='pending',$vehicle=1) {
    global $conn;
    bulkQuery($conn,'INSERT INTO gas_slips(id,gas_slip_id,user_id,area_id,vehicle_id,status,requested_by) VALUES(?,?,?,1,?,?,?)','isiiss',[$id,'BULK'.$id,$owner,$vehicle,$status,$name]);
}
function snapshotBulk($action,$group=0,$selected=[],$user=10) {
    $state=bulkHttp('state',['group'=>$group],$user);
    return bulkHttp('snapshot',['group'=>$group,'action'=>$action,'ids'=>$selected,'eligible_ids'=>bulkIds($state['records'])],$user);
}
verifyAccess(!bulkHttp('state',[],0)['success'],'Authentication required');
verifyAccess(!bulkHttp('state',['csrf'=>'bad'])['success'],'CSRF required');
verifyAccess(!bulkHttp('state',[],20)['success'],'Regular preparer cannot manage groups');
foreach (['recommend','approve','reject'] as $operation) {
    $one=['ids'=>[1],'strong'=>false,'action'=>$operation];
    $multiple=['ids'=>[1,2],'strong'=>false,'action'=>$operation];
    $fields=['reason'=>'Shared reason'];
    verifyAccess(bulkConfirmationValid($one,$fields),'Single-record standard '.$operation.' does not require acknowledgment');
    verifyAccess(!bulkConfirmationValid($multiple,$fields),'Two-record '.$operation.' requires acknowledgment from validated IDs');
    verifyAccess(bulkConfirmationValid($multiple,$fields+['acknowledge'=>true]),'Two-record '.$operation.' accepts acknowledgment without a typed phrase');
    if ($operation==='reject') {
        verifyAccess(!bulkConfirmationValid($multiple,['acknowledge'=>true,'reason'=>' ']),'Acknowledgment never bypasses mandatory shared rejection reason');
    } else {
        $strong=$one;$strong['strong']=true;
        verifyAccess(!bulkConfirmationValid($strong,['acknowledge'=>true]),'One-record strong '.$operation.' retains typed confirmation');
        verifyAccess(!bulkConfirmationValid($strong,['phrase'=>strtoupper($operation).' ALL']),'One-record strong '.$operation.' retains acknowledgment');
    }
}
$group=(int)bulkHttp('create',['name'=>' Routine   Field Trips '])['group'];
verifyAccess(!bulkHttp('create',['name'=>'routine field trips'])['success'],'Normalized group names remain unique');
verifyAccess(!bulkHttp('rename',['group'=>$group,'name'=>'stolen'],11)['success'],'Groups remain private to their owner');
verifyAccess(bulkHttp('add_name',['group'=>$group,'name'=>' R.  Socrates '])['success'],'Manual Requested By entry is preserved');
$g2=(int)bulkHttp('create',['name'=>'Other'])['group'];
verifyAccess(bulkHttp('rename',['group'=>$g2,'name'=>'Other renamed'])['success'],'Existing API permits confirmed inline rename by owner');
verifyAccess(bulkHttp('rename',['group'=>$g2,'name'=>'Other'])['success'],'Owner can restore group name without affecting membership');
verifyAccess(bulkHttp('add_name',['group'=>$g2,'name'=>'Temporary Requested By'])['success'],'Manual names can be added to selected group');
$temporaryNameId=(int)$conn->query('SELECT id FROM bulk_processing_group_names WHERE group_id='.$g2)->fetch_row()[0];
verifyAccess(!bulkHttp('remove_name',['group'=>$g2,'name_id'=>$temporaryNameId],11)['success'],'Another account cannot remove configured group names');
verifyAccess(bulkHttp('remove_name',['group'=>$g2,'name_id'=>$temporaryNameId])['success'],'Owner can remove a configured name without removing slips');
verifyAccess(!bulkHttp('add_name',['group'=>$g2,'name'=>'r. socrates'])['success'],'Owner-wide normalized duplicate membership rejected');
$other=(int)bulkHttp('create',['name'=>'Routine'],11)['group'];
verifyAccess(bulkHttp('add_name',['group'=>$other,'name'=>'R. Socrates'],11)['success'],'Different owners may share Requested By names');
addBulkSlip(201,' r.  socrates ');addBulkSlip(202);addBulkSlip(203,'Other Name');addBulkSlip(204,'R. Socrates',23);
verifyAccess(bulkIds(bulkHttp('state',['group'=>$group])['records'])===[201,202],'Pending groups dynamically match normalized names with department authorization');
addBulkSlip(205);
verifyAccess(bulkIds(bulkHttp('state',['group'=>$group])['records'])===[201,202,205],'New matching records join automatically');
$tabState=bulkHttp('state');
verifyAccess($tabState['counts'][0]===count($tabState['records']) && $tabState['counts'][$group]===3 && $tabState['counts'][$g2]===0,'Server tab counts cover the full authorized queue and normalized group membership, including zero-record groups');
$groupState=bulkHttp('state',['group'=>$group]);
verifyAccess($groupState['counts']===$tabState['counts'] && count($groupState['records'])===3,'All tab counts remain independent of the active group');
addBulkSlip(206,'Unique Requested Name');addBulkSlip(207,' Unique  Requested Name ');addBulkSlip(208,'Secret Unauthorized Name',23);
verifyAccess(count(bulkHttp('suggest',['query'=>' UNIQUE  requested '])['names'])===1,'Autocomplete normalizes and deduplicates actual Requested By values');
bulkHttp('add_name',['group'=>$g2,'name'=>'Other Name']);
verifyAccess(bulkHttp('state')['counts'][$g2]===1,'Eligible tab count refreshes after adding a matching Requested By name');
$countNameId=(int)$conn->query('SELECT id FROM bulk_processing_group_names WHERE group_id='.$g2)->fetch_row()[0];
bulkHttp('remove_name',['group'=>$g2,'name_id'=>$countNameId]);
verifyAccess(bulkHttp('state')['counts'][$g2]===0,'Eligible tab count refreshes after removing Requested By membership');
verifyAccess(bulkHttp('suggest',['query'=>'secret'])['names']===[],'Autocomplete preserves visibility authorization');
verifyAccess(bulkHttp('suggest',['query'=>'socrates'])['names']===[],'Autocomplete omits already assigned names');
bulkQuery($conn,'INSERT INTO bulk_processing_exclusions(user_id,gas_slip_id,reason) VALUES(?,?,?)','iis',[10,201,'Stored legacy review']);
verifyAccess(in_array(201,bulkIds(bulkHttp('state')['records']),true),'Legacy exclusions no longer restrict bulk eligibility');
verifyAccess(!bulkHttp('exclude',['id'=>201,'excluded'=>false])['success'],'Removed exclusion endpoint cannot modify stored data');
verifyAccess((int)$conn->query('SELECT COUNT(*) FROM bulk_processing_exclusions')->fetch_row()[0]===1,'Existing exclusion data retained untouched');
verifyAccess(bulkHttp('state')['action']==='recommend' && bulkHttp('state',[],30)['action']==='approve','Actions resolve from database assignments without a selector');
verifyAccess(str_contains(implode('\n',$bulkLastHttp['headers']),'application/json') && !str_contains($bulkLastHttp['body'],'<br'),'Authenticated Approver initialization returns clean JSON headers/body with PHP warning display enabled');
verifyAccess(bulkHttp('state')['success'] && !str_contains($bulkLastHttp['body'],'Warning'),'Authenticated Recommender initialization returns clean JSON without stale exclusion-variable warnings');
// Unexpected warnings and database faults must be logged, never exposed in JSON.
$bulkLibrary=file_get_contents($sandbox.'/includes/bulk_processing.php');
file_put_contents($sandbox.'/includes/bulk_processing.php',$bulkLibrary."\ntrigger_error('fixture private warning with /secret/path', E_USER_WARNING);\n");
$fault=bulkHttp('state');
verifyAccess(!$fault['success'] && str_contains($bulkLastHttp['headers'][0],'500') && !str_contains($bulkLastHttp['body'],'/secret/path') && !str_contains($bulkLastHttp['body'],'Warning'),'Unexpected PHP warning becomes sanitized HTTP 500 JSON');
file_put_contents($sandbox.'/includes/bulk_processing.php',$bulkLibrary);
$conn->query('RENAME TABLE bulk_processing_group_names TO fixture_group_names');
$fault=bulkHttp('state');
verifyAccess(!$fault['success'] && str_contains($bulkLastHttp['headers'][0],'500') && !str_contains($bulkLastHttp['body'],'SELECT') && !str_contains($bulkLastHttp['body'],'bulk_processing_group_names'),'SQL failures return sanitized HTTP 500 JSON without schema details');
$conn->query('RENAME TABLE fixture_group_names TO bulk_processing_group_names');
verifyAccess(bulkHttp('state')['success'],'Initialization recovers after a temporary storage fault');
verifyAccess(!snapshotBulk('approve',0,[201])['success'],'Recommender cannot forge bulk approval');
verifyAccess(!snapshotBulk('recommend',0,[],30)['success'],'Approver cannot forge a bulk recommendation action');
verifyAccess(!snapshotBulk('reject',0,[201])['success'],'All Gas Slips never offers backend bulk rejection');
verifyAccess(!snapshotBulk('reject',$group,[])['success'],'Group rejection requires explicit selection');
verifyAccess(!snapshotBulk('recommend',$group,[204])['success'],'Forged foreign-department IDs denied');
foreach (['recommended'=>'history-rec','approved'=>'history-app'] as $context=>$csrf) {
    verifyAccess(bulkHttp('state',['page'=>$context,'csrf'=>$csrf,'group'=>$group])['records']===[],'History group context exposes no actionable records: '.$context);
    foreach (['snapshot','process','validate'] as $operation) {
        verifyAccess(!bulkHttp($operation,['page'=>$context,'csrf'=>$csrf,'action'=>'recommend','ids'=>[201]])['success'],'Read-only context rejects '.$operation.': '.$context);
    }
    verifyAccess(!bulkHttp('snapshot',['page'=>'pending','csrf'=>$csrf,'action'=>'recommend'])['success'],'History token cannot masquerade as pending context: '.$context);
}
$snap=snapshotBulk('recommend');$token=$snap['token'];
verifyAccess($snap['snapshot']['strong'] && $snap['snapshot']['all'],'No-selection All action retains strong confirmation');
verifyAccess(!bulkHttp('process',['token'=>$token])['success'],'Strong confirmation requires acknowledgment and typed phrase');
verifyAccess(!bulkHttp('process',['token'=>$token,'acknowledge'=>true,'phrase'=>'WRONG'])['success'],'Typed phrase is validated server-side');
addBulkSlip(209,'After snapshot');
verifyAccess(!bulkHttp('validate',['token'=>$token])['valid'],'New eligibility invalidates the open confirmation');
verifyAccess(!bulkHttp('process',['token'=>$token,'acknowledge'=>true,'phrase'=>'RECOMMEND ALL'])['success'],'Changed eligible count stops the whole batch before any transition');
$conn->query('DELETE FROM gas_slips WHERE id=209');
$done=bulkHttp('process',['token'=>$token,'acknowledge'=>true,'phrase'=>'RECOMMEND ALL']);
verifyAccess(in_array(201,bulkIds($done['summary']['successful']),true),'Previously excluded record is processed normally');
verifyAccess(!in_array(209,bulkIds($done['summary']['successful']),true),'New records never enter an existing snapshot');
verifyAccess(bulkHttp('state')['counts'][$group]===0,'Eligible tab counts refresh after bulk status transitions');
verifyAccess(!bulkHttp('process',['token'=>$token,'acknowledge'=>true,'phrase'=>'RECOMMEND ALL'])['success'],'Snapshot replay rejected');
verifyAccess((int)$conn->query("SELECT COUNT(*) FROM notifications WHERE gas_slip_id=201 AND type='recommended'")->fetch_row()[0]>0,'Existing recommendation notifications preserved');
// Real historical list controllers keep their original visibility and statuses while filtering.
function historyRows($file,$group,$user=10) {
    global $address;
    $context=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>5,'header'=>'Cookie: PHPSESSID=bulktest'.$user]]);
    $body=file_get_contents('http://'.$address.'/'.$file.'?fixture_user='.$user.'&bulk_group='.$group,false,$context);
    $result=json_decode($body,true);if(!is_array($result))throw new RuntimeException($body);return $result;
}
$history=historyRows('recommended_gas_slips.php',$group);
verifyAccess(bulkIds(array_map(fn($r)=>['id'=>$r['gs_id']],$history['rows']))===[201,202,205],'Recommended group filters original historical table rather than pending queue');
$historyState=bulkHttp('state',['page'=>'recommended','csrf'=>'history-rec','group'=>$group]);
verifyAccess($historyState['counts'][$group]===3 && $historyState['counts'][0]===(int)historyRows('recommended_gas_slips.php',0)['total'],'Recommended tab counts match historical visibility across pagination, not pending eligibility');
verifyAccess((int)historyRows('recommended_gas_slips.php',$other)['total']===0,'Foreign history group fails closed');
$snap=snapshotBulk('approve',0,[],30);
verifyAccess($snap['snapshot']['strong'],'Approve All still requires strong confirmation');
$done=bulkHttp('process',['token'=>$snap['token'],'acknowledge'=>true,'phrase'=>'APPROVE ALL'],30);
verifyAccess(in_array(201,bulkIds($done['summary']['successful']),true),'Authorized automatic approval invokes original workflow');
verifyAccess((int)historyRows('recommended_gas_slips.php',$group)['total']===0,'Recommended group remains restricted to recommended status');
$history=historyRows('approved_slips.php',$group);
verifyAccess(bulkIds(array_map(fn($r)=>['id'=>$r['gs_id']],$history['rows']))===[201,202,205],'Approved groups retain approved historical visibility');
$historyState=bulkHttp('state',['page'=>'approved','csrf'=>'history-app','group'=>$group]);
verifyAccess($historyState['counts'][$group]===3 && $historyState['counts'][0]===(int)historyRows('approved_slips.php',0)['total'],'Approved tab counts match the original approved record scope');
$conn->query("UPDATE gas_slips SET printed_at=NOW(),approved_at='2026-01-02' WHERE id=201");
$filtered=bulkHistoryCountRecords($conn,10,'approved',['is_recommender'=>true,'show_printed'=>0]);
verifyAccess(!in_array(201,bulkIds($filtered),true),'Approved counts respect the existing printed-record filter');
$filtered=bulkHistoryCountRecords($conn,10,'approved',['is_recommender'=>true,'approved_slips_search'=>'BULK201','date_from'=>'2026-01-01','date_to'=>'2026-01-03']);
verifyAccess(bulkIds($filtered)===[201],'Approved counts respect existing search and date filters');
$conn->query('UPDATE gas_slips SET printed_at=NULL,approved_at=NOW() WHERE id=201');
verifyAccess((int)historyRows('approved_slips.php',$other)['total']===0,'Approved groups enforce private ownership');
// Automatic mixed workflows preserve private recommendation and final private approval.
addBulkSlip(212,'Private Driver',20,'pending',2);addBulkSlip(213,'Private Driver Two',20,'pending',2);
$snap=snapshotBulk('approve',0,[212,213],30);
verifyAccess($snap['snapshot']['records'][0]['workflow']==='recommend','Private pending record retains its database-authorized recommendation step');
$done=bulkHttp('process',['token'=>$snap['token'],'acknowledge'=>true,'phrase'=>'APPROVE ALL'],30);
verifyAccess(in_array(212,bulkIds($done['summary']['successful']),true) && $conn->query('SELECT status FROM gas_slips WHERE id=212')->fetch_row()[0]==='recommended','Automatic private handling never bypasses the recommendation step');
$snap=snapshotBulk('approve',0,[212,213],91);
$done=bulkHttp('process',['token'=>$snap['token'],'acknowledge'=>true,'phrase'=>'APPROVE ALL'],91);
verifyAccess(in_array(212,bulkIds($done['summary']['successful']),true),'Configured private final approver remains authoritative');
addBulkSlip(210);addBulkSlip(211);addBulkSlip(214);addBulkSlip(215);
verifyAccess(!snapshotBulk('reject',$group,[210])['success'],'A one-record selected batch is rejected even in a larger eligible group');
$snap=snapshotBulk('reject',$group,[210,211]);
verifyAccess(!$snap['snapshot']['strong'],'Partial multi-record rejection retains standard confirmation');
verifyAccess(!bulkHttp('process',['token'=>$snap['token'],'acknowledge'=>true,'reason'=>' '])['success'],'Rejection still requires a reason');
$done=bulkHttp('process',['token'=>$snap['token'],'reason'=>'Review','acknowledge'=>true]);
verifyAccess(count($done['summary']['successful'])===2,'Selected multi-record rejection remains functional');
$snap=snapshotBulk('reject',$group,[214,215]);
verifyAccess($snap['snapshot']['strong'],'Reject All retains strong confirmation');
verifyAccess(!bulkHttp('process',['token'=>$snap['token'],'reason'=>'Review'])['success'],'Reject All still requires acknowledgment');
verifyAccess(count(bulkHttp('process',['token'=>$snap['token'],'reason'=>'Review','acknowledge'=>true])['summary']['successful'])===2,'Reject All preserves shared reason and transitions');
addBulkSlip(220,'Failure case');addBulkSlip(221,'Success case');
$conn->query("CREATE TRIGGER fail_bulk_notification BEFORE INSERT ON notifications FOR EACH ROW BEGIN IF NEW.gas_slip_id=220 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture failure'; END IF; END");
$snap=snapshotBulk('recommend',0,[220,221]);
verifyAccess(!bulkHttp('process',['token'=>$snap['token'],'count'=>1])['success'],'Multiple Selected recommendation requires acknowledgment regardless of forged client count');
$done=bulkHttp('process',['token'=>$snap['token'],'acknowledge'=>true,'phrase'=>'RECOMMEND ALL']);
verifyAccess(count($done['summary']['failed'])===1 && count($done['summary']['successful'])===1,'Per-record rollback and partial result reporting preserved');
$conn->query('DROP TRIGGER fail_bulk_notification');
addBulkSlip(222,'Competing');addBulkSlip(226,'Competing two');$a=snapshotBulk('recommend',0,[222,226]);$b=snapshotBulk('recommend',0,[222,226]);
bulkHttp('process',['token'=>$a['token'],'acknowledge'=>true]);$done=bulkHttp('process',['token'=>$b['token'],'acknowledge'=>true]);
verifyAccess(!$done['success'],'Changed eligibility prevents a competing snapshot from processing again');
addBulkSlip(227,'Permission change');
$snap=snapshotBulk('recommend',0,[220,227]);$conn->query('UPDATE users SET assigned_recommender_id=11 WHERE id=20');
verifyAccess(!bulkHttp('process',['token'=>$snap['token'],'acknowledge'=>true])['success'],'Changed authorization invalidates the confirmed eligible set');
$conn->query('UPDATE users SET assigned_recommender_id=10 WHERE id=20');
addBulkSlip(223,'Individual rejection');
$individualPost=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'timeout'=>5,'header'=>'Content-Type: application/x-www-form-urlencoded','content'=>'gas_slip_id=223&rejection_reason=Original+individual+review']]);
file_get_contents('http://'.$address.'/reject_gas_slip.php?fixture_user=10',false,$individualPost);
verifyAccess($conn->query('SELECT status FROM gas_slips WHERE id=223')->fetch_row()[0]==='rejected','Original individual Reject endpoint remains functional');
// Multi-role accounts retain both authorized record workflows without a manual selector.
$conn->query('INSERT INTO department_approvers VALUES (8,1,10,1)');
addBulkSlip(224,'Dual role approval',20,'recommended');addBulkSlip(225,'Dual role approval two',20,'recommended');
$conn->query('UPDATE gas_slips SET recommended_by=11,recommended_at=NOW() WHERE id IN (224,225)');
$mixed=bulkHttp('state');
$mixedRow=array_values(array_filter($mixed['records'],fn($r)=>(int)$r['id']===224));
verifyAccess($mixed['action']==='recommend' && $mixedRow[0]['workflow']==='approve','Multi-role account resolves primary action and individual workflow from database permissions');
$mixedSnap=snapshotBulk('recommend',0,[224,225]);
verifyAccess(count(bulkHttp('process',['token'=>$mixedSnap['token'],'acknowledge'=>true])['summary']['successful'])===2,'Multi-role processing invokes only the authorized per-record workflow');
$conn->query('DELETE FROM department_approvers WHERE id=8');
verifyAccess(count(bulkHttp('state',[],11)['records'])===1,'Separate recommender fixture has one eligible record across its entire queue');
verifyAccess(!snapshotBulk('recommend',0,[],11)['success'],'All Gas Slips cannot create a one-record bulk snapshot');
addBulkSlip(232,'Second eligible request',24);$conn->query('UPDATE gas_slips SET area_id=2 WHERE id=232');
verifyAccess(snapshotBulk('recommend',0,[],11)['success'],'All Gas Slips bulk processing is available once two records are eligible');
bulkHttp('add_name',['group'=>$g2,'name'=>'Minimum pair']);
verifyAccess(!snapshotBulk('recommend',$g2,[])['success'],'Zero-record group cannot create a bulk snapshot');
addBulkSlip(230,'Minimum pair');
verifyAccess(!snapshotBulk('recommend',$g2,[])['success'],'One-record group cannot create a bulk snapshot');
addBulkSlip(231,'Minimum pair');
$minimum=snapshotBulk('recommend',$g2,[]);
verifyAccess($minimum['success'] && count($minimum['snapshot']['ids'])===2,'Two-record group can create a bulk snapshot');
verifyAccess(!snapshotBulk('recommend',$g2,[230])['success'],'Selecting one record cannot bypass the minimum batch size');
$singlePost=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'timeout'=>5,'header'=>'Content-Type: application/x-www-form-urlencoded','content'=>'id=231']]);
$single=json_decode(file_get_contents('http://'.$address.'/recommend_gas_slip.php?fixture_user=10',false,$singlePost),true);
verifyAccess($single['success'],'Original individual Recommend still processes a single eligible slip');
verifyAccess(!bulkHttp('validate',['token'=>$minimum['token']])['valid'],'A drop from two eligible records to one invalidates confirmation');
verifyAccess(!bulkHttp('process',['token'=>$minimum['token'],'acknowledge'=>true])['success'],'Final submission rejects changed eligibility without processing the remaining single record');
verifyAccess($conn->query('SELECT status FROM gas_slips WHERE id=230')->fetch_row()[0]==='pending','Rejected bulk submission leaves the single remaining request unchanged');
include __DIR__.'/approval_separation_cases.php';
$before=(int)$conn->query('SELECT COUNT(*) FROM gas_slips')->fetch_row()[0];
verifyAccess(bulkHttp('delete',['group'=>$group])['success'],'Group deletion preserved');
verifyAccess((int)$conn->query('SELECT COUNT(*) FROM gas_slips')->fetch_row()[0]===$before,'Group deletion never alters slips');
