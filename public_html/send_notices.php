<?php
// Set a higher time limit for script execution, as generating multiple files and emailing can take time.
set_time_limit(300);

// --- Request Validation ---
// Ensure this script is called via a POST request and that the 'notices' data is present.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['notices'])) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(array('status' => 'error', 'message' => 'Invalid request.'));
    exit;
}

// Decode the JSON array of notice data sent from the client.
$noticeQueryStrings = json_decode($_POST['notices'], true);

// Check if JSON decoding was successful and if the array is not empty.
if (json_last_error() !== JSON_ERROR_NONE || !is_array($noticeQueryStrings) || empty($noticeQueryStrings)) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(array('status' => 'error', 'message' => 'No notices were selected or the data was corrupted.'));
    exit;
}

// Include the TBS library.
require_once __DIR__ . '/../vendor/autoload.php';

// --- Database Connection ---
$host = "localhost";
$user = "root";
$password = "";
$dbname = "parking";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode(array('status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error));
    exit;
}

// --- Reusable Functions (adapted from download_notices.php) ---

/**
 * Converts a date from Sydney timezone to UTC.
 */
function convertSydneyToUTC($date, $dayBoundary = 'start', $format = 'Y-m-d H:i:s')
{
    $dateTime = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Australia/Sydney'));
    if (!$dateTime) {
        return 'ERROR';
    }
    if ($dayBoundary === 'end') {
        $dateTime->setTime(23, 59, 59);
    } else {
        $dateTime->setTime(0, 0, 0);
    }
    $dateTime->setTimezone(new DateTimeZone('UTC'));
    return $dateTime->format($format);
}

/**
 * Fetches image data for a given vehicle plate.
 */
function addImages($table, $images, $plate, $timeFrom, $timeTo, $conn) {
    $stmt = null;
    switch ($table) {
        case 'parking_records':
            $sql = "SELECT * FROM parking_records WHERE plate = ? AND phototime BETWEEN ? AND ? GROUP BY checksum ORDER BY phototime";
            $caption = 'Visitor Parking';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $plate, $timeFrom, $timeTo);
            break;
        case 'notice':
            $sql = "SELECT * FROM notice WHERE plate = ? GROUP BY checksum ORDER BY phototime DESC";
            $caption = 'Notice';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $plate);
            break;
        case 'survey':
            $sql = "SELECT * FROM survey WHERE plate = ? ORDER BY phototime DESC LIMIT 1";
            $caption = 'Survey';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $plate);
            break;
        default:
            return $images;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $filePath = $row['uploadFile'];
            if (trim($filePath) == '' || !file_exists($filePath)) continue;

            $utcPhototime = new DateTime($row['phototime'], new DateTimeZone('UTC'));
            $utcPhototime->setTimezone(new DateTimeZone('Australia/Sydney'));
            $formattedPhototime = $utcPhototime->format('d/m/Y H:i:s');
            $xcaption = $caption;
            if (isset($row['containsnumber']) && $row['containsnumber'] == 'Yes') {
                $xcaption = 'Resident Park';
            }
            $images[] = array('file' => realpath($filePath), 'caption' => $xcaption, 'phototime' => $formattedPhototime);
        }
    }
    $stmt->close();
    return $images;
}


// --- Main Processing Logic ---

// Initialize TBS
new clsOpenTBS();
$TBS = new clsTinyButStrong;
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

$attachmentFiles = array();
$offendingPlates = array(); // Array to store plate numbers
// Create a unique temporary directory to store the generated notices.
$tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'notices_' . uniqid();
if (!mkdir($tempDir, 0700)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(array('status' => 'error', 'message' => 'Failed to create a temporary directory for notices.'));
    exit;
}

// Loop through each selected offender to generate their notice file.
foreach ($noticeQueryStrings as $queryString) {
    parse_str($queryString, $data);

    $plate = isset($data['plate']) ? $data['plate'] : 'UNKNOWN';
    if ($plate !== 'UNKNOWN' && !in_array($plate, $offendingPlates)) {
        $offendingPlates[] = $plate;
    }

    // If unit number is missing, try to find it in the vehicles table.
    if (empty($data['unitnumber'])) {
        $sql = "SELECT unitnumber FROM vehicles WHERE plate=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $plate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if (isset($row['unitnumber'])) {
            $data['unitnumber'] = $row['unitnumber'];
        }
        $stmt->close();
    }

    $timeFrom = convertSydneyToUTC($data['datefrom'], 'start');
    $timeTo = convertSydneyToUTC($data['dateto'], 'end');

    $images = array();
    $images = addImages('notice', $images, $plate, $timeFrom, $timeTo, $conn);
    $images = addImages('survey', $images, $plate, $timeFrom, $timeTo, $conn);
    $images = addImages('parking_records', $images, $plate, $timeFrom, $timeTo, $conn);

    $template = empty($data['unitnumber']) ? 'template.docx' : 'template_unitnumber.docx';
    $TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);

    // Populate template variables
    foreach ($data as $name => $val) {
       $TBS->VarRef[$name] = $val;
    }
    $TBS->VarRef['platex'] = $plate;
    $TBS->MergeBlock('blkImg', $images);

    // Save the generated document to the temporary directory
    $filename = 'Notice-' . $plate . '-' . $data['dateto'] . '.docx';
    $filepath = $tempDir . DIRECTORY_SEPARATOR . $filename;
    $TBS->Show(OPENTBS_FILE, $filepath);

    $attachmentFiles[] = array('path' => $filepath, 'name' => $filename);
}
$conn->close();

// --- Emailing Section ---
$to = "00511306782@print.brother.com";  // PW Printer
//$to = "21055527210@print.brother.com";  // BM Printer
$subject = "Parking Breach Notices - " . date("Y-m-d H:i");
$from = "no-reply@completewebservices.com.au"; // CHANGE THIS to a valid sending email address.
$boundary = "boundary-" . md5(time());
$eol = "\r\n";

// Construct email headers for a multipart message with attachments.
$headers = "From: Parking Enforcement <" . $from . ">" . $eol;
$headers .= "MIME-Version: 1.0" . $eol;
$headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"" . $eol;

// Construct the email body
$body = "--" . $boundary . $eol;
$body .= "Content-Type: text/plain; charset=ISO-8859-1" . $eol;
$body .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
if (!empty($offendingPlates)) {
    $body .= count($attachmentFiles) . " parking breach notice(s) have been issued for vehicle(s) with plate(s): " . implode(', ', $offendingPlates) . "." . $eol . $eol;
} else {
    $body .= count($attachmentFiles) . " parking breach notice(s) have been issued." . $eol . $eol;
}


// Attach each generated file to the email.
foreach ($attachmentFiles as $file) {
    $content = chunk_split(base64_encode(file_get_contents($file['path'])));
    $body .= "--" . $boundary . $eol;
    $body .= "Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document; name=\"" . $file['name'] . "\"" . $eol;
    $body .= "Content-Transfer-Encoding: base64" . $eol;
    $body .= "Content-Disposition: attachment; filename=\"" . $file['name'] . "\"" . $eol . $eol;
    $body .= $content . $eol;
}
$body .= "--" . $boundary . "--";

// Attempt to send the email.
$mailSent = mail($to, $subject, $body, $headers);

// --- Cleanup ---
// Delete the temporary files and the directory.
foreach ($attachmentFiles as $file) {
    if (file_exists($file['path'])) {
        unlink($file['path']);
    }
}
rmdir($tempDir);

// --- Final Response ---
// Send a final JSON response back to the client.
header('Content-Type: application/json');
if ($mailSent) {
    echo json_encode(array('status' => 'success', 'message' => count($attachmentFiles) . ' notice(s) have been successfully generated and emailed.'));
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(array('status' => 'error', 'message' => 'The server generated the notices but failed to send the email.'));
}
exit;
?>
