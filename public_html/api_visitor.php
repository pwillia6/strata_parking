<?php

if (isset($_GET['ping'])) {
    echo 'pong';
    exit;
}

define('V2','Yes');

// The autoloader in header.php will handle including Database, Semaphore, etc.
$plate = null;

// Check if the request is a POST or GET
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
        $requestData = FileHandler::getRequestDataForUpload(__DIR__ . '/../var/uploads/');
        // 1. Extract data from image
        $prompt = "What is the number plate of this vehicle in alphanumeric format ?  Include only aphanumeric characters in the plate.   Does the floor contain the word 'car'?  Does the floor resemble any a part of the word 'visitor'?  Does the floor contain a number?   JSON format element 'plate', 'contains_car','contains_visitor' and 'contains_number";
        $info = OpenAiVisionExtractor::extractDataFromImage($requestData->imagePath, $prompt);

        $plate = $info->plate;
        $containscar = $info->contains_car ? "Yes" : "No";
        $containsvisitor = $info->contains_visitor ? "Yes" : "No";
        $containsnumber = $info->contains_number ? "Yes" : "No";
        $result = $info->raw_result;
        $checksum = $info->checksum;

        $uploadFile = FileHandler::saveUploadedFile($requestData->imagePath, $requestData->originalName, $requestData->uploadDir, $plate, $requestData->phototime);

        // 3. Insert record into database
        Database::insertParkingRecord($plate, $containscar, $containsvisitor, $containsnumber, $result, $uploadFile, $requestData->phototime, $checksum, $requestData->uuid);
        echo "<h2>" . htmlspecialchars($result) . "</h2>";

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        // Handle GET request (re-processing)
        $id = intval($_GET['id']);

        // 1. Fetch the existing record
        $imagePath = Database::getParkingRecordUploadFileById($id);

        // 2. Extract data from image
        $prompt = "What is the number plate of this vehicle in alphanumeric format ?  Include only aphanumeric characters in the plate.   Does the floor contain the word 'car'?  Does the floor resemble any a part of the word 'visitor'?  Does the floor contain a number?   JSON format element 'plate', 'contains_car','contains_visitor' and 'contains_number";
        $info = OpenAiVisionExtractor::extractDataFromImage($imagePath, $prompt);

        $plate = $info->plate;
        $containscar = $info->contains_car ? "Yes" : "No";
        $containsvisitor = $info->contains_visitor ? "Yes" : "No";
        $containsnumber = $info->contains_number ? "Yes" : "No";
        $result = $info->raw_result;
        $checksum = $info->checksum;

        // 3. Update the record
        Database::updateParkingRecord($id, $plate, $containscar, $containsvisitor, $containsnumber, $result, $checksum);
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
$result = Database::getParkingHistoryByPlate($plate);

// Generate HTML table of previous sightings
if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Photo</th><th>Timestamp</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $filePath = $row['uploadFile']; // This is an absolute path
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
