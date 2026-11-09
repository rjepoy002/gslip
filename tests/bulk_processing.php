<?php
// Reuse the isolated MySQL/HTTP regression fixtures, never the application's database.
define('GSLIP_BULK_TEST', true);
require __DIR__ . '/gas_slip_access.php';
