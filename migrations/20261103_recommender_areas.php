<?php
/* CLI only. Default mode is read-only preflight; --apply must target a copy first. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$database = getenv('GSLIP_MIGRATION_DATABASE');
if (!$database) { fwrite(STDERR, "Set GSLIP_MIGRATION_DATABASE explicitly. Default mode is preflight.\n"); exit(1); }
$conn = new mysqli(getenv('GSLIP_MIGRATION_HOST') ?: 'localhost', getenv('GSLIP_MIGRATION_USER') ?: 'root', getenv('GSLIP_MIGRATION_PASSWORD') ?: '', $database);
$types = [];
foreach (['departments', 'users', 'areas', 'department_areas', 'department_recommenders'] as $table) {
    $result = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
    if (stripos($result, 'ENGINE=InnoDB') === false) { throw new RuntimeException("$table must use InnoDB"); }
    $columns = [];
    foreach ($conn->query("SHOW COLUMNS FROM `$table`") as $column) { $columns[$column['Field']] = $column['Type']; }
    foreach (($table === 'department_recommenders' ? ['department_id','user_id'] : ($table === 'department_areas' ? ['department_id','area_id'] : ['id'])) as $required) {
        if (!isset($columns[$required])) { throw new RuntimeException("Missing $table.$required"); }
    }
    if (isset($columns['id'])) {
        if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(\d+\))?( unsigned)?$/i', $columns['id'])) { throw new RuntimeException("Unsupported $table.id type"); }
        $types[$table] = $columns['id'];
    }
    echo "$table: schema checked\n";
}
$ddl = "CREATE TABLE IF NOT EXISTS recommender_area_assignments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 department_id {$types['departments']} NOT NULL,
 recommender_id {$types['users']} NOT NULL,
 area_id {$types['areas']} NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_recommender_area (department_id,recommender_id,area_id),
 KEY idx_recommender (recommender_id), KEY idx_area (area_id),
 FOREIGN KEY (department_id) REFERENCES departments(id),
 FOREIGN KEY (recommender_id) REFERENCES users(id),
 FOREIGN KEY (area_id) REFERENCES areas(id)
) ENGINE=InnoDB";
echo $ddl, ";\n";
if (!in_array('--apply', $argv, true)) { echo "Preflight only; no changes made.\n"; exit; }
$conn->query($ddl);
$conn->query('CREATE TABLE IF NOT EXISTS gslip_schema_migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$conn->begin_transaction();
try {
    // The marker and seed commit together. Reruns never restore deliberately removed areas.
    $conn->query("INSERT IGNORE INTO gslip_schema_migrations (name) VALUES ('20261103_recommender_areas')");
    if ($conn->affected_rows > 0) {
        $conn->query("INSERT IGNORE INTO recommender_area_assignments (department_id,recommender_id,area_id)
            SELECT dr.department_id, dr.user_id, u.area_id FROM department_recommenders dr
            JOIN users u ON u.id = dr.user_id JOIN areas a ON a.id = u.area_id
            JOIN departments d ON d.id = dr.department_id
            WHERE UPPER(TRIM(d.name)) IN ('ANOD','ASOD')");
    }
    $conn->commit();
    echo "Migration complete.\n";
} catch (Throwable $e) { $conn->rollback(); throw $e; }
