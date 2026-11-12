<?php
// Included only by the existing disposable MySQL/HTTP access harness.
function approvedVisibilityHttp($endpoint, $actor=10, $query=[], $payload=null, $json=false) {
    global $address;
    $query += ['fixture_profile'=>'actual','fixture_user'=>$actor];
    $options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>5];
    if($payload!==null) $options += ['method'=>'POST','header'=>$json?'Content-Type: application/json':'Content-Type: application/x-www-form-urlencoded','content'=>$json?json_encode($payload):http_build_query($payload)];
    $body=file_get_contents('http://'.$address.'/'.$endpoint.'?'.http_build_query($query),false,stream_context_create(['http'=>$options]));
    return ['body'=>$body,'data'=>json_decode($body,true),'status'=>$http_response_header[0]??''];
}
function approvedVisibilityPage($actor=10,$filters=[]) {
    $response=approvedVisibilityHttp('approved_slips.php',$actor,$filters);
    if(!is_array($response['data']) || !str_contains($response['status'],'200')) throw new RuntimeException('Approved controller response: '.$response['body']);
    return $response['data'];
}
function approvedVisibilityIds($data) { $ids=array_map('intval',array_column($data['rows'],'gs_id'));sort($ids);return $ids; }

$pendingBefore=creatorPendingIds($conn,10);
$originalAssignment=(int)$conn->query('SELECT assigned_recommender_id FROM users WHERE id=10')->fetch_row()[0];
try {
    $conn->query('UPDATE users SET assigned_recommender_id=11 WHERE id=10');
    $conn->query('INSERT INTO recommender_area_assignments VALUES (1,11,1)');
    $conn->query("INSERT INTO users(id,department_id,area_id,status) VALUES (99,1,1,'inactive')");
    $fixtures=[[501,10,2,'approved',30,91],[502,10,1,'approved',11,30],
        [503,10,1,'approved',10,30],[504,10,1,'approved',10,10],
        [505,10,2,'printed',30,91],[506,10,1,'printed',11,30],
        [507,23,2,'approved',31,91],[508,21,1,'approved',10,30],
        [509,10,1,'draft',null,null],[510,10,2,'pending',null,null],
        [511,10,2,'recommended',30,null],[512,10,1,'rejected',null,null],
        [513,99,2,'approved',30,91],[514,999,2,'approved',30,91],
        [515,10,1,'approved',11,30],[516,10,1,'printed',11,30]];
    for($id=517;$id<=536;$id++)$fixtures[]=[$id,10,1,'approved',11,30];
    foreach($fixtures as [$id,$owner,$vehicle,$status,$rec,$app]) {
        $printed=in_array($id,[505,506,515],true)?"'2026-10-12 12:00:00'":'NULL';
        $conn->query("INSERT INTO gas_slips(id,gas_slip_id,user_id,area_id,vehicle_id,status,recommended_by,approved_by,approver_type,printed_at,date_issued,approved_at,requested_by,purpose)
            VALUES($id,'APPROVED$id',$owner,1,$vehicle,'$status',".($rec??'NULL').','.($app??'NULL').",'".($id===504?'recommender':'primary')."',$printed,DATE_ADD('2090-01-01',INTERVAL $id SECOND),'2026-10-12 15:00:00','Approved visibility','Creator inspection')");
    }
    $conn->query("INSERT INTO routes VALUES(501,'Search origin','Unique approved destination')");
    $conn->query('INSERT INTO gas_slip_routes VALUES(501,502,501),(502,502,501)');
    $conn->query('CREATE TABLE bulk_processing_groups(id INT PRIMARY KEY,owner_user_id INT,is_active TINYINT)');
    $conn->query('CREATE TABLE bulk_processing_group_names(group_id INT,owner_user_id INT,normalized_name VARCHAR(100))');
    $conn->query('INSERT INTO bulk_processing_groups VALUES(1,10,1),(2,11,1)');
    $conn->query("INSERT INTO bulk_processing_group_names VALUES(1,10,'approved visibility'),(2,11,'approved visibility')");

    // Execute the actual page controller/query prefix. Fixture query parameters
    // populate its existing session filters; production filter handling is unchanged.
    $pageSource=explode('?>',file_get_contents(__DIR__.'/../approved_slips.php'),2)[0];
    $filterSetup= <<<'PHP'
$conn = getDBConnection();
$_SESSION['show_printed']=(int)($_GET['fixture_printed']??1);
$_SESSION['approved_slips_search']=$_GET['fixture_search']??'';
$_SESSION['date_from']=$_GET['fixture_from']??null;
$_SESSION['date_to']=$_GET['fixture_to']??null;
PHP;
    $pageSource=str_replace('$conn = getDBConnection();',$filterSetup,$pageSource);
    file_put_contents($sandbox.'/approved_slips.php',$pageSource.' header("Content-Type: application/json"); echo json_encode(["total"=>(int)$totalRecords,"rows"=>$result->fetch_all(MYSQLI_ASSOC)]);');
    $scope=recommenderApprovedSlipSql(10);
    $expected=array_map('intval',array_column($conn->query("SELECT gs.id FROM gas_slips gs WHERE $scope ORDER BY gs.id")->fetch_all(MYSQLI_ASSOC),'id'));
    $all=approvedVisibilityPage();$pages=[];
    for($p=1;$p<=ceil($all['total']/15);$p++) {
        $data=approvedVisibilityPage(10,['page'=>$p]);
        verifyAccess($data['total']===count($expected),'Approved page count matches shared display scope on page '.$p);
        $pages=array_merge($pages,approvedVisibilityIds($data));
    }
    sort($pages);
    verifyAccess($pages===$expected && count(array_unique($pages))===count($pages),'Actual Approved pagination includes creator/author overlaps once, even with multiple routes');
    foreach([501,502,503,504,505,506,508] as $id)verifyAccess(in_array($id,$pages,true),'Approved list retains private/coop creator or recommendation author: '.$id);
    foreach([507,509,510,511,512] as $id)verifyAccess(!in_array($id,$pages,true),'Approved scope excludes unrelated or non-completed record: '.$id);
    foreach([99,999] as $actor) {
        $s=recommenderApprovedSlipSql($actor);
        verifyAccess($conn->query("SELECT 1 FROM gas_slips gs WHERE $s")->num_rows===0,'Approved ownership requires existing active actor: '.$actor);
    }
    verifyAccess(in_array(502,approvedVisibilityIds(approvedVisibilityPage(11,['fixture_search'=>'APPROVED502'])),true),'B retains visibility to A coop slip B recommended');
    verifyAccess(in_array(502,approvedVisibilityIds(approvedVisibilityPage(30,['fixture_search'=>'APPROVED502'])),true),'Existing department approver Approved branch unchanged');
    verifyAccess(in_array(501,approvedVisibilityIds(approvedVisibilityPage(91,['fixture_search'=>'APPROVED501'])),true),'Existing global private approver Approved branch unchanged');
    verifyAccess(approvedVisibilityPage(23,['fixture_search'=>'APPROVED507'])['total']===1,'Normal preparer keeps own Approved record behavior');

    foreach([['fixture_search'=>'APPROVED501'],['fixture_search'=>'Unique approved destination'],['fixture_from'=>'2026-10-12','fixture_to'=>'2026-10-12'],['fixture_from'=>'2026-10-13'],['fixture_to'=>'2026-10-11'],['fixture_printed'=>0],['bulk_group'=>1],['bulk_group'=>2]] as $filter) {
        $first=approvedVisibilityPage(10,$filter);$filtered=[];
        for($p=1;$p<=max(1,ceil($first['total']/15));$p++)$filtered=array_merge($filtered,approvedVisibilityIds(approvedVisibilityPage(10,$filter+['page'=>$p])));
        sort($filtered);
        $session=['is_recommender'=>true,'show_printed'=>$filter['fixture_printed']??1,'approved_slips_search'=>$filter['fixture_search']??'','date_from'=>$filter['fixture_from']??null,'date_to'=>$filter['fixture_to']??null];
        $history=bulkHistoryCountRecords($conn,10,'approved',$session);
        $group=(int)($filter['bulk_group']??0);
        $counts=bulkTabCounts($history,[['id'=>1,'names'=>[['normalized_name'=>'approved visibility']]]]);
        if($group===2)$wanted=[];
        elseif($group===1)$wanted=array_map('intval',array_column(array_filter($history,fn($r)=>bulkNormalize($r['requested_by'])==='approved visibility'),'id'));
        else $wanted=array_map('intval',array_column($history,'id'));
        sort($wanted);
        verifyAccess($filtered===$wanted && $first['total']===count($wanted),'Actual Approved filter/list/history count parity: '.json_encode($filter));
        if($group===1)verifyAccess($counts[1]===$first['total'],'Custom-group tab count matches creator-inclusive list');
        if(isset($filter['fixture_search']))verifyAccess($filtered===[$filter['fixture_search']==='APPROVED501'?501:502],'Creator search matches slip code or related route');
        if(isset($filter['fixture_from']) && $filter['fixture_from']==='2026-10-12')verifyAccess(in_array(501,$filtered,true),'Approval-date boundaries include same-day timestamp');
        if(($filter['fixture_printed']??1)===0)verifyAccess(!array_intersect([505,506,515],$filtered) && in_array(516,$filtered,true),'Hide Printed preserves printed_at semantics including inconsistent legacy values');
    }
    $sidebar=file_get_contents(__DIR__.'/../includes/sidebar.php');$start=strpos($sidebar,'$statuses =');$end=strpos($sidebar,'$approvalCount = 0;',$start);
    $userId=10;$isRecommender=true;$isApprover=false;eval(substr($sidebar,$start,$end-$start));
    $approvedOnly=(int)$conn->query("SELECT COUNT(*) FROM gas_slips gs WHERE $scope AND gs.status='approved'")->fetch_row()[0];
    verifyAccess((int)$counts['approved']===$approvedOnly && $approvedOnly<count($expected),'Sidebar counts creator-or-author approved only, excluding printed');
    verifyAccess(str_contains(file_get_contents(__DIR__.'/../includes/dashboard/cards.php'),"\$counts['approved']"),'Dashboard Approved card still consumes sidebar count');

    foreach([501,502,505,506] as $id) {
        $r=approvedVisibilityHttp('view_gas_slip_modal.php',10,['id'=>$id,'mode'=>'body']);
        verifyAccess(str_contains($r['status'],'200') && !str_contains($r['body'],'id="recommendBtn"') && !str_contains($r['body'],'id="approveBtn"') && !str_contains($r['body'],'rejectBtn'),'Real creator approved/printed modal opens without workflow actions: '.$id);
        foreach(['recommend_gas_slip.php','approve_gas_slip.php'] as $endpoint)verifyAccess((approvedVisibilityHttp($endpoint,10,[],['id'=>$id])['data']['success']??null)===false,'Completed creator record denied by direct '.$endpoint.': '.$id);
        approvedVisibilityHttp('reject_gas_slip.php',10,[],['gas_slip_id'=>$id,'rejection_reason'=>'Unauthorized']);
        verifyAccess(in_array($conn->query('SELECT status FROM gas_slips WHERE id='.$id)->fetch_row()[0],['approved','printed'],true),'Direct rejection cannot mutate creator completed record: '.$id);
    }
    verifyAccess(str_contains(approvedVisibilityHttp('view_gas_slip_modal.php',10,['id'=>507,'mode'=>'json'])['status'],'403'),'Foreign private completed record remains forbidden');
    verifyAccess(!array_intersect([501,502,505,506],bulkIds(bulkRecords($conn,10,'auto'))),'Completed display rows acquire no bulk action eligibility');

    // Exercise unchanged print status endpoint with the disposable schema.
    foreach(['includes/print_access.php','update_printed_at.php'] as $file)copy(__DIR__.'/../'.$file,$sandbox.'/'.$file);
    $conn->query("INSERT INTO system_settings VALUES('print_once','1')");
    verifyAccess((approvedVisibilityHttp('update_printed_at.php',10,[],['ids'=>[502]],true)['data']['success']??false),'Existing creator print permission allows first print through real status endpoint');
    verifyAccess((approvedVisibilityHttp('update_printed_at.php',10,[],['ids'=>[502]],true)['data']['success']??true)===false,'Print Once denies creator reprint through real status endpoint');
    verifyAccess((approvedVisibilityHttp('update_printed_at.php',10,[],['ids'=>[507]],true)['data']['success']??true)===false,'Print endpoint still denies unrelated creator relationship');
    $conn->query("UPDATE system_settings SET setting_value='0' WHERE setting_key='print_once'");
    verifyAccess((approvedVisibilityHttp('update_printed_at.php',10,[],['ids'=>[502]],true)['data']['success']??false),'Existing creator reprint allowed when Print Once disabled');

    verifyAccess((approvedVisibilityHttp('recommend_gas_slip.php',30,[],['id'=>510])['data']['success']??false) && (approvedVisibilityHttp('approve_gas_slip.php',91,[],['id'=>510])['data']['success']??false),'Unchanged private department recommendation and global approval HTTP flow');
    verifyAccess(in_array(510,approvedVisibilityIds(approvedVisibilityPage(10,['fixture_search'=>'APPROVED510'])),true),'Creator sees private slip after actual workflow completion');
    $conn->query("INSERT INTO gas_slips(id,gas_slip_id,user_id,area_id,vehicle_id,status) VALUES(537,'APPROVED537',10,1,1,'pending')");
    verifyAccess((approvedVisibilityHttp('recommend_gas_slip.php',11,[],['id'=>537])['data']['success']??false) && (approvedVisibilityHttp('approve_gas_slip.php',30,[],['id'=>537])['data']['success']??false),'Unchanged assigned B coop recommendation and department approval HTTP flow');
    verifyAccess(approvedVisibilityPage(10,['fixture_search'=>'APPROVED537'])['total']===1,'Creator sees B-recommended coop after actual workflow completion');
    $conn->query('UPDATE users SET assigned_recommender_id=10 WHERE id=10');
    $conn->query('INSERT INTO department_approval_settings VALUES(1,1,1)');
    $conn->query("INSERT INTO gas_slips(id,gas_slip_id,user_id,area_id,vehicle_id,status) VALUES(538,'APPROVED538',10,1,1,'pending')");
    verifyAccess((approvedVisibilityHttp('recommend_gas_slip.php',10,[],['id'=>538])['data']['recommender_only']??false) && approvedVisibilityPage(10,['fixture_search'=>'APPROVED538'])['total']===1,'Actual self-assigned recommender-only approval remains visible once');
} finally {
    $conn->rollback();
    $conn->query('DROP TABLE IF EXISTS bulk_processing_group_names,bulk_processing_groups');
    $conn->query('DELETE FROM gas_slip_routes WHERE id IN (501,502)');
    $conn->query('DELETE FROM routes WHERE id=501');
    $conn->query('DELETE FROM notifications WHERE gas_slip_id BETWEEN 501 AND 538');
    $conn->query('DELETE FROM gas_slips WHERE id BETWEEN 501 AND 538');
    $conn->query('DELETE FROM users WHERE id=99');
    $conn->query("DELETE FROM system_settings WHERE setting_key='print_once'");
    $conn->query('DELETE FROM department_approval_settings WHERE department_id=1 AND area_id=1');
    $conn->query('DELETE FROM recommender_area_assignments WHERE recommender_id=11 AND area_id=1');
    $conn->query('UPDATE users SET assigned_recommender_id='.$originalAssignment.' WHERE id=10');
    // Leave the shared sandbox controller uninstrumented for subsequent suites.
    $originalPage=explode('?>',file_get_contents(__DIR__.'/../approved_slips.php'),2)[0];
    file_put_contents($sandbox.'/approved_slips.php',$originalPage.' header("Content-Type: application/json"); echo json_encode(["total"=>$totalRecords,"rows"=>$result->fetch_all(MYSQLI_ASSOC)]);');
}
verifyAccess(creatorPendingIds($conn,10)===$pendingBefore,'Previous Pending visibility and count scope unchanged after Approved cases');
