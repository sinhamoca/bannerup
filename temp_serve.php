<?php
if (!isset($_GET['file'])) {
    http_response_code(404);
    exit;
}

$tempDir = sys_get_temp_dir();
$filePath = $tempDir . DIRECTORY_SEPARATOR . basename($_GET['file']);

if (file_exists($filePath)) {
    header('Content-Type: image/png');
    readfile($filePath);
    exit;
} else {
    http_response_code(404);
    exit;
}
?>