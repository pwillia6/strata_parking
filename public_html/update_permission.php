<?php
header('Content-Type: application/json');

// It's good practice to include your login/authentication script if this endpoint needs protection.

// Database connection details (consider moving to a shared config file)
$host = "localhost";
$user = "root";
$password = "";
$dbname = "parking";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plate']) && array_key_exists('permitted', $_POST)) {
    $plate = trim($_POST['plate']);
    // Convert JavaScript boolean 'true'/'false' string to 'YES'/'NO' for ENUM
    $visitor_permitted = ($_POST['permitted'] === 'true' || $_POST['permitted'] === true) ? 'YES' : 'NO';
    // Handle expiry date. If not provided or empty, set to NULL.
    $expiry_date = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;

    if (empty($plate)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Plate cannot be empty.']);
        exit;
    }

    // Check if a record already exists for this plate
    $stmt_check = $conn->prepare("SELECT id FROM permission WHERE plate = ?");
    $stmt_check->bind_param("s", $plate);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Update existing record
        $stmt_update = $conn->prepare("UPDATE permission SET visitor_permitted = ?, expiry_date = ? WHERE plate = ?");
        $stmt_update->bind_param("sss", $visitor_permitted, $expiry_date, $plate);
        if ($stmt_update->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Visitor permission updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to update visitor permission: ' . $stmt_update->error]);
        }
        $stmt_update->close();
    } else {
        // Insert new record
        $stmt_insert = $conn->prepare("INSERT INTO permission (plate, visitor_permitted, expiry_date) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $plate, $visitor_permitted, $expiry_date);
        if ($stmt_insert->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Visitor permission set successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to set visitor permission: ' . $stmt_insert->error]);
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['plate'])) {
    $plate = trim($_GET['plate']);

    if (empty($plate)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Plate cannot be empty for GET request.']);
        exit;
    }

    $stmt_get = $conn->prepare("SELECT visitor_permitted, expiry_date, unitnumber, email FROM permission WHERE plate = ?");
    $stmt_get->bind_param("s", $plate);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();

    if ($row_get = $result_get->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'visitor_permitted' => $row_get['visitor_permitted'], 'expiry_date' => $row_get['expiry_date'], 'unitnumber' => $row_get['unitnumber'], 'email' => $row_get['email']]);
    } else {
        // If no record, assume not permitted (or you can return a specific status)
        echo json_encode(['status' => 'success', 'visitor_permitted' => 'NO', 'expiry_date' => null, 'unitnumber' => '', 'email' => '']);
    }
    $stmt_get->close();

} else {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid request. Missing required parameters.']);
}
$conn->close();
?>