<?php
// Read-only CLI oversight for persisted recommendations. Never changes statuses or identities.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/gas_slip_access.php';
$conn=getDBConnection();
$actors=$conn->query("SELECT id FROM users WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
$report=[];
foreach($conn->query("SELECT gs.id,gs.gas_slip_id,gs.recommended_by,gs.recommended_at,gs.area_id,u.department_id,
    COALESCE(s.recommender_only,0) current_recommender_only
    FROM gas_slips gs LEFT JOIN users u ON u.id=gs.user_id
    LEFT JOIN department_approval_settings s ON s.department_id=u.department_id AND s.area_id=gs.area_id
    WHERE gs.status='recommended' ORDER BY gs.id") as $slip) {
    $slip['authorized_approvers']=[];
    foreach($actors as $actor)if(canUserAccessGasSlip($conn,(int)$actor['id'],(int)$slip['id'],'approve'))$slip['authorized_approvers'][]=(int)$actor['id'];
    $slip['review_required']=empty($slip['recommended_by'])?'Missing original recommender identity':(!$slip['authorized_approvers']?'No independent authorized approver':null);
    $report[]=$slip;
}
$output=['database_status'=>'read-only','recommended_slips'=>$report];
foreach($argv as $argument) {
    if(!preg_match('/^--pending-user=([1-9][0-9]*)$/',$argument,$match))continue;
    $pendingUser=(int)$match[1];
    $actor=$conn->query("SELECT id,department_id,area_id,role FROM users WHERE id=$pendingUser AND status='active'")->fetch_assoc();
    if(!$actor)throw new RuntimeException('Active pending-queue account not found.');
    $_SESSION=['user_id'=>$pendingUser,'session_token'=>'cli-read-only-audit','role'=>$actor['role']??'user','department_id'=>(int)$actor['department_id'],'area'=>(int)$actor['area_id']];
    $_SESSION['is_recommender']=$conn->query("SELECT 1 FROM department_recommenders WHERE user_id=$pendingUser AND department_id=".(int)$actor['department_id'])->num_rows>0;
    $_SESSION['is_approver']=$conn->query("SELECT 1 FROM department_approvers WHERE user_id=$pendingUser AND department_id=".(int)$actor['department_id'])->num_rows>0;
    $_SESSION['is_private_approver']=$conn->query("SELECT 1 FROM approval_global_settings WHERE private_vehicle_approver_user_id=$pendingUser")->num_rows>0;
    $offset=0;$recordsPerPage=100000;
    include __DIR__.'/../includes/pending/query.php';
    $output['pending_queue']=['user_id'=>$pendingUser,'total'=>(int)$totalRecords,'ids'=>array_map('intval',array_column($result->fetch_all(MYSQLI_ASSOC),'gs_id'))];
    $conn->rollback();
}
echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
