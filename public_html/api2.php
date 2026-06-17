<?php

if (isset($_GET['ping'])) {
    echo 'pong';
    exit;
}

require_once __DIR__ . '/../lib/config.php';

define('V2','Yes');

// Add to database 
// Create a connection to MySQL
$host = "localhost";
$user = "root";
$password = "";
$dbname = "parking";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

class Semaphore {
    private static $lockFile;

    public static function acquire() {
        if (!self::$lockFile) {
            self::$lockFile = fopen(__DIR__ . "/../var/api_call_semaphore.lock", "w+");
        }
        return flock(self::$lockFile, LOCK_EX);
    }

    public static function release() {
        if (self::$lockFile) {
            sleep(2); /* So we don't do this too often - ChatGPT starts grouping things */
            flock(self::$lockFile, LOCK_UN);
            fclose(self::$lockFile);
            self::$lockFile = null;
        }
    }
}

function fileChecksum($fileContent) {
    // Calculate checksum
    return md5($fileContent);
}

include 'extractPlateNumber.php';


function removeLinesStartingWithBackticks($inputString) {
    // Split the string into lines
    $lines = explode("\n", $inputString);
    
    // Filter out lines that start with triple backticks
    $filteredLines = array_filter($lines, function($line) {
        return strpos(trim($line), '```') !== 0;
    });
    
    // Join the filtered lines back into a single string
    return implode("\n", $filteredLines);
}

// Function to call the ChatGPT API
function extractPlateFromImageFile($imagePath) {

    // Acquire semaphore to ensure only one API call is executed concurrently
    if (!Semaphore::acquire()) {
        throw new Exception("Could not acquire lock to call API.");
    }

    // Verify that the file exists
    if (!file_exists($imagePath)) {
        echo "Error: File not found at $imagePath\n";
        exit(1);
    }

    // Read and encode the image as Base64
    $imageData = base64_encode($fileContent = file_get_contents($imagePath));

    // Prepare payload for the OpenAI GPT-4 Vision API
    $query = "What is the number plate of this vehicle in alphanumeric format ?  Include only aphanumeric characters in the plate.   Does the floor contain the word 'car'?  Does the floor resemble any a part of the word 'visitor'?  Does the floor contain a number?   JSON format element 'plate', 'contains_car','contains_visitor' and 'contains_number";
    $apiUrl = "https://api.openai.com/v1/chat/completions";
    $payload = [
        "model" => "gpt-4o",
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $query],
                    ["type" => "image_url", "image_url" => (object) [ 'url' =>  "data:image/jpeg;base64,$imageData"]]
                ]
            ]
        ],
        "max_tokens" => 300
    ];
 
    // Prepare headers for the request
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . OPENAI_API_KEY
    ];

    try {
        // Make the API request using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        // Execute and handle the response
        $response = curl_exec($ch);
        error_log('Response:' . $response);
        if (curl_errno($ch)) {
            echo "Error: " . curl_error($ch) . "\n";
            exit(1);
        }
        curl_close($ch);

        // Decode and display the output
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            $json = $result = $result['choices'][0]['message']['content'] . "\n";
            $result = removeLinesStartingWithBackticks($result);
            $info = json_decode($result);
        } else {
            echo "Error: Unable to extract plate.\n";
        }
        $return = [ 'plate' => $info->plate, 
                 'result' => $result,  
                 'containscar' => $info->contains_car ? "Yes" : "No",
                 'containsvisitor' => $info->contains_visitor ? "Yes" : "No",
                 'containsnumber' => $info->contains_number ? "Yes" : "No",
                 'checksum' => fileChecksum($fileContent) 
                ];
        return $return;
    } finally {
        // Release semaphore lock
        Semaphore::release();
    }
}

// Check if the request is a POST or GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    
    $uploadDir = __DIR__ . '/../var/uploads/';
    $phototime = $_POST['phototime'];
    $uuid = isset($_POST['uuid']) ? $_POST['uuid'] : null;

    // Ensure the upload directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    extract(extractPlateFromImageFile($_FILES['photo']['tmp_name']));

    /* Save the photo */
    // Generate a unique filename based on the current time
    $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    // Validate file type
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(['_error' => 'Invalid file type']);
        exit;
    }

    $uniqueFileName =  $plate . ' ' . $phototime . '.' . $fileExtension;
    $uploadFile = $uploadDir . $uniqueFileName;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
        echo "{ '_error' : 'Invalid Call A' }";
        exit;
    }
    /* End save photo */

    $imagePath = $uploadFile;

    $stmt = $conn->prepare("INSERT INTO parking_records (plate, containscar, containsvisitor, containsnumber, result, uploadFile, phototime, `checksum`, `uuid`) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    error_log($conn->error);
    $stmt->bind_param("sssssssss", $plate, $containscar, $containsvisitor, $containsnumber, $result, $uploadFile, $phototime, $checksum, $uuid);
 

}  elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {


    // Handle GET request (new functionality)
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        die("Error: 'id' parameter is required.");
    }

    $id = intval($_GET['id']);

    // Fetch the photo file based on the provided "id"
    $sql = "SELECT uploadFile FROM parking_records WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Error: No record found with the provided 'id'.");
    }

    $row = $result->fetch_assoc();
    $imagePath = $row['uploadFile'];

    if (!file_exists($imagePath)) {
        die("Error: Photo file does not exist.");
    }

    extract(extractPlateFromImageFile($imagePath));

    $stmt = $conn->prepare("UPDATE parking_records set plate = ?, containscar = ?, containsvisitor = ?, containsnumber = ?, result = ?, `checksum`=?, timestamp=timestamp where id=?");
    $stmt->bind_param("sssssss", $plate, $containscar, $containsvisitor, $containsvisitor,  $result, $checksum, $id);
}
/* Post codition - $imageFile is what we are processing, $stmt is SQL update to process */

// Execute the statement
if ($stmt->execute()) {
    echo "<h2>$result</h2>";
} else {
    error_log($stmt->error);
    echo "Error: " . $stmt->error;
}

// Close statement and connection
$stmt->close();


// Prepare the SQL query
$sql = "SELECT uploadFile, timestamp FROM parking_records WHERE plate = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $plate);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML table
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

$conn->close();
