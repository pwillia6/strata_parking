<?php

require_once __DIR__ . '/../lib/config.php';

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
    $query = "What is the number plate of this vehicle in alphanumeric format?  Include only alphanumeric characters on the plate.   On the floor is a unit number which will be one for the following \"101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320,401,402,403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,418,501,502,503,504,505,506,507,508,509,510,511,512,513,514,515,516,517,518,519,520,601,602,603,604,605,606,607,608,609,610,611,612,613,614,701,702,703,704,705,706,707,708,709,710,711,712,713,714,715,716,801,802,803,804,805,806,807,808,809,810,811,812,813,814,815,816,901,902,903,904,905,906,907,1001,1002,1003,1004,1005,1006,1007,1008,001\"?   Extract the plat and unit number into JSON, format element \'plate\', \'unit_number\'";
    $apiUrl = "https://api.openai.com/v1/chat/completions";
    $payload = $payload = '{
        "model" : "gpt-4o",
        "messages": [
          {
            "role": "user",
            "content": [
              {
                "type": "image_url",
                "image_url": {
                  "url": "--IMAGE--"
                }
              },
              {
                "type": "text",
                "text": "What is the number plate of this vehicle in alphanumeric format?  Include only alphanumeric characters on the plate.   On the floor is a unit number which will be one for the following \"101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320,401,402,403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,418,501,502,503,504,505,506,507,508,509,510,511,512,513,514,515,516,517,518,519,520,601,602,603,604,605,606,607,608,609,610,611,612,613,614,701,702,703,704,705,706,707,708,709,710,711,712,713,714,715,716,801,802,803,804,805,806,807,808,809,810,811,812,813,814,815,816,901,902,903,904,905,906,907,1001,1002,1003,1004,1005,1006,1007,1008,001\"?   Extract the plat and unit number into JSON, format element \'plate\', \'unit_number\'"
              }
            ]
          }
        ],
        "response_format": {
          "type": "text"
        },
        "temperature": 1,
        "max_completion_tokens": 4095,
        "top_p": 1,
        "frequency_penalty": 0,
        "presence_penalty": 0
      }';
          $payload = str_replace('--IMAGE--',"data:image/jpeg;base64,$imageData", $payload);
 
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        // Execute and handle the response
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "Error: " . curl_error($ch) . "\n";
            exit(1);
        }
        curl_close($ch);

        // Decode and display the output
        //echo $response;
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
                 'unit_number' => $info->unit_number,
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
    
    $uploadDir = __DIR__ . '/../var/survey/';

    extract(extractPlateFromImageFile($_FILES['photo']['tmp_name']));
    $phototime = $_POST['phototime'];
    $uuid = isset($_POST['uuid']) ? $_POST['uuid'] : null;

    // Generate a unique filename based on the current time
    $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $uniqueFileName = $plate . ' ' . $phototime . '.' . $fileExtension;
    $uploadFile = $uploadDir . $uniqueFileName;

    // Ensure the upload directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Validate file type
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(['_error' => 'Invalid file type']);
        exit;
    }

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
        echo "{ '_error' : 'Invalid Call A' }";
        exit;
    }
    $imagePath = $uploadFile;

    

    $stmt = $conn->prepare("INSERT INTO survey (plate, unitnumber, result, uploadFile, phototime, `checksum`, `uuid`) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
    error_log($conn->error);
    $stmt->bind_param("sssssss", $plate, $unit_number, $result, $uploadFile, $phototime, $checksum, $uuid);
 

}  elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {


    // Handle GET request (new functionality)
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        die("Error: 'id' parameter is required.");
    }

    $id = intval($_GET['id']);

    // Fetch the photo file based on the provided "id"
    $sql = "SELECT uploadFile FROM survey WHERE id = ?";
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

    $stmt = $conn->prepare("UPDATE survey set plate = ?, unitnumber=?,  result = ?, `checksum`=?, timestamp=timestamp where id=?");
    $stmt->bind_param("sssss", $plate, $unit_number,  $result, $checksum, $id);
}
/* Post codition - $imageFile is what we are processing, $stmt is SQL update to process */

// Execute the statement
if ($stmt->execute()) {
    echo "<h2>$result</h2>";
} else {
    echo "Error: " . $stmt->error;
}

// Close statement and connection
$stmt->close();


// Prepare the SQL query
$sql = "SELECT uploadFile, unitnumber FROM survey WHERE plate = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $plate);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML table
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

        // Convert UTC timestamp to Australia/Sydney time zone
  

        echo "<tr>";
        echo "<td><img src='data:image/jpeg;base64," . htmlspecialchars($base64Image) . "' alt='Photo' width='100'></td>";
        echo "<td>" . htmlspecialchars($row['unitnumber']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "No records found for plate: " . htmlspecialchars($plate);
}

$conn->close();
