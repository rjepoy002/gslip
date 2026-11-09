<?php
// CLI preflight by default, following the existing migration convention.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$database = getenv('GSLIP_MIGRATION_DATABASE');
if (!$database) { fwrite(STDERR,"Set GSLIP_MIGRATION_DATABASE explicitly. Default mode is preflight.\n"); exit(1); }
$conn = new mysqli(getenv('GSLIP_MIGRATION_HOST') ?: 'localhost',getenv('GSLIP_MIGRATION_USER') ?: 'root',getenv('GSLIP_MIGRATION_PASSWORD') ?: '',$database);
foreach (['users','gas_slips'] as $table) {
    $ddl = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
    $columns = array_column($conn->query("SHOW COLUMNS FROM `$table`")->fetch_all(MYSQLI_ASSOC),'Type','Field');
    if (stripos($ddl,'ENGINE=InnoDB')===false || !preg_match('/^int(?:\(\d+\))?$/i',$columns['id'] ?? '')) { throw new RuntimeException("$table must use InnoDB with a signed INT id. No changes made."); }
    echo "$table: schema checked\n";
}
$sql = file_get_contents(__DIR__.'/20261106_bulk_processing.sql');
if (!in_array('--apply',$argv,true)) { echo $sql,"\nPreflight only; no changes made.\n"; exit; }
$conn->multi_query($sql);
do { if ($result=$conn->store_result()) { $result->free(); } } while ($conn->more_results() && $conn->next_result());
echo "Bulk processing migration complete. Existing gas slips were not changed.\n";
