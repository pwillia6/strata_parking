<?php


header('Content-Type: application/json');

// ----- MySQLi Connection -----
// Add to database 
// Create a connection to MySQL
$host = "localhost";
$user = "root";
$password = "";
$dbname = "parking";

$mysqli = new mysqli($host, $user, $password, $dbname);

if ($mysqli->connect_errno) {
    echo json_encode([
        "status"  => "error",
        "message" => "Connection failed: " . $mysqli->connect_error
    ]);
    exit;
}


// ----- Grab the POST data -----
$plate          = isset($_POST['plate']) ? $_POST['plate'] : '';
$oldUnitNumber  = isset($_POST['old_unitnumber']) ? $_POST['old_unitnumber'] : '';
$newUnitNumber  = isset($_POST['new_unitnumber']) ? $_POST['new_unitnumber'] : '';

// Validate required fields
if (!$plate || !$newUnitNumber) {
    echo json_encode([
        "status"  => "error",
        "message" => "Missing required parameters (plate/new_unitnumber)."
    ]);
    $mysqli->close();
    exit;
}

/**
 * If old_unitnumber is blank => Insert a new record.
 * Else => Try to update. If update fails (no record found), insert instead.
 */
if (trim($oldUnitNumber) === '') {
    // ----- INSERT a NEW RECORD -----
    $insertSql = "INSERT INTO vehicles (plate, unitnumber) VALUES (?, ?)";
    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) {
        echo json_encode([
            "status"  => "error",
            "message" => "Failed to prepare INSERT statement."
        ]);
        $mysqli->close();
        exit;
    }
    
    $stmt->bind_param("ss", $plate, $newUnitNumber);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "Insert failed (duplicate or other DB error)."
        ]);
    }
    $stmt->close();
    
} else {
    // ----- UPDATE EXISTING RECORD -----
    $updateSql = "UPDATE vehicles
                  SET unitnumber = ?
                  WHERE plate = ?
                    AND unitnumber = ?";
                    
    $stmt = $mysqli->prepare($updateSql);
    if (!$stmt) {
        echo json_encode([
            "status"  => "error",
            "message" => "Failed to prepare UPDATE statement."
        ]);
        $mysqli->close();
        exit;
    }
    
    $stmt->bind_param("sss", $newUnitNumber, $plate, $oldUnitNumber);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Successfully updated at least one row
        echo json_encode(["status" => "success"]);
    } else {
        // No record matched (plate + old_unitnumber) => create one
        $stmt->close();
        
        $insertSql = "INSERT INTO vehicles (plate, unitnumber) VALUES (?, ?)";
        $stmt2 = $mysqli->prepare($insertSql);
        if (!$stmt2) {
            echo json_encode([
                "status"  => "error",
                "message" => "Failed to prepare INSERT statement after no update."
            ]);
            $mysqli->close();
            exit;
        }
        
        $stmt2->bind_param("ss", $plate, $newUnitNumber);
        $stmt2->execute();
        
        if ($stmt2->affected_rows > 0) {
            // Inserted successfully
            echo json_encode(["status" => "success"]);
        } else {
            // Insert also failed
            echo json_encode([
                "status"  => "error",
                "message" => "No existing record found and insert also failed."
            ]);
        }
        $stmt2->close();
    }
    
    // Close the initial UPDATE statement if still open
    if ($stmt) {
        $stmt->close();
    }
}

// Close DB
$mysqli->close();