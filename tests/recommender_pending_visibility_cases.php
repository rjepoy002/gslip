<?php
// Runs inside gas_slip_access.php's disposable DB and HTTP sandbox only.
require_once __DIR__ . '/../includes/bulk_processing.php';
function creatorPendingIds($conn, $actor) {
    $scope = recommenderPendingSlipSql($actor);
    return array_map('intval', array_column($conn->query("SELECT gs.id FROM gas_slips gs WHERE $scope ORDER BY gs.id")->fetch_all(MYSQLI_ASSOC), 'id'));
}
function creatorPendingPage($actor, $offset = 0, $limit = 100) {
    global $conn;
    $_SESSION = ['user_id'=>$actor,'role'=>'user','session_token'=>'fixture','department_id'=>1,'area'=>1,'is_recommender'=>$actor===10];
    $recordsPerPage = $limit;
    include __DIR__ . '/../includes/pending/query.php';
    $page = ['total'=>(int)$totalRecords,'ids'=>array_map('intval',array_column($result->fetch_all(MYSQLI_ASSOC),'gs_id'))];
    $conn->rollback();
    return $page;
}
function creatorVisibilityHttp($endpoint, $actor, $payload = null) {
    global $address;
    $options = ['ignore_errors'=>true,'follow_location'=>0,'timeout'=>5];
    if ($payload !== null) {
        $options += ['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>http_build_query($payload)];
    }
    $separator = str_contains($endpoint,'?') ? '&' : '?';
    $body = file_get_contents('http://'.$address.'/'.$endpoint.$separator.'fixture_profile=actual&fixture_user='.$actor,false,stream_context_create(['http'=>$options]));
    return ['body'=>$body,'data'=>json_decode($body,true),'status'=>$http_response_header[0] ?? ''];
}

$originalAssignment = (int)$conn->query('SELECT assigned_recommender_id FROM users WHERE id=10')->fetch_row()[0];
$originalB = (int)$conn->query('SELECT assigned_recommender_id FROM users WHERE id=11')->fetch_row()[0];
$before = creatorPendingIds($conn,10);
try {
    $conn->query('UPDATE users SET assigned_recommender_id=11 WHERE id IN (10,11)');
    $conn->query('INSERT INTO recommender_area_assignments VALUES (1,11,1)');
    $conn->query("INSERT INTO users(id,department_id,area_id,status) VALUES (99,1,1,'inactive')");
    foreach ([[401,10,2,'pending',null],[402,10,2,'recommended',30],
              [403,10,1,'pending',null],[404,11,1,'pending',null],
              [405,10,1,'recommended',11],[406,10,1,'rejected',null],
              [407,20,2,'pending',null],[408,24,1,'pending',null],
              [409,10,1,'draft',null],[410,10,1,'approved',11],
              [411,10,1,'printed',11],[412,99,2,'pending',null],
              [413,999,2,'pending',null],[414,10,1,'recommended',10]] as $fixture) {
        [$id,$owner,$vehicle,$status,$author] = $fixture;
        $conn->query("INSERT INTO gas_slips(id,gas_slip_id,user_id,area_id,vehicle_id,status,recommended_by,requested_by,date_issued)
            VALUES ($id,'CREATOR$id',$owner,1,$vehicle,'$status',".($author ?? 'NULL').",'Creator visibility',DATE_ADD('2090-01-01',INTERVAL $id SECOND))");
    }
    $own = [401,402,403,405,406,414];
    $expected = array_merge($before,$own); sort($expected);
    verifyAccess(creatorPendingIds($conn,10)===$expected,'Creator queue adds own private/coop pending and recommended, preserves rejected, excludes other stages and unrelated slips');
    verifyAccess(!in_array(412,creatorPendingIds($conn,99),true) && !in_array(413,creatorPendingIds($conn,999),true),'New ownership branch denies inactive and missing database actors');
    foreach ([401,402,403,405,406] as $id) {
        verifyAccess(canUserAccessGasSlip($conn,10,$id) && !canUserAccessGasSlip($conn,10,$id,'recommend') && !canUserAccessGasSlip($conn,10,$id,'approve') && !canUserAccessGasSlip($conn,10,$id,'reject'),'Creator-only visibility grants no workflow actions: '.$id);
    }
    verifyAccess(canUserAccessGasSlip($conn,11,403,'recommend') && canUserAccessGasSlip($conn,11,404,'recommend'),'Assigned B and self-assigned B retain coop recommendation authority');
    verifyAccess(count(array_keys(creatorPendingIds($conn,11),404,true))===1,'Overlapping creator/action scope includes self-assigned slip once');
    verifyAccess(!canUserAccessGasSlip($conn,11,401,'recommend') && canUserAccessGasSlip($conn,30,401,'recommend') && !canUserAccessGasSlip($conn,91,401,'recommend'),'Private recommendation remains department approver only');
    verifyAccess(canUserAccessGasSlip($conn,91,402,'approve') && !canUserAccessGasSlip($conn,30,402,'approve'),'Private final approval retains configured approver and membership rules');
    $conn->query('DELETE FROM department_approvers WHERE user_id=91');
    verifyAccess(!canUserAccessGasSlip($conn,91,402,'approve'),'Global private approver without department membership still cannot approve');
    $conn->query('INSERT INTO department_approvers VALUES (3,1,91,0)');
    $conn->query('DELETE FROM recommender_area_assignments WHERE recommender_id=11 AND area_id=1');
    verifyAccess(!canUserAccessGasSlip($conn,11,403,'recommend') && in_array(403,creatorPendingIds($conn,10),true),'Area removal revokes assignee action without hiding creator-owned slip');
    $conn->query('INSERT INTO recommender_area_assignments VALUES (1,11,1)');

    $page = creatorPendingPage(10);
    $ids = $page['ids']; sort($ids);
    verifyAccess($ids===$expected && $page['total']===count($expected),'Actual Pending list and count match creator-inclusive shared scope');
    $paginated = [];
    for ($offset=0;$offset<$page['total'];$offset+=2) {
        $part=creatorPendingPage(10,$offset,2);
        verifyAccess($part['total']===$page['total'],'Pagination retains full visible count at offset '.$offset);
        $paginated=array_merge($paginated,$part['ids']);
    }
    sort($paginated);
    verifyAccess($paginated===$expected && count(array_unique($paginated))===count($paginated),'Actual pagination has no duplicate or missing creator/action overlaps');
    // Execute the existing sidebar count block, without unrelated HTML/settings queries.
    $sidebar=file_get_contents(__DIR__.'/../includes/sidebar.php');
    $start=strpos($sidebar,'$statuses =');
    $end=strpos($sidebar,'$approvalCount = 0;',$start);
    $userId=10; $isRecommender=true; $isApprover=false;
    eval(substr($sidebar,$start,$end-$start));
    verifyAccess((int)$counts['pending']===$page['total'],'Actual sidebar count used by dashboard Pending card matches default visible list');
    $normal=creatorPendingPage(20);
    $normalExpected=array_map('intval',array_column($conn->query("SELECT id FROM gas_slips WHERE user_id=20 AND status IN ('pending','recommended','rejected')")->fetch_all(MYSQLI_ASSOC),'id'));
    sort($normalExpected); $normalIds=$normal['ids']; sort($normalIds);
    verifyAccess($normalIds===$normalExpected && $normal['total']===count($normalExpected),'Normal preparer Pending behavior remains owner/status based');

    foreach ([401,402,403,405] as $id) {
        $json=creatorVisibilityHttp('view_gas_slip_modal.php?id='.$id.'&mode=json',10);
        $html=creatorVisibilityHttp('view_gas_slip_modal.php?id='.$id.'&mode=body',10);
        $status=$conn->query('SELECT status FROM gas_slips WHERE id='.$id)->fetch_row()[0];
        verifyAccess(str_contains($json['status'],'200') && (int)($json['data']['id']??0)===$id && str_contains($html['status'],'200') && ($json['data']['statusLabel']??'')===ucfirst($status),'Actual creator JSON/body modal opens with correct status: '.$id);
        verifyAccess(!str_contains($html['body'],'id="recommendBtn"') && !str_contains($html['body'],'id="approveBtn"') && !str_contains($html['body'],'rejectBtn'),'Actual creator-only modal hides Recommend, Approve and Reject: '.$id);
    }
    verifyAccess(str_contains(creatorVisibilityHttp('view_gas_slip_modal.php?id=407&mode=json',10)['status'],'403'),'Unrelated private modal remains forbidden');
    verifyAccess(str_contains(creatorVisibilityHttp('view_gas_slip_modal.php?id=403&mode=body',11)['body'],'id="recommendBtn"'),'Assigned recommender retains actual Recommend button');
    foreach ([401,403] as $id) {
        $denied=creatorVisibilityHttp('recommend_gas_slip.php',10,['id'=>$id]);
        verifyAccess(($denied['data']['success']??null)===false && $conn->query('SELECT status FROM gas_slips WHERE id='.$id)->fetch_row()[0]==='pending','Direct recommendation cannot use creator visibility: '.$id);
    }
    foreach ([402,405] as $id) {
        $denied=creatorVisibilityHttp('approve_gas_slip.php',10,['id'=>$id]);
        verifyAccess(($denied['data']['success']??null)===false && $conn->query('SELECT status FROM gas_slips WHERE id='.$id)->fetch_row()[0]==='recommended','Direct approval cannot use creator visibility: '.$id);
    }
    $conn->query('INSERT INTO department_approvers VALUES (9,1,10,0)');
    verifyAccess(!canUserAccessGasSlip($conn,10,414,'approve') && (creatorVisibilityHttp('approve_gas_slip.php',10,['id'=>414])['data']['success']??null)===false,'Dual-capability creator cannot approve their own persisted recommendation');
    $conn->query('DELETE FROM department_approvers WHERE id=9');

    // Minimal disposable group tables support the unchanged group queries; no migration.
    $conn->query('CREATE TABLE bulk_processing_groups(id INT PRIMARY KEY,owner_user_id INT,is_active TINYINT)');
    $conn->query('CREATE TABLE bulk_processing_group_names(group_id INT,owner_user_id INT,normalized_name VARCHAR(100))');
    $conn->query('INSERT INTO bulk_processing_groups VALUES (1,10,1),(2,11,1)');
    $conn->query("INSERT INTO bulk_processing_group_names VALUES (1,10,'creator visibility'),(2,11,'creator visibility')");
    foreach (['recommend','approve','auto'] as $action) {
        verifyAccess(!array_intersect($own,bulkIds(bulkRecords($conn,10,$action))),'Creator-only slips excluded from bulk '.$action.' eligibility');
        verifyAccess(bulkRecords($conn,10,$action,1)===[],'Matching custom group does not acquire creator-only '.$action.' records');
    }
    try { bulkRecords($conn,10,'auto',2); verifyAccess(false,'Foreign group must fail'); }
    catch (InvalidArgumentException $e) { verifyAccess(true,'Foreign custom group remains owner-scoped'); }
    $active=bulkIds(bulkRecords($conn,10,'auto'));
    try { bulkSelection(bulkRecords($conn,10,'auto'),[401,403]); verifyAccess(false,'Forged selection must fail'); }
    catch (InvalidArgumentException $e) { verifyAccess(true,'Select All/Selected eligibility cannot include creator-only IDs'); }
    creatorPendingPage(10); // Initialize the actual list variables for its override.
    $recordsPerPage=100;$page=1;$offset=0;$_GET['bulk_group']=1;
    include __DIR__.'/../includes/bulk_table_query.php';
    verifyAccess((int)$totalRecords===0 && $result->num_rows===0,'Actual custom Pending table keeps action-only behavior');
    unset($_GET['bulk_group']);

    verifyAccess((creatorVisibilityHttp('recommend_gas_slip.php',30,['id'=>401])['data']['success']??false) && in_array(401,creatorPendingIds($conn,10),true),'Department approver recommends private slip and creator retains recommended visibility');
    verifyAccess((creatorVisibilityHttp('approve_gas_slip.php',91,['id'=>401])['data']['success']??false) && $conn->query('SELECT status FROM gas_slips WHERE id=401')->fetch_row()[0]==='approved','Configured private approver completes unchanged private workflow');
    $conn->query('INSERT INTO department_approval_settings VALUES (1,1,1)');
    $auto=creatorVisibilityHttp('recommend_gas_slip.php',11,['id'=>404]);
    $saved=$conn->query('SELECT status,recommended_by,approved_by,approver_type FROM gas_slips WHERE id=404')->fetch_assoc();
    verifyAccess(($auto['data']['recommender_only']??false) && $saved['status']==='approved' && (int)$saved['recommended_by']===11 && (int)$saved['approved_by']===11 && $saved['approver_type']==='recommender','Self-assignment and recommender-only automatic approval remain unchanged');
} finally {
    $conn->rollback();
    unset($_GET['bulk_group']);
    $conn->query('DROP TABLE IF EXISTS bulk_processing_group_names,bulk_processing_groups');
    $conn->query('DELETE FROM notifications WHERE gas_slip_id BETWEEN 401 AND 414');
    $conn->query('DELETE FROM gas_slips WHERE id BETWEEN 401 AND 414');
    $conn->query('DELETE FROM users WHERE id=99');
    $conn->query('DELETE FROM department_approvers WHERE id=9');
    $conn->query('DELETE FROM department_approval_settings WHERE department_id=1 AND area_id=1');
    $conn->query('DELETE FROM recommender_area_assignments WHERE recommender_id=11 AND area_id=1');
    $conn->query('UPDATE users SET assigned_recommender_id='.$originalAssignment.' WHERE id=10');
    $conn->query('UPDATE users SET assigned_recommender_id='.$originalB.' WHERE id=11');
}
