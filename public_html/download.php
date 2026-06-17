<?php



// Secure file download script

// Define the directory where files are stored
$baseDirs = [realpath(__DIR__ . '/../var/uploads'), realpath(__DIR__ . '/../var/survey')];

foreach ($baseDirs as $baseDir) {
    if ($baseDir === false) {
        die('Invalid base directory configuration.');
    }
}

// Check if the file parameter is set
if (!isset($_GET['file'])) {
    die('No file specified.');
}

// Get the file name from the query parameter
$file = basename($_GET['file']); // Sanitize input to prevent directory traversal
foreach($baseDirs as $baseDir) {
    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $file);
    if ($filePath!==false) {
        break;
    }
}

// Verify that the resolved path is within the allowed directory
if ($filePath === false || strpos($filePath, $baseDir) !== 0) {
    die('Access denied.');
}

// Check if the file exists
if (!file_exists($filePath)) {
    die('File does not exist.');
}

// Set appropriate headers for file download
header('Content-Description: File Transfer');
header('Content-Type: image/jpeg');
//header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Read the file and output its contents
readfile($filePath);
exit;
