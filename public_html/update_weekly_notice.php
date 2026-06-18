<?php
header('Content-Type: application/json');

// In a real application, you would include your authentication script here.
// include 'login.php'; 

try {
    // Database connection
    $conn = Database::getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $plate = isset($_POST['plate']) ? trim($_POST['plate']) : '';
        $week_start_date = isset($_POST['week_start_date']) ? trim($_POST['week_start_date']) : '';
        // Convert 'true'/'false' string from JS to 1/0 for TINYINT
        $issued = (isset($_POST['issued']) && ($_POST['issued'] === 'true' || $_POST['issued'] === true)) ? 1 : 0;

        if (empty($plate) || empty($week_start_date)) {
            http_response_code(400);
            throw new Exception('Plate and week start date are required.');
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing records.
        // This is more efficient than a SELECT followed by an INSERT or UPDATE.
        $sql = "INSERT INTO weekly_notices_issued (plate, week_start_date, issued, issued_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE issued = VALUES(issued), issued_at = VALUES(issued_at)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssi", $plate, $week_start_date, $issued);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Notice status updated.']);
            } else {
                throw new Exception('Failed to update notice status: ' . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
    } else {
        http_response_code(400);
        throw new Exception('Invalid request method.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
} finally {
    Database::close();
}
?>