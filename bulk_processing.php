<?php
// Scope strict error handling and output buffering to this JSON endpoint only.
ini_set('display_errors','0');
ini_set('log_errors','1');
error_reporting(E_ALL);
$bulkOutputLevel = ob_get_level();
ob_start();
$bulkResponseSent = false;
function bulkJsonResponse($payload, $status = 200) {
    global $bulkOutputLevel, $bulkResponseSent;
    while (ob_get_level() > $bulkOutputLevel) { ob_end_clean(); }
    $body = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($body === false) { $status=500; $body='{"success":false,"message":"Unable to prepare the server response. Please try again."}'; }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $bulkResponseSent = true;
    echo $body;
}
set_error_handler(function ($severity,$message,$file,$line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message,0,$severity,$file,$line);
});
register_shutdown_function(function () {
    global $bulkResponseSent;
    if (!$bulkResponseSent) {
        error_log('Bulk API terminated before responding: '.json_encode(error_get_last()));
        bulkJsonResponse(['success'=>false,'message'=>'Bulk processing is temporarily unavailable. Please try again.'],500);
    }
});
try {
    session_start();
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/bulk_processing.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST'); http_response_code(405); throw new RuntimeException('This endpoint requires a POST request.');
    }
    if (empty($_SESSION['user_id']) || empty($_SESSION['session_token']) || !isset($_SESSION['role'])) {
        http_response_code(401); throw new RuntimeException('Authentication required.');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $pageContext = is_array($input) ? ($input['page'] ?? '') : '';
    if (!in_array($pageContext,['pending','recommended','approved'],true)) { throw new RuntimeException('Invalid page context.'); }
    $csrfKey = 'bulk_csrf_' . $pageContext;
    if (!is_array($input) || !hash_equals($_SESSION[$csrfKey] ?? '', (string)($input['csrf'] ?? '')) || empty($_SESSION[$csrfKey])) {
        http_response_code(403); throw new RuntimeException('Invalid security token. Refresh this page.');
    }
    $conn = getDBConnection();
    $conn->set_charset('utf8mb4');
    $user = (int)$_SESSION['user_id'];
    $actor = bulkQuery($conn, "SELECT * FROM users WHERE id=? AND status='active' AND session_token=?", 'is', [$user,$_SESSION['session_token']])->get_result()->fetch_assoc();
    if (!$actor) { http_response_code(401); throw new RuntimeException('Your session has expired.'); }
    // Database assignments, never session role flags, determine access to the feature.
    $manager = bulkQuery($conn, "SELECT 1 FROM users u WHERE u.id=? AND (
        EXISTS(SELECT 1 FROM department_recommenders dr WHERE dr.user_id=u.id AND dr.department_id=u.department_id)
        OR EXISTS(SELECT 1 FROM department_approvers da WHERE da.user_id=u.id AND da.department_id=u.department_id)
        OR EXISTS(SELECT 1 FROM recommender_delegations rd JOIN users p ON p.id=rd.primary_recommender_id
          JOIN department_recommenders dr ON dr.user_id=p.id AND dr.department_id=rd.department_id
          WHERE rd.secondary_recommender_id=u.id AND rd.department_id=u.department_id AND p.status='active'
          AND rd.status='active' AND CURDATE() BETWEEN rd.start_date AND rd.end_date))", 'i', [$user])->get_result()->num_rows;
    if (!$manager) { http_response_code(403); throw new RuntimeException('Workflow access required.'); }
    $op = $input['op'] ?? '';
    $group = (int)($input['group'] ?? 0);
    $workflow = 'auto';
    $defaultAction = bulkDefaultAction($conn,$user);
    if (isset($input['workflow']) && !in_array($input['workflow'],['auto','recommend','approve'],true)) { throw new InvalidArgumentException('Invalid workflow.'); }
    if ($pageContext !== 'pending' && in_array($op,['snapshot','validate','process','exclude'],true)) {
        http_response_code(403); throw new RuntimeException('This page supports group filtering only. Bulk processing is available on Pending Gas Slips.');
    }
    if ($op === 'exclude') { throw new InvalidArgumentException('Individual Review is no longer supported.'); }
    $result = [];
    if ($op === 'state') {
        if ($group) { bulkOwnerGroup($conn,$user,$group); }
        $ownedGroups=bulkGroups($conn,$user);
        $allEligible=$pageContext==='pending'?bulkRecords($conn,$user,$workflow):[];
        $activeRecords=$allEligible;
        if($pageContext==='pending'&&$group){
            $activeGroup=array_values(array_filter($ownedGroups,fn($g)=>(int)$g['id']===$group))[0]??null;
            if(!$activeGroup)throw new InvalidArgumentException('Group not found.');
            $names=array_column($activeGroup['names'],'normalized_name');
            $activeRecords=array_values(array_filter($allEligible,fn($r)=>trim($r['requested_by'])!==''&&in_array(bulkNormalize($r['requested_by']),$names,true)));
        }
        $result = ['groups'=>$ownedGroups, 'records'=>$activeRecords, 'action'=>$defaultAction];
        $countRecords=$pageContext==='pending'?$allEligible:bulkHistoryCountRecords($conn,$user,$pageContext,$_SESSION);
        $result['counts']=(object)bulkTabCounts($countRecords,$ownedGroups);
    } elseif ($op === 'suggest') {
        $scope = gasSlipPermissionSql('view',$user);
        $assigned = array_column(bulkQuery($conn,'SELECT normalized_name FROM bulk_processing_group_names WHERE owner_user_id=?','i',[$user])->get_result()->fetch_all(MYSQLI_ASSOC),'normalized_name');
        $query = trim((string)($input['query'] ?? ''));
        $query = $query === '' ? '' : bulkNormalize($query);
        $names = [];
        $rows = $conn->query("SELECT DISTINCT gs.requested_by FROM gas_slips gs WHERE $scope ORDER BY gs.requested_by");
        while ($row = $rows->fetch_assoc()) {
            if (trim($row['requested_by']) === '') { continue; }
            $key = bulkNormalize($row['requested_by']);
            if (!in_array($key,$assigned,true) && ($query === '' || mb_strpos($key,$query,0,'UTF-8') !== false)) { $names[$key] = bulkDisplayName($row['requested_by']); }
            if (count($names) >= 10) { break; }
        }
        $result = ['names'=>array_values($names)];
    } elseif (in_array($op,['create','rename','delete','add_name','remove_name'],true)) {
        $conn->begin_transaction();
        // Serializes concurrent changes by the same owner; DB uniqueness is the final guard.
        bulkQuery($conn,'SELECT id FROM users WHERE id=? FOR UPDATE','i',[$user]);
        if ($op !== 'create') { bulkOwnerGroup($conn,$user,$group,true); }
        if ($op === 'create' || $op === 'rename') {
            $name = bulkDisplayName($input['name'] ?? ''); $normal = bulkNormalize($name);
            if ($op === 'create') {
                bulkQuery($conn,'INSERT INTO bulk_processing_groups(owner_user_id,group_name,normalized_name) VALUES(?,?,?)','iss',[$user,$name,$normal]);
                $result['group'] = $conn->insert_id;
            } else { bulkQuery($conn,'UPDATE bulk_processing_groups SET group_name=?,normalized_name=? WHERE id=? AND owner_user_id=?','ssii',[$name,$normal,$group,$user]); }
        } elseif ($op === 'delete') {
            bulkQuery($conn,'DELETE FROM bulk_processing_groups WHERE id=? AND owner_user_id=?','ii',[$group,$user]);
        } elseif ($op === 'add_name') {
            $name = bulkDisplayName($input['name'] ?? '');
            bulkQuery($conn,'INSERT INTO bulk_processing_group_names(group_id,owner_user_id,requested_by_name,normalized_name) VALUES(?,?,?,?)','iiss',[$group,$user,$name,bulkNormalize($name)]);
        } else {
            bulkQuery($conn,'DELETE FROM bulk_processing_group_names WHERE id=? AND group_id=? AND owner_user_id=?','iii',[(int)($input['name_id'] ?? 0),$group,$user]);
        }
        $conn->commit();
        $result['groups'] = bulkGroups($conn,$user);
    } elseif ($op === 'snapshot') {
        $action = $input['action'] ?? '';
        if ($action !== $defaultAction && $action !== 'reject') { throw new InvalidArgumentException('Invalid action.'); }
        if ($action === 'reject' && !$group) { throw new InvalidArgumentException('Bulk rejection requires a custom group.'); }
        $rows = bulkRecords($conn,$user,$workflow,$group);
        if(count($rows)<2)throw new InvalidArgumentException('Bulk processing requires at least two eligible gas slips. Open the individual gas slip details instead.');
        $selected = bulkSelection($rows,$input['ids'] ?? []);
        if ($action === 'reject' && !$selected) { throw new InvalidArgumentException('Explicitly select records to reject.'); }
        $all = !$selected || $selected === bulkIds($rows);
        if (!$selected) { $selected = bulkIds($rows); }
        if(count($selected)<2)throw new InvalidArgumentException('Select at least two gas slips for bulk processing, or use the individual details.');
        // Reconcile the exact eligible universe reviewed in the UI, independent of search/page.
        if (bulkSelection($rows,$input['eligible_ids'] ?? []) !== bulkIds($rows)) { throw new InvalidArgumentException('Eligibility changed. Refresh and review the updated counts.'); }
        $records = array_values(array_filter($rows,fn($r)=>in_array((int)$r['id'],$selected,true)));
        $token = bin2hex(random_bytes(32));
        $snapshot = ['ids'=>$selected,'eligible_ids'=>bulkIds($rows),'records'=>$records,'action'=>$action,'workflow'=>$workflow,'group'=>$group,'all'=>$all,'strong'=>($action==='reject' ? $all : (!$group && $all)),'expires'=>time()+600];
        $_SESSION['bulk_snapshots'] = array_filter($_SESSION['bulk_snapshots'] ?? [],fn($s)=>$s['expires']>time());
        if (count($_SESSION['bulk_snapshots']) >= 10) { array_shift($_SESSION['bulk_snapshots']); }
        $_SESSION['bulk_snapshots'][$token] = $snapshot;
        $result = ['token'=>$token,'snapshot'=>$snapshot];
    } elseif ($op === 'validate' || $op === 'process') {
        $token = (string)($input['token'] ?? '');
        $snapshot = $_SESSION['bulk_snapshots'][$token] ?? null;
        if (!$snapshot || $snapshot['expires'] <= time()) { throw new RuntimeException('Confirmation expired. Review the records again.'); }
        $current = bulkRecords($conn,$user,$snapshot['workflow'],$snapshot['group']);
        $validIds = bulkIds($current);
        $eligibilityValid=count($validIds)>=2&&count($snapshot['ids'])>=2&&($snapshot['eligible_ids']??null)===$validIds;
        $valid = $eligibilityValid && !array_diff($snapshot['ids'],$validIds);
        // Edits to Requested By invalidate the review, even when group eligibility stays the same.
        $currentNames = array_column($current,'requested_by','id');
        $currentWorkflows = array_column($current,'workflow','id');
        $currentStatuses = array_column($current,'status','id');
        foreach ($snapshot['records'] as $record) {
            if (($currentNames[$record['id']] ?? null) !== $record['requested_by'] || ($currentWorkflows[$record['id']] ?? null) !== $record['workflow'] || ($currentStatuses[$record['id']] ?? null) !== $record['status']) { $valid=false; }
        }
        if ($op === 'validate') { $result = ['valid'=>$valid]; }
        else {
            if(!$eligibilityValid){http_response_code(409);throw new RuntimeException('Eligibility changed. Refresh the page and review the updated gas slips before confirming again.');}
            if (!bulkConfirmationValid($snapshot,$input)) { throw new InvalidArgumentException('Complete the required confirmation and rejection reason.'); }
            // Consume before execution; PHP session serialization prevents simultaneous reuse/retries.
            unset($_SESSION['bulk_snapshots'][$token]);
            require_once __DIR__ . '/includes/notifications.php';
            $conn->set_charset('utf8mb4');
            require_once __DIR__ . '/includes/gas_slip_workflow.php';
            require_once __DIR__ . '/includes/gas_slip_rejection.php';
            $summary = ['successful'=>[], 'skipped'=>[], 'failed'=>[]];
            foreach ($snapshot['records'] as $record) {
                $id = (int)$record['id'];
                try {
                    $GLOBALS['bulkNotificationPushQueue'] = [];
                    $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                    $conn->begin_transaction();
                    // Same department lock order as individual recommendations, then lock slip and owner.
                    $ctx = getGasSlipContext($conn,$id);
                    if ($record['workflow']==='recommend' && $ctx) { lockRecommenderDepartment($conn,(int)$ctx['requester_department_id']); }
                    bulkQuery($conn,'SELECT id FROM gas_slips WHERE id=? FOR UPDATE','i',[$id]);
                    bulkQuery($conn,'SELECT id FROM users WHERE id=? FOR UPDATE','i',[$user]);
                    $eligible = bulkRecords($conn,$user,$snapshot['workflow'],$snapshot['group'],$id);
                    $matched = array_values(array_filter($eligible,fn($r)=>(int)$r['id']===$id && $r['requested_by']===$record['requested_by'] && $r['workflow']===$record['workflow'] && $r['status']===$record['status']));
                    if (!$matched) {
                        $conn->rollback(); $summary['skipped'][] = $record + ['reason'=>'Status, permissions, membership or Requested By changed.']; continue;
                    }
                    if ($snapshot['action']==='reject') {
                        $outcome = rejectGasSlipWorkflow($conn,$id,$user,trim($input['reason']));
                    } elseif ($record['workflow']==='recommend') {
                        $outcome = recommendGasSlipWorkflow($conn,$id,$user,(int)$actor['department_id'],true);
                    } else { $outcome = approveGasSlipWorkflow($conn,$id,$user,(int)$actor['department_id'],true); }
                    if (empty($outcome['success'])) { $conn->rollback(); $summary['skipped'][]=$record+['reason'=>$outcome['message']??'No longer eligible.']; }
                    else {
                        $conn->commit(); $summary['successful'][]=$record;
                        foreach ($GLOBALS['bulkNotificationPushQueue'] as $push) {
                            try { sendWebPushToUser(...$push); } catch (Throwable $pushError) { error_log('Bulk web push: '.$pushError->getMessage()); }
                        }
                    }
                } catch (Throwable $e) {
                    $conn->rollback(); error_log('Bulk gas slip '.$id.': '.$e->getMessage());
                    $summary['failed'][]=$record+['reason'=>'Processing failed; this record was rolled back. Refresh before retrying.'];
                } finally {
                    unset($GLOBALS['bulkNotificationPushQueue']);
                }
            }
            $result = ['summary'=>$summary];
        }
    } else { throw new InvalidArgumentException('Unknown operation.'); }
    bulkJsonResponse(['success'=>true]+$result);
} catch (Throwable $e) {
    if (isset($conn)) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
    error_log('Bulk API: '.get_class($e).' '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
    $status = http_response_code() >= 400 ? http_response_code() : 400;
    if ($e instanceof mysqli_sql_exception) {
        $status = $e->getCode()===1062 ? 409 : 500;
        $message = $e->getCode()===1062 ? 'This name is already assigned to one of your groups.' : 'Bulk storage is temporarily unavailable. Please try again.';
    } elseif ($e instanceof InvalidArgumentException || get_class($e) === RuntimeException::class) {
        $message = $e->getMessage();
    } else { $status=500; $message='Bulk processing is temporarily unavailable. Please try again.'; }
    bulkJsonResponse(['success'=>false,'message'=>$message],$status);
}
