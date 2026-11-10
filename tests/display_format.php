<?php
require_once __DIR__.'/../includes/display_format.php';
require_once __DIR__.'/../includes/gas_slip_report.php';
$checks = 0;
function checkDisplay($actual, $expected): void {
    global $checks;
    if ($actual !== $expected) throw new RuntimeException('Expected '.var_export($expected,true).'; got '.var_export($actual,true));
    ++$checks;
}
foreach ([['E. garcellano','E. GARCELLANO'],['Asnain','ASNAIN'],['Batul','BATUL'],['J. arzaga','J. ARZAGA'],['Peña, Niño','PEÑA, NIÑO'],[null,''],['','']] as [$input,$expected]) {
    checkDisplay(formatRequestedBy($input),$expected);
}
foreach ([
    ['READING','Reading'],
    ['LINE CLEARING AT ABORLAN','Line Clearing at Aborlan'],
    ['LINE CLEARING-CHAINSAW','Line Clearing-Chainsaw'],
    ['LINE MAINTENANCE','Line Maintenance'],
    ['AT THE OFFICE OF PALECO AND ICT FOR GPS','At the Office of PALECO and ICT for GPS'],
    ['inspection IN ñARRA ON THE LINE TO espaÑola','Inspection in Ñarra on the Line to Española'],
    ["  LINE\nCLEARING, ICT/GPS; PALECO!  ", "  Line\nClearing, ICT/GPS; PALECO!  "],
    [null,''],['',''],
] as [$input,$expected]) {
    checkDisplay(formatPurpose($input),$expected);
    checkDisplay(formatPurpose(formatPurpose($input)),$expected);
}
foreach ([
    ['Narra → ANTIPULUAN INTERIOR','Narra → Antipuluan Interior'],
    ['Narra → ABORLAN-QUEZON-RIZAL','Narra → Aborlan-Quezon-Rizal'],
    ['Narra → LABOG, ESPAÑOLA-DUMANGUEÑA','Narra → Labog, Española-Dumangueña'],
    ['Narra → Taritien-Antipuluan','Narra → Taritien-Antipuluan'],
    ['R-001 → PALECO / ICT; AB12 → GPS','R-001 → PALECO / ICT; AB12 → GPS'],
    ["O'NEILL → PEÑA; LABOG -> NARRA", "O'Neill → Peña; Labog -> Narra"],
    [null,''],['',''],
] as [$input,$expected]) {
    checkDisplay(formatRoute($input),$expected);
    checkDisplay(formatRoute(formatRoute($input)),$expected);
}
$raw = ['requested_by'=>'Peña','purpose'=>'LINE CLEARING AT PALECO','route_path'=>'NARRA → LABOG'];
$copy = $raw;
foreach ($raw as $value) {
    $formatted = formatPurpose($value);
    checkDisplay(preg_replace('/[\p{L}\p{M}\p{N}]+/u','',$formatted),preg_replace('/[\p{L}\p{M}\p{N}]+/u','',$value));
}
checkDisplay($raw,$copy);
checkDisplay(htmlspecialchars(formatPurpose('<SCRIPT>alert("X")</SCRIPT>'),ENT_QUOTES,'UTF-8'),'&lt;Script&gt;Alert(&quot;X&quot;)&lt;/Script&gt;');
// The same value builder supplies Reports and Monitoring CSV exports.
$report = array_fill_keys(['gas_slip_id','date_issued','validity_until','plate_no','fuel','liters','fuel_items','fuel_quantities','recommender_name','recommended_at','approver_name','approved_at','rejector_name','rejected_at','rejection_reason','printed_at','department','area'], '');
$report += $raw + ['status'=>'approved'];
foreach ([false,true] as $monitoring) {
    $cells=array_combine(gasSlipReportHeaders($monitoring),gasSlipReportValues($report,$monitoring));
    checkDisplay($cells['Requested By'],'PEÑA');
    checkDisplay($cells['Purpose'],'Line Clearing at PALECO');
    checkDisplay($cells[$monitoring?'Routes':'Route'],'Narra → Labog');
}
checkDisplay($report['requested_by'],'Peña');
echo "$checks display-format checks passed.\n";
