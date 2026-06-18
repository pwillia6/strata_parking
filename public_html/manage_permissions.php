<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Parking - Manage Visitor Permissions</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container" style="text-align:left">

<?php
include 'nav.php';

$conn = Database::getConnection();

// Handle form submissions and GET requests
$message = '';
$plate_value = '';
$unitnumber_value = '';
$email_value = '';
$is_permitted_value = false;
$expiry_date_value = '';
$is_resident_vehicle_value = false;

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plate'])) {
    $plate_to_delete = trim($_POST['delete_plate']);
    if ($plate_to_delete !== '') {
        $stmt_delete = $conn->prepare("DELETE FROM permission WHERE plate = ?");
        $stmt_delete->bind_param("s", $plate_to_delete);
        if ($stmt_delete->execute()) {
            $message = "<p style='color:green;'>Permission for plate " . htmlspecialchars($plate_to_delete) . " has been deleted.</p>";
        } else {
            $message = "<p style='color:red;'>Failed to delete permission: " . $stmt_delete->error . "</p>";
        }
        $stmt_delete->close();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plate'])) {
    if (trim($_POST['plate']) !== '') {
        $plate = trim($_POST['plate']);
        $unitnumber = trim($_POST['unitnumber']);
        $email = trim($_POST['email']);
        $visitor_permitted = isset($_POST['visitor_permitted']) ? 'YES' : 'NO';
        $expiry_date = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
        $is_resident_vehicle = isset($_POST['is_resident_vehicle']);

        $plate_value = htmlspecialchars($plate); // For repopulating the form
        $unitnumber_value = htmlspecialchars($unitnumber);
        $email_value = htmlspecialchars($email);
        $is_permitted_value = ($visitor_permitted === 'YES');
        $expiry_date_value = $expiry_date;
        $is_resident_vehicle_value = $is_resident_vehicle;

        // --- Check and insert/update 'survey' table if resident vehicle is checked ---
        if ($is_resident_vehicle) {
            if (!empty($unitnumber)) {
                // Check if a record already exists for this plate in 'survey' table
                $stmt_survey_check = $conn->prepare("SELECT id FROM survey WHERE plate = ?");
                $stmt_survey_check->bind_param("s", $plate);
                $stmt_survey_check->execute();
                $result_survey_check = $stmt_survey_check->get_result();

                if ($result_survey_check->num_rows > 0) {
                    // Update existing record
                    $stmt_survey_update = $conn->prepare("UPDATE survey SET unitnumber = ? WHERE plate = ?");
                    $stmt_survey_update->bind_param("is", $unitnumber, $plate);
                    $stmt_survey_update->execute();
                    $stmt_survey_update->close();
                } else {
                    // If no record exists, insert a new one
                    $survey_result_text = 'Manual';
                    $survey_uploadFile = '';
                    $survey_checksum = '';
                    $survey_phototime = null;
                    $survey_uuid = null;

                    $stmt_survey_insert = $conn->prepare("INSERT INTO survey (plate, unitnumber, result, uploadFile, checksum, phototime, uuid) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt_survey_insert) {
                        $stmt_survey_insert->bind_param("sisssss", $plate, $unitnumber, $survey_result_text, $survey_uploadFile, $survey_checksum, $survey_phototime, $survey_uuid);
                        $stmt_survey_insert->execute();
                        $stmt_survey_insert->close();
                    }
                }
                $stmt_survey_check->close();
            } else {
                $message .= "<p style='color:red;'>Unit number is required to mark a vehicle as resident-owned.</p>";
            }
        } else {
            // If checkbox is unchecked, remove from survey if it was manually added.
            $stmt_survey_delete = $conn->prepare("DELETE FROM survey WHERE plate = ? AND result = 'Manual'");
            $stmt_survey_delete->bind_param("s", $plate);
            $stmt_survey_delete->execute();
            $stmt_survey_delete->close();
        }

        // Check if a record already exists for this plate
        $stmt_check = $conn->prepare("SELECT id, visitor_permitted FROM permission WHERE plate = ?");
        $stmt_check->bind_param("s", $plate);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Update existing record
            $stmt_update = $conn->prepare("UPDATE permission SET visitor_permitted = ?, expiry_date = ?, unitnumber = ?, email = ? WHERE plate = ?");
            $stmt_update->bind_param("sssss", $visitor_permitted, $expiry_date, $unitnumber, $email, $plate);
            if ($stmt_update->execute()) {
                $message = "<p style='color:green;'>Visitor permission updated successfully for plate: " . htmlspecialchars($plate) . ".</p>";
            } else {
                $message = "<p style='color:red;'>Failed to update visitor permission: " . $stmt_update->error . "</p>";
            }
            $stmt_update->close();
        } else {
            // Insert new record
            $stmt_insert = $conn->prepare("INSERT INTO permission (plate, unitnumber, email, visitor_permitted, expiry_date) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssss", $plate, $unitnumber, $email, $visitor_permitted, $expiry_date);
            if ($stmt_insert->execute()) {
                $message = "<p style='color:green;'>Visitor permission set successfully for plate: " . htmlspecialchars($plate) . ".</p>";
            } else {
                $message = "<p style='color:red;'>Failed to set visitor permission: " . $stmt_insert->error . "</p>";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    } else {
        $message = "<p style='color:red;'>Plate number cannot be empty.</p>";
    }
} elseif (isset($_GET['plate']) && trim($_GET['plate']) !== '') { // Allow pre-filling via GET
    $plate_to_load = trim($_GET['plate']);
    $plate_value = htmlspecialchars($plate_to_load);
    $stmt_load = $conn->prepare("SELECT visitor_permitted, expiry_date, unitnumber, email FROM permission WHERE plate = ?");
    $stmt_load->bind_param("s", $plate_to_load);
    $stmt_load->execute();
    $result_load = $stmt_load->get_result();
    if ($row_load = $result_load->fetch_assoc()) {
        $is_permitted_value = ($row_load['visitor_permitted'] === 'YES');
        $expiry_date_value = $row_load['expiry_date'];
        $unitnumber_value = htmlspecialchars($row_load['unitnumber']);
        $email_value = htmlspecialchars($row_load['email']);
    }
    $stmt_load->close();

    // Check survey table to pre-check the resident vehicle checkbox
    $stmt_survey_check = $conn->prepare("SELECT id FROM survey WHERE plate = ?");
    $stmt_survey_check->bind_param("s", $plate_to_load);
    $stmt_survey_check->execute();
    if ($stmt_survey_check->get_result()->num_rows > 0) {
        $is_resident_vehicle_value = true;
    }
    $stmt_survey_check->close();
}

// --- Fetch and display permissions ---
$show_all = isset($_GET['show']) && $_GET['show'] === 'all';

if ($show_all) {
    $sql_list = "SELECT id, plate, unitnumber, email, visitor_permitted, expiry_date FROM permission ORDER BY plate ASC";
    $stmt_list = $conn->prepare($sql_list);
} else {
    // Default view: show active permissions only
    $sql_list = "SELECT id, plate, unitnumber, email, visitor_permitted, expiry_date FROM permission WHERE visitor_permitted = 'YES' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY plate ASC";
    $stmt_list = $conn->prepare($sql_list);
}

$permissions = [];
if ($stmt_list) {
    $stmt_list->execute();
    $permissions_result = $stmt_list->get_result();
    while($row = $permissions_result->fetch_assoc()) {
        $permissions[] = $row;
    }
    $stmt_list->close();
}

Database::close();
?>

<h1>Manage Visitor Permissions</h1>

<p><a href="permit.docx" download>Download Permit Template (.docx)</a></p>

<?php echo $message; ?>

<form method="POST" action="manage_permissions.php">
    <table style="width:auto;">
        <tr>
            <td><label for="plate">Plate Number:</label></td>
            <td><input type="text" id="plate" name="plate" value="<?php echo $plate_value; ?>" required></td>
        </tr>
        <tr>
            <td><label for="unitnumber">Unit Number:</label></td>
            <td><input type="text" id="unitnumber" name="unitnumber" value="<?php echo $unitnumber_value; ?>"></td>
        </tr>
        <tr>
            <td><label for="is_resident_vehicle">Vehicle is owned by resident:</label></td>
            <td><input type="checkbox" id="is_resident_vehicle" name="is_resident_vehicle" <?php echo $is_resident_vehicle_value ? 'checked' : ''; ?>></td>
        </tr>
        <tr>
            <td><label for="email">Email:</label></td>
            <td><input type="email" id="email" name="email" value="<?php echo $email_value; ?>"></td>
        </tr>
        <tr>
            <td><label for="visitor_permitted">Visitor Permitted:</label></td>
            <td><input type="checkbox" id="visitor_permitted" name="visitor_permitted" <?php echo $is_permitted_value ? 'checked' : ''; ?>></td>
        </tr>
        <tr>
            <td><label for="expiry_date">Expiry Date:</label></td>
            <td><input type="date" id="expiry_date" name="expiry_date" value="<?php echo htmlspecialchars($expiry_date_value); ?>"></td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:center;">
                <button type="submit" style="width:auto; padding: 10px 20px; font-size:1em; height: auto;">Update Permission</button>
            </td>
        </tr>
    </table>
</form>

<p><em>Enter a plate number to manage visitor parking permission. An empty expiry date means the permission does not expire.</em></p>

<hr style="margin: 20px 0;">

<button type="button" id="sendPermitsButton" style="float:right; font-size:1.2em; margin-bottom: 10px; padding: 5px 10px; width:auto; height:auto;">Send Permits</button>

<h2>
    <?php echo $show_all ? 'All Permissions History' : 'Active Permissions'; ?>
    <a href="manage_permissions.php?show=<?php echo $show_all ? 'active' : 'all'; ?>" style="float:right; font-size:0.8em; text-decoration:none;">
        <button type="button" style="width:auto; padding: 5px 10px; font-size:1em; height: auto;">
            <?php echo $show_all ? 'Show Active Only' : 'Show All History'; ?>
        </button>
    </a>
</h2>

<?php if (count($permissions) > 0): ?>
    <table border="1" style="width:100%;">
        <thead>
            <tr>
                <th style="width:1%" class="noexport">Send Permit</th>
                <th>Plate Number</th>
                <th>Unit Number</th>
                <th>Email</th>
                <th>Visitor Permitted</th>
                <th>Expiry Date</th>
                <th class="noexport">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permissions as $perm): ?>
                <?php
                    // A permission is active if it's for a visitor and not expired.
                    $is_active = ($perm['visitor_permitted'] === 'YES' && (!$perm['expiry_date'] || $perm['expiry_date'] >= date('Y-m-d')));
                    $disable_permit_checkbox = $is_active ? '' : 'disabled';
                ?>
                <tr>
                    <td class="noexport"><input type="checkbox" class="permit-checkbox" name="permits[]" value="<?php echo htmlspecialchars(http_build_query($perm)); ?>" <?php echo $disable_permit_checkbox; ?>></td>
                    <td><?php echo htmlspecialchars($perm['plate']); ?></td>
                    <td><?php echo htmlspecialchars($perm['unitnumber']); ?></td>
                    <td><?php echo htmlspecialchars($perm['email']); ?></td>
                    <td><?php echo htmlspecialchars($perm['visitor_permitted']); ?></td>
                    <td><?php echo htmlspecialchars($perm['expiry_date'] ? date('d-m-Y', strtotime($perm['expiry_date'])) : 'Never'); ?></td>
                    <td style="text-align:center; white-space:nowrap;">
                        <a href="manage_permissions.php?plate=<?php echo urlencode($perm['plate']); ?>">Edit</a>
                        &nbsp;|&nbsp;
                        <form method="POST" action="manage_permissions.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete the permission for plate <?php echo htmlspecialchars($perm['plate']); ?>?');">
                            <input type="hidden" name="delete_plate" value="<?php echo htmlspecialchars($perm['plate']); ?>">
                            <button type="submit" style="background:none; border:none; color: #4e54c8; text-decoration:underline; cursor:pointer; padding:0; font-size:1em; width:auto; height:auto;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No permissions found for this view.</p>
<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const plateInput = document.getElementById('plate');
    const permissionCheckbox = document.getElementById('visitor_permitted');
    const unitnumberInput = document.getElementById('unitnumber');
    const emailInput = document.getElementById('email');
    const expiryDateInput = document.getElementById('expiry_date');

    plateInput.addEventListener('blur', function() {
        const plateValue = this.value.trim();
        if (plateValue === '') {
            // Optionally clear checkbox if plate is empty or handle as needed
            // permissionCheckbox.checked = false; 
            return;
        }

        fetchPermissionStatus(plateValue);
    });

    function fetchPermissionStatus(plate) {
        const xhr = new XMLHttpRequest();
        xhr.open("GET", "update_permission.php?plate=" + encodeURIComponent(plate), true);
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === "success") {
                            permissionCheckbox.checked = (response.visitor_permitted === 'YES');
                            expiryDateInput.value = response.expiry_date || '';
                            unitnumberInput.value = response.unitnumber || '';
                            emailInput.value = response.email || '';
                        } else {
                            console.error("Error fetching permission: " + (response.message || "Unknown error"));
                            // Optionally, uncheck or set to a default state on error
                            // permissionCheckbox.checked = false; 
                        }
                    } catch (e) {
                        console.error("Error parsing server response for permission status: ", e);
                    }
                } else {
                    console.error("Failed to fetch permission status. Server responded with status: " + xhr.status);
                }
            }
        };
        xhr.send();
    }

    // --- SCRIPT FOR SENDING PERMITS ---
    
    // Logic for the "Select All" checkbox
    const selectAllCheckbox = document.getElementById('selectAllPermits');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function(e) {
            const isChecked = e.target.checked;
            const checkboxes = document.querySelectorAll('.permit-checkbox');
            for (let i = 0; i < checkboxes.length; i++) {
                // Only check visible and not-disabled checkboxes
                const row = checkboxes[i].closest('tr');
                if (row && row.style.display !== 'none' && !checkboxes[i].disabled) {
                    checkboxes[i].checked = isChecked;
                }
            }
        });
    }

    // Handle the "Send Permits" button click
    const sendButton = document.getElementById('sendPermitsButton');
    if (sendButton) {
        sendButton.addEventListener('click', function() {
            const selectedPermits = [];
            const checkboxes = document.querySelectorAll('.permit-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one active permit to send.');
                return;
            }

            for (let i = 0; i < checkboxes.length; i++) {
                selectedPermits.push(checkboxes[i].value);
            }

            const originalButtonText = sendButton.textContent;
            sendButton.textContent = 'Processing...';
            sendButton.disabled = true;

            const xhr = new XMLHttpRequest();
            xhr.open("POST", "send_permit.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    // Restore button state
                    sendButton.textContent = originalButtonText;
                    sendButton.disabled = false;
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        alert(response.message || "An unknown response was received from the server.");
                    } catch (e) {
                        alert("An error occurred while processing the server's response.");
                        console.error("Server Response: ", xhr.responseText);
                    }
                }
            };

            // Send the selected permits as a JSON string
            const params = "permits=" + encodeURIComponent(JSON.stringify(selectedPermits));
            xhr.send(params);
        });
    }
});
</script>
</body>
</html>