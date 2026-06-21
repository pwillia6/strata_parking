<?php

// The autoloader in header.php will handle including Database, Semaphore, etc.
$plate = null;

// Check if the request is a POST or GET
try {
    $prompt = "What is the number plate of this vehicle in alphanumeric format?  Include only alphanumeric characters on the plate.   On the floor is a unit number which will be one for the following \"101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320,401,402,403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,418,501,502,503,504,505,506,507,508,509,510,511,512,513,514,515,516,517,518,519,520,601,602,603,604,605,606,607,608,609,610,611,612,613,614,701,702,703,704,705,706,707,708,709,710,711,712,713,714,715,716,801,802,803,804,805,806,807,808,809,810,811,812,813,814,815,816,901,902,903,904,905,906,907,1001,1002,1003,1004,1005,1006,1007,1008,001\"?   Extract the plat and unit number into JSON, format element \'plate\', \'unit_number\'";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
        $requestData = FileHandler::getRequestDataForUpload(__DIR__ . '/../var/survey/');
        $info = OpenAiVisionExtractor::extractDataFromImage($requestData->imagePath, $prompt);
        $plate = $info->plate;

        $uploadFile = FileHandler::saveUploadedFile($requestData->imagePath, $requestData->originalName, $requestData->uploadDir, $info->plate, $requestData->phototime);
        Database::insertSurveyRecord($info->plate, $info->unit_number, $info->raw_result, $uploadFile, $requestData->phototime, $info->checksum, $requestData->uuid);
        echo "<h2>" . htmlspecialchars($info->raw_result) . "</h2>";

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $imagePath = Database::getSurveyUploadFileById($id);

        $info = OpenAiVisionExtractor::extractDataFromImage($imagePath, $prompt);
        $plate = $info->plate;
        if ($plate === null) {
            // DB Will not accept record
        } else {
            Database::updateSurveyRecord($id, $info->plate, $info->unit_number, $info->raw_result, $info->checksum);
        }
        echo "<h2>" . htmlspecialchars($info->raw_result) . "</h2>";
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
$result = Database::getSurveyHistoryByPlate($plate);

// Generate HTML table of previous sightings
if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Photo</th><th>Unitnumber</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $filePath = $row['uploadFile'];
        $base64Image = "";

        if (file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            $base64Image = base64_encode($fileData);
        }

        echo "<tr>";
        echo "<td><img src='data:image/jpeg;base64," . htmlspecialchars($base64Image) . "' alt='Photo' width='100'></td>";
        echo "<td>" . htmlspecialchars($row['unitnumber']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "No records found for plate: " . htmlspecialchars($plate);
}

Database::close();
