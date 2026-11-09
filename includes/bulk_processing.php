<?php
require_once __DIR__ . '/gas_slip_access.php';

function bulkDisplayName($value) {
    if (!is_string($value)) { throw new InvalidArgumentException('A name is required.'); }
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (!$value || mb_strlen($value, 'UTF-8') > 100) { throw new InvalidArgumentException('Names must contain 1–100 characters.'); }
    return $value;
}
function bulkNormalize($value) { return mb_strtolower(bulkDisplayName($value), 'UTF-8'); }
function bulkQuery($conn, $sql, $types = '', $values = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$values); }
    $stmt->execute();
    return $stmt;
}
function bulkOwnerGroup($conn, $user, $group, $lock = false) {
    $row = bulkQuery($conn, 'SELECT * FROM bulk_processing_groups WHERE id=? AND owner_user_id=? AND is_active=1' . ($lock ? ' FOR UPDATE' : ''), 'ii', [$group,$user])->get_result()->fetch_assoc();
    if (!$row) { throw new InvalidArgumentException('Group not found.'); }
    return $row;
}
function bulkGroups($conn, $user) {
    $groups = bulkQuery($conn, 'SELECT id,group_name FROM bulk_processing_groups WHERE owner_user_id=? AND is_active=1 ORDER BY group_name', 'i', [$user])->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($groups as &$group) {
        $group['names'] = bulkQuery($conn, 'SELECT id,requested_by_name,normalized_name FROM bulk_processing_group_names WHERE group_id=? AND owner_user_id=? ORDER BY requested_by_name', 'ii', [(int)$group['id'],$user])->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    return $groups;
}
// PHP normalization is shared by membership, autocomplete and management; no SQL collation drift.
function bulkDefaultAction($conn, $user) {
    $recommender = bulkQuery($conn, "SELECT 1 FROM users u WHERE u.id=? AND u.status='active' AND (
        EXISTS(SELECT 1 FROM department_recommenders dr WHERE dr.user_id=u.id AND dr.department_id=u.department_id)
        OR EXISTS(SELECT 1 FROM recommender_delegations rd JOIN users p ON p.id=rd.primary_recommender_id
          JOIN department_recommenders dr ON dr.user_id=p.id AND dr.department_id=rd.department_id
          WHERE rd.secondary_recommender_id=u.id AND rd.department_id=u.department_id AND p.status='active'
          AND rd.status='active' AND CURDATE() BETWEEN rd.start_date AND rd.end_date))", 'i', [$user])->get_result()->num_rows;
    return $recommender ? 'recommend' : 'approve';
}
function bulkGroupFilterSql($conn, $user, $group) {
    if (!$group) { return '1=1'; }
    bulkOwnerGroup($conn,$user,$group);
    $names = array_column(bulkQuery($conn,'SELECT normalized_name FROM bulk_processing_group_names WHERE group_id=? AND owner_user_id=?','ii',[$group,$user])->get_result()->fetch_all(MYSQLI_ASSOC),'normalized_name');
    $scope = gasSlipPermissionSql('view',$user);
    $ids = [];
    foreach ($conn->query("SELECT gs.id,gs.requested_by FROM gas_slips gs WHERE $scope") as $row) {
        if (trim($row['requested_by']) !== '' && in_array(bulkNormalize($row['requested_by']),$names,true)) { $ids[]=(int)$row['id']; }
    }
    return 'gs.id IN ('.($ids?implode(',',$ids):'0').')';
}
function bulkHistoryGroupSql($conn, $user) {
    try { return bulkGroupFilterSql($conn,$user,(int)($_GET['bulk_group']??0)); }
    catch (Throwable $e) { return '1=0'; }
}
function bulkRecords($conn, $user, $action, $group = 0, $onlyId = 0) {
    if (!in_array($action, ['recommend','approve','auto'], true)) { throw new InvalidArgumentException('Invalid workflow.'); }
    $names = null;
    if ($group) {
        bulkOwnerGroup($conn, $user, $group);
        $names = array_column(bulkQuery($conn, 'SELECT normalized_name FROM bulk_processing_group_names WHERE group_id=? AND owner_user_id=?', 'ii', [$group,$user])->get_result()->fetch_all(MYSQLI_ASSOC), 'normalized_name');
    }
    $recommend = gasSlipPermissionSql('recommend',$user);
    $approve = gasSlipPermissionSql('approve',$user);
    $scope = $action === 'auto' ? "($recommend OR $approve)" : gasSlipPermissionSql($action, $user);
    if ($onlyId) { $scope = '(' . $scope . ') AND gs.id=' . (int)$onlyId; }
    $rows = bulkQuery($conn, "SELECT gs.id, gs.gas_slip_id, gs.requested_by, gs.status,
        CASE WHEN $recommend THEN 'recommend' ELSE 'approve' END AS workflow
        FROM gas_slips gs WHERE $scope ORDER BY gs.date_issued DESC, gs.id DESC")->get_result()->fetch_all(MYSQLI_ASSOC);
    return array_values(array_filter($rows, function ($row) use ($names) {
        if ($names === null) { return true; }
        if (trim($row['requested_by']) === '') { return false; }
        return in_array(bulkNormalize($row['requested_by']), $names, true);
    }));
}
function bulkIds($rows) { $ids = array_map('intval', array_column($rows,'id')); sort($ids); return $ids; }
function bulkTabCounts($records, $groups) {
    $counts=[0=>count($records)];$membership=[];
    foreach ($groups as $group) {
        $counts[(int)$group['id']]=0;
        foreach ($group['names'] as $name) { $membership[$name['normalized_name']]=(int)$group['id']; }
    }
    foreach ($records as $record) {
        if(trim($record['requested_by'])==='')continue;
        $groupId=$membership[bulkNormalize($record['requested_by'])]??null;
        if($groupId!==null)$counts[$groupId]++;
    }
    return $counts;
}
// Mirror the historical pages' visible-record scopes, without pagination or a
// selected-group restriction. Pending eligibility is deliberately not used here.
function bulkHistoryCountRecords($conn, $user, $page, $session) {
    if(!in_array($page,['recommended','approved'],true))throw new InvalidArgumentException('Invalid history page.');
    $scope=gasSlipPermissionSql('view',$user);
    $values=[];$types='';
    if($page==='recommended') {
        $hasWorkflowRole=!empty($session['is_recommender'])||!empty($session['is_approver']);
        $where="gs.status='recommended' AND $scope AND ".($hasWorkflowRole?'gs.recommended_by=?':'(gs.user_id=? OR gs.recommended_by=?)');
        $values[]=$user;$types.='i';
        if(!$hasWorkflowRole){$values[]=$user;$types.='i';}
    } else {
        $where="gs.status IN ('approved','printed') AND $scope";
        if(!empty($session['is_recommender'])) {$where.=' AND gs.recommended_by=?';$values[]=$user;$types.='i';}
        elseif(!empty($session['is_approver'])) {
            $where.=" AND ((v.ownership='private' AND (
                EXISTS(SELECT 1 FROM approval_global_settings ags WHERE ags.private_vehicle_approver_user_id=a.id)
                OR gs.recommended_by=? OR gs.approved_by=?)) OR
                (v.ownership='coop-owned' AND EXISTS(SELECT 1 FROM department_areas da WHERE da.department_id=a.department_id AND da.area_id=u.area_id)))";
            array_push($values,$user,$user);$types.='ii';
        } else {$where.=' AND gs.user_id=?';$values[]=$user;$types.='i';}
        if(empty($session['show_printed']??1))$where.=' AND gs.printed_at IS NULL';
        foreach(['date_from'=>'>=','date_to'=>'<='] as $key=>$operator) {
            if(!empty($session[$key])){$where.=" AND DATE(gs.approved_at) $operator ?";$values[]=$session[$key];$types.='s';}
        }
        $search=trim((string)($session['approved_slips_search']??''));
        if($search!=='') {
            $where.=" AND (gs.gas_slip_id LIKE ? OR gs.requested_by LIKE ? OR gs.purpose LIKE ? OR EXISTS(
                SELECT 1 FROM gas_slip_routes gsr_search LEFT JOIN routes r_search ON r_search.id=gsr_search.route_id
                WHERE gsr_search.gas_slip_id=gs.id AND (r_search.origin LIKE ? OR r_search.destination LIKE ?)))";
            for($i=0;$i<5;$i++){$values[]='%'.$search.'%';$types.='s';}
        }
    }
    return bulkQuery($conn,"SELECT gs.id,gs.requested_by FROM gas_slips gs
        LEFT JOIN users u ON u.id=gs.user_id LEFT JOIN users a ON a.id=".(int)$user."
        LEFT JOIN vehicles v ON v.id=gs.vehicle_id WHERE $where",$types,$values)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function bulkSelection($rows, $ids) {
    $eligible = bulkIds($rows);
    $selected = array_values(array_unique(array_map('intval', $ids)));
    sort($selected);
    if (array_diff($selected, $eligible)) { throw new InvalidArgumentException('Eligibility changed. Refresh and review your selection.'); }
    return $selected;
}
function bulkConfirmationValid($snapshot, $input) {
    if ((!empty($snapshot['strong']) || count($snapshot['ids']) >= 2) && empty($input['acknowledge'])) { return false; }
    if ($snapshot['strong']) {
        if ($snapshot['action'] !== 'reject' && ($input['phrase'] ?? '') !== strtoupper($snapshot['action']) . ' ALL') { return false; }
    }
    return $snapshot['action'] !== 'reject' || trim($input['reason'] ?? '') !== '';
}
