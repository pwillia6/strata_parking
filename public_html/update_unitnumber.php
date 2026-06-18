<?php


header('Content-Type: application/json');

try {
    // ----- MySQLi Connection -----
    $mysqli = Database::getConnection();

    // ----- Grab the POST data -----
    $plate          = isset($_POST['plate']) ? $_POST['plate'] : '';
    $oldUnitNumber  = isset($_POST['old_unitnumber']) ? $_POST['old_unitnumber'] : '';
    $newUnitNumber  = isset($_POST['new_unitnumber']) ? $_POST['new_unitnumber'] : '';

    // Validate required fields
    if (!$plate || !$newUnitNumber) {
        throw new Exception("Missing required parameters (plate/new_unitnumber).");
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
            throw new Exception("Failed to prepare INSERT statement.");
        }
        
        $stmt->bind_param("ss", $plate, $newUnitNumber);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(["status" => "success"]);
        } else {
            throw new Exception("Insert failed (duplicate or other DB error).");
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
            throw new Exception("Failed to prepare UPDATE statement.");
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
                throw new Exception("Failed to prepare INSERT statement after no update.");
            }
            
            $stmt2->bind_param("ss", $plate, $newUnitNumber);
            $stmt2->execute();
            
            if ($stmt2->affected_rows > 0) {
                // Inserted successfully
                echo json_encode(["status" => "success"]);
            } else {
                // Insert also failed
                throw new Exception("No existing record found and insert also failed.");
            }
            $stmt2->close();
        }
        
        // Close the initial UPDATE statement if still open
        if ($stmt) {
            $stmt->close();
        }
    }

} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}

// Close DB
Database::close();