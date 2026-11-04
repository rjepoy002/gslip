<?php
/* Isolated integration fixture. Never uses the configured application database. */
require_once __DIR__ . '/../includes/recommender_assignment.php';
require_once __DIR__ . '/../includes/settings/assignment_scope.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '');
$db = 'gslip_area_test_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$db`");
$conn->select_db($db);
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
function ids($conn, $preparer) { return array_column(getEligibleRecommendersForPreparer($conn, $preparer), 'id'); }
function migrateFixture($db) {
    putenv('GSLIP_MIGRATION_DATABASE=' . $db);
    $process = proc_open([PHP_BINARY, __DIR__ . '/../migrations/20261103_recommender_areas.php', '--apply'], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException($output); }
}
try {
    foreach ([
        'departments (id INT PRIMARY KEY, name VARCHAR(50))',
        'areas (id INT PRIMARY KEY, area_name VARCHAR(100))',
        'users (id INT PRIMARY KEY, department_id INT, area_id INT, assigned_recommender_id INT, first_name VARCHAR(50), middle_name VARCHAR(50), last_name VARCHAR(50), designation VARCHAR(50), status VARCHAR(20))',
        'department_areas (id INT PRIMARY KEY, department_id INT, area_id INT)',
        'department_recommenders (id INT AUTO_INCREMENT PRIMARY KEY, department_id INT, user_id INT)',
        'department_approvers (id INT PRIMARY KEY, department_id INT, user_id INT, is_primary TINYINT)',
        'recommender_delegations (id INT PRIMARY KEY, department_id INT, primary_recommender_id INT, secondary_recommender_id INT, status VARCHAR(20), start_date DATE, end_date DATE)'
    ] as $table) { $conn->query('CREATE TABLE ' . $table . ' ENGINE=InnoDB'); }
    $conn->query("INSERT INTO departments VALUES (1,'ANOD'),(2,'ASOD'),(3,'ISD')");
    $conn->query("INSERT INTO areas VALUES (1,'Roxas'),(2,'Taytay'),(3,'El Nido'),(4,'Narra')");
    $conn->query('INSERT INTO department_areas VALUES (1,1,1),(2,1,2),(3,1,3),(4,2,4),(5,3,1)');
    foreach ([[10,1,1,0],[11,1,1,0],[12,2,4,0],[20,1,1,10],[21,1,2,10],[22,1,3,10],[23,2,4,12],[30,1,1,0],[31,2,4,0],[40,1,1,0],[50,3,1,0],[51,3,1,50]] as $u) {
        $conn->query("INSERT INTO users VALUES (" . implode(',', $u) . ", 'First', '', 'Last', '', 'active')");
    }
    $conn->query('INSERT INTO department_recommenders VALUES (1,1,10),(2,1,11),(3,2,12),(4,3,50)');
    $conn->query('INSERT INTO department_approvers VALUES (1,1,30,1),(2,2,31,1)');
    migrateFixture($db);
    check(in_array(10, ids($conn,20)), 'Migration preserves primary area eligibility');
    $conn->query('INSERT INTO recommender_area_assignments (department_id,recommender_id,area_id) VALUES (1,10,2),(1,10,3)');
    check(in_array(10,ids($conn,21)) && in_array(10,ids($conn,22)), 'ANOD multi-area eligibility');
    check(!in_array(10,ids($conn,23)), 'No cross-department eligibility');
    check(count(ids($conn,20)) === 2, 'Both recommenders listed once');
    check(canAccessAssignedRecommender($conn,20,10) && !canAccessAssignedRecommender($conn,20,11), 'Only selected primary has access');
    check(canManageDepartmentRecommenders($conn,30,'user',1) && !canManageDepartmentRecommenders($conn,30,'user',2), 'Approver department isolation');
    check(!canManageDepartmentRecommenders($conn,40,'admin',1), 'Admin role alone cannot manage operational department');
    check(isEligibleAssignedRecommender($conn,21,10), 'Saved assignment remains valid');
    $conn->query("INSERT INTO recommender_delegations VALUES (1,1,10,40,'active',CURDATE(),DATE_ADD(CURDATE(), INTERVAL 1 DAY))");
    check(canAccessAssignedRecommender($conn,20,40) && !canAccessAssignedRecommender($conn,21,40), 'Delegation retains original same-area restriction');
    $conn->query('DELETE FROM recommender_area_assignments WHERE recommender_id = 10 AND area_id = 2');
    check(!isEligibleAssignedRecommender($conn,21,10) && !canAccessAssignedRecommender($conn,21,10), 'Removed area revokes eligibility and pending access');
    migrateFixture($db);
    check(!isEligibleAssignedRecommender($conn,21,10), 'Migration rerun does not restore removed areas');
    check((int) $conn->query('SELECT COUNT(*) FROM recommender_area_assignments')->fetch_row()[0] === 4, 'Migration rerun creates no duplicates');
    check(in_array(50,ids($conn,51)), 'Other department retains original area behavior');
    check((int) $conn->query('SELECT assigned_recommender_id FROM users WHERE id=21')->fetch_row()[0] === 10, 'Saved assignment is never rewritten');
    replaceRecommenderAreas($conn,1,10,[1,2,2,3]);
    check((int) $conn->query('SELECT COUNT(*) FROM recommender_area_assignments WHERE recommender_id=10')->fetch_row()[0] === 3, 'Saving deduplicates submitted areas');
    try { replaceRecommenderAreas($conn,1,10,[4]); throw new LogicException('Foreign area accepted'); }
    catch (RuntimeException $e) { check($e->getMessage() === 'An area does not belong to this department.', 'Foreign department area rejected'); }
    replaceRecommenderAreas($conn,1,10,[]);
    migrateFixture($db);
    check(in_array(10,ids($conn,20)), 'Clearing all areas restores primary eligibility and survives migration rerun');
    check((int) $conn->query('SELECT area_id FROM recommender_area_assignments WHERE recommender_id=10')->fetch_row()[0] === 1, 'Fallback primary area is persisted');
    replaceRecommenderAreas($conn,1,10,[2,3]);
    check(!in_array(10,ids($conn,20)) && in_array(10,ids($conn,21)), 'Nonempty selection excludes primary area and preserves other eligibility');
    foreach ([null,4,999] as $invalidPrimary) {
        $conn->begin_transaction();
        $conn->query('UPDATE users SET area_id = ' . ($invalidPrimary ?? 'NULL') . ' WHERE id=10');
        try { replaceRecommenderAreas($conn,1,10,[]); throw new LogicException('Invalid fallback accepted'); }
        catch (RuntimeException $e) { check(str_contains($e->getMessage(),'no valid primary area'), 'Invalid fallback rejected clearly'); }
        check((int) $conn->query('SELECT COUNT(*) FROM recommender_area_assignments WHERE recommender_id=10')->fetch_row()[0] === 2, 'Rejected fallback preserves existing assignments');
        $conn->rollback();
    }
    $fallback = replaceRecommenderAreas($conn,1,10,[]);
    check($fallback['default_area_restored'] && (int) $fallback['areas'][0]['id'] === 1 && str_contains($fallback['message'],'Roxas'), 'Fallback response includes actual saved primary and success message');
    // Registration uses the same transaction/department lock as the Add endpoint.
    $beforeUsers = $conn->query('SELECT * FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $beforeRegistrations = $conn->query('SELECT * FROM department_recommenders ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $beforeAreas = $conn->query('SELECT * FROM recommender_area_assignments ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $conn->begin_transaction();
    lockRecommenderDepartment($conn,1);
    check(registerDepartmentRecommender($conn,22,1), 'New recommender registration succeeds');
    check((int) $conn->query('SELECT area_id FROM recommender_area_assignments WHERE recommender_id=22')->fetch_row()[0] === 3, 'Add automatically assigns the primary area');
    check(!registerDepartmentRecommender($conn,22,1), 'Repeated Add is a no-op');
    check((int) $conn->query('SELECT COUNT(*) FROM department_recommenders WHERE user_id=22')->fetch_row()[0] === 1, 'Repeated Add creates no duplicate registrations');
    replaceRecommenderAreas($conn,1,22,[1,2,3]);
    check(!registerDepartmentRecommender($conn,22,1) && (int) $conn->query('SELECT COUNT(*) FROM recommender_area_assignments WHERE recommender_id=22')->fetch_row()[0] === 3, 'Repeated Add preserves all edited areas');
    replaceRecommenderAreas($conn,1,22,[1,2]);
    registerDepartmentRecommender($conn,22,1);
    check((int) $conn->query('SELECT COUNT(*) FROM recommender_area_assignments WHERE recommender_id=22 AND area_id=3')->fetch_row()[0] === 0, 'Repeated Add never restores removed primary area');
    replaceRecommenderAreas($conn,1,22,[]);
    registerDepartmentRecommender($conn,22,1);
    check((int) $conn->query('SELECT COUNT(*) FROM recommender_area_assignments WHERE recommender_id=22 AND area_id=3')->fetch_row()[0] === 1, 'Empty edit restores primary; repeated Add preserves the saved fallback');
    foreach ([null,4,999] as $invalidArea) {
        $conn->query('UPDATE users SET area_id = ' . ($invalidArea ?? 'NULL') . ' WHERE id=21');
        try { registerDepartmentRecommender($conn,21,1); throw new LogicException('Invalid primary area accepted'); }
        catch (RuntimeException $e) { check(str_contains($e->getMessage(),'no valid primary area'), 'Missing, foreign or nonexistent primary area rejected clearly'); }
    }
    check((int) $conn->query('SELECT COUNT(*) FROM department_recommenders WHERE user_id=21')->fetch_row()[0] === 0, 'Invalid primary area leaves no registration');
    try { registerDepartmentRecommender($conn,23,1); throw new LogicException('Foreign user accepted'); }
    catch (RuntimeException $e) { check(str_contains($e->getMessage(),'does not belong'), 'Foreign-department recommender rejected'); }
    $conn->rollback();
    check($beforeUsers === $conn->query('SELECT * FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC)
        && $beforeRegistrations === $conn->query('SELECT * FROM department_recommenders ORDER BY id')->fetch_all(MYSQLI_ASSOC)
        && $beforeAreas === $conn->query('SELECT * FROM recommender_area_assignments ORDER BY id')->fetch_all(MYSQLI_ASSOC), 'Registration tests roll back without changing existing records');

} finally {
    $conn->query("DROP DATABASE `$db`");
}
