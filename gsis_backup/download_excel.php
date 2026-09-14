<?php
// Path to your Excel macro file
$file = __DIR__ . "/files/template_gaslist_upload.xlsm";  

// Check if file exists
if (file_exists($file)) {
    header("Content-Description: File Transfer");
    header("Content-Type: application/vnd.ms-excel.sheet.macroEnabled.12");
    header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate");
    header("Pragma: public");
    header("Content-Length: " . filesize($file));
    readfile($file);
    exit;
} else {
    echo "File not found!";
}
?>
