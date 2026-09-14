<?php
$recordsPerPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $recordsPerPage;
?>