<?php

define('V2','Yes');

// The autoloader in header.php will handle including Database, Semaphore, etc.
$plate = null;

// Check if the request is a POST or GET
try {
    $prompt = "What is the number plate of this vehicle in alphanumeric format ?  Include only aphanumeric characters in the plate.   Is a paper notice pinned on the windsceen?    JSON format element 'plate', 'notice_pinned'";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
        $requestData = FileHandler::getRequestDataForUpload(__DIR__ . '/../var/uploads/');
        $info = OpenAiVisionExtractor::extractDataFromImage($requestData->imagePath, $prompt);
        $plate = $info->plate;
        $noticepinned = $info->notice_pinned ? "Yes" : "No";
        $result = $info->raw_result;
        $checksum = $info->checksum;

        $uploadFile = FileHandler::saveUploadedFile($requestData->imagePath, $requestData->originalName, $requestData->uploadDir, $plate, $requestData->phototime);
        Database::insertNoticeRecord($plate, $noticepinned, $result, $uploadFile, $requestData->phototime, $checksum, $requestData->uuid);
        echo "<h2>" . htmlspecialchars($result) . "</h2>";

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $imagePath = Database::getNoticeUploadFileById($id);

        $info = OpenAiVisionExtractor::extractDataFromImage($imagePath, $prompt);
        $plate = $info->plate;
        $noticepinned = $info->notice_pinned ? "Yes" : "No";
        $result = $info->raw_result;
        $checksum = $info->checksum;

        Database::updateNoticeRecord($id, $plate, $noticepinned, $result, $checksum);
        echo "<h2>" . htmlspecialchars($result) . "</h2>";
    } else {
        die("Error: Invalid request.");
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die("Error: " . $e->getMessage());
}

// If a plate was processed, show its history
if ($plate === null) {
    Database::close();
    exit;
}

// Prepare the SQL query
$result = Database::getNoticeHistoryByPlate($plate);

// Generate HTML table of previous sightings
if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Photo</th><th>Timestamp</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $filePath = $row['uploadFile'];
        $base64Image = "";

        if (file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            $base64Image = base64_encode($fileData);
        }

        // Convert UTC timestamp to Australia/Sydney time zone
        $utcTimestamp = new DateTime($row['timestamp'], new DateTimeZone('UTC'));
        $utcTimestamp->setTimezone(new DateTimeZone('Australia/Sydney'));
        $formattedTimestamp = $utcTimestamp->format('Y-m-d H:i:s');

        echo "<tr>";
        echo "<td><img src='data:image/jpeg;base64," . htmlspecialchars($base64Image) . "' alt='Photo' width='100'></td>";
        echo "<td>" . htmlspecialchars($formattedTimestamp) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "No records found for plate: " . htmlspecialchars($plate);
}

Database::close();
