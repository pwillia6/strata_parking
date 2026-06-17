<?php
// Set a higher time limit for script execution, as generating multiple files and emailing can take time.
set_time_limit(300);

// --- Request Validation ---
// Ensure this script is called via a POST request and that the 'permits' data is present.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['permits'])) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(array('status' => 'error', 'message' => 'Invalid request.'));
    exit;
}

// Decode the JSON array of permit data sent from the client.
$permitQueryStrings = json_decode($_POST['permits'], true);

// Check if JSON decoding was successful and if the array is not empty.
if (json_last_error() !== JSON_ERROR_NONE || !is_array($permitQueryStrings) || empty($permitQueryStrings)) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(array('status' => 'error', 'message' => 'No permits were selected or the data was corrupted.'));
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


// --- Main Processing Logic ---

// Initialize TBS
new clsOpenTBS();
$TBS = new clsTinyButStrong;
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

$success_count = 0;
$failure_count = 0;
$no_email_count = 0;
$processed_plates = array();
// Create a unique temporary directory to store the generated permits.
$tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'permits_' . uniqid();
if (!mkdir($tempDir, 0700)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(array('status' => 'error', 'message' => 'Failed to create a temporary directory for permits.'));
    exit;
}

// Loop through each selected permission to generate their permit file and email it.
foreach ($permitQueryStrings as $queryString) {
    parse_str($queryString, $data);

    $plate = isset($data['plate']) ? $data['plate'] : 'UNKNOWN';
    $email_to = isset($data['email']) ? trim($data['email']) : '';

    if (empty($email_to) || !filter_var($email_to, FILTER_VALIDATE_EMAIL)) {
        $no_email_count++;
        if ($plate !== 'UNKNOWN' && !in_array($plate, $processed_plates)) {
            $processed_plates[] = $plate;
        }
        continue; // Skip to next permit if no valid email
    }

    if ($plate !== 'UNKNOWN' && !in_array($plate, $processed_plates)) {
        $processed_plates[] = $plate;
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

    // Format expiry date for display
    $original_expiry_date = isset($data['expiry_date']) ? $data['expiry_date'] : '';
    if (!empty($original_expiry_date)) {
        $data['expiry_date'] = date('d-m-Y', strtotime($original_expiry_date));
    } else {
        $data['expiry_date'] = 'Never';
    }

    $template = 'permit.docx';
    $TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);

    // Populate template variables
    foreach ($data as $name => $val) {
       $TBS->VarRef[$name] = $val;
    }

    // Save the generated document to the temporary directory
    $docx_filename = 'Permit-' . $plate . '.docx';
    $docx_filepath = $tempDir . DIRECTORY_SEPARATOR . $docx_filename;
    $TBS->Show(OPENTBS_FILE, $docx_filepath);

    // --- Convert to PDF using LibreOffice ---
    $pdf_filename = 'Permit-' . $plate . '.pdf';
    $pdf_filepath = $tempDir . DIRECTORY_SEPARATOR . $pdf_filename;
    
    // Build and execute the conversion command
    $command = '/opt/libreoffice3.6/program/swriter --headless --convert-to pdf ' . escapeshellarg($docx_filepath) . ' --outdir ' . escapeshellarg($tempDir);
    shell_exec($command);

    $attachmentFile = array();
    // Check if PDF was created and add to attachments, otherwise fall back to DOCX
    if (file_exists($pdf_filepath)) {
        $attachmentFile = array('path' => $pdf_filepath, 'name' => $pdf_filename, 'type' => 'application/pdf');
        unlink($docx_filepath); // Cleanup the docx file
    } else {
        $attachmentFile = array('path' => $docx_filepath, 'name' => $docx_filename, 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    // --- Emailing Section for this permit ---
    $to = $email_to;
    $subject = "Parking Permit for " . $plate;
    $from = "no-reply@completewebservices.com.au"; // CHANGE THIS to a valid sending email address.
    $boundary = "boundary-" . md5(time());
    $eol = "\r\n";

    // Construct email headers
    $headers = "From: KQ Parking Permit<" . $from . ">" . $eol;
    $headers .= "Cc: management@kingstonquarter.com.au" . $eol;
    $headers .= "MIME-Version: 1.0" . $eol;
    $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"" . $eol;

    // Construct the email body
    $body = "--" . $boundary . $eol;
    $body .= "Content-Type: text/plain; charset=ISO-8859-1" . $eol;
    $body .= "Content-Transfer-Encoding: 7bit" . $eol . $eol;
    $body .= "Dear Resident," . $eol . $eol;
    $body .= "Please find attached the visitor parking permit for vehicle with plate number " . $plate . "." . $eol . $eol;
    $body .= "Please put on or under your windscreen where it can be seen" . $eol . $eol;
    $body .= "Regards," . $eol . "Building Manager" . $eol . $eol;

    // Attach the generated file
    $content = chunk_split(base64_encode(file_get_contents($attachmentFile['path'])));
    $body .= "--" . $boundary . $eol;
    $body .= "Content-Type: " . $attachmentFile['type'] . "; name=\"" . $attachmentFile['name'] . "\"" . $eol;
    $body .= "Content-Transfer-Encoding: base64" . $eol;
    $body .= "Content-Disposition: attachment; filename=\"" . $attachmentFile['name'] . "\"" . $eol . $eol;
    $body .= $content . $eol;
    $body .= "--" . $boundary . "--";

    // Attempt to send the email.
    if (mail($to, $subject, $body, $headers)) {
        $success_count++;
    } else {
        $failure_count++;
    }

    // Cleanup the file for this iteration
    if (file_exists($attachmentFile['path'])) {
        unlink($attachmentFile['path']);
    }
}
$conn->close();

// --- Cleanup ---
// Delete the temporary directory.
rmdir($tempDir);

// --- Final Response ---
$message_parts = array();
if ($success_count > 0) {
    $message_parts[] = "$success_count permit(s) successfully generated and emailed.";
}
if ($failure_count > 0) {
    $message_parts[] = "Failed to email $failure_count permit(s).";
}
if ($no_email_count > 0) {
    $message_parts[] = "$no_email_count permit(s) were skipped due to missing or invalid email addresses.";
}

if (empty($message_parts)) {
    $message = "No permits were processed.";
} else {
    $message = implode(' ', $message_parts);
}

// Send a final JSON response back to the client.
header('Content-Type: application/json');
if ($failure_count > 0) {
    http_response_code(500); // Internal Server Error if any email failed
    echo json_encode(array('status' => 'error', 'message' => $message));
} else {
    echo json_encode(array('status' => 'success', 'message' => $message));
}
exit;
?>
