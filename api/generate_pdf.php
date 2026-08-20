<?php
require_once __DIR__ . '/../libs/bootstrap.php';
require_once __DIR__ . '/../libs/TcmPdfGenerator.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    http_response_code(400);
    die("Error: Token tidak diberikan.");
}

$storageDir = getTcmStorageDir();
$pdf_dir = $storageDir . '/pdf/';
$log_dir = $storageDir . '/logs/';

function writeLog($msg) {
    global $log_dir;
    @file_put_contents($log_dir . 'app.log', date('Y-m-d H:i:s') . " - " . $msg . PHP_EOL, FILE_APPEND);
}

try {
    $pdo = getTcmDatabase();

    $generator = new TcmPdfGenerator($pdo, $storageDir);
    $pdfPath = $generator->generate($token);

    if (!file_exists($pdfPath)) {
        writeLog("ERROR: Generated PDF not found at expected path: $pdfPath");
        die("Error: Generated PDF not found.");
    }

    writeLog("Pure PHP PDF generated and saved to: " . $pdfPath);

    // Prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($pdfPath) . '"');
    header('Content-Length: ' . filesize($pdfPath));

    readfile($pdfPath);
    exit;

} catch (Exception $e) {
    writeLog("Error generating PDF: " . $e->getMessage());
    die("Error: " . htmlspecialchars($e->getMessage()));
}
