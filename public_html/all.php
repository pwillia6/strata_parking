<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Parking - All photos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        #editModal, #editNoticeModal {
            display: none;
            position: fixed;
            top: 20%;
            left: 40%;
            width: 300px;
            background-color: #fff;
            border: 2px solid #333;
            padding: 15px;
            z-index: 9999;
        }
        #modalOverlay {
            display: none;
            position: fixed;
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.3);
            z-index: 9998;
        }
        .pagination {
            clear: both;
            text-align: center;
            padding: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #4e54c8;
        }
        .pagination .current {
            background-color: #4e54c8;
            color: white;
            border-color: #4e54c8;
        }
        #loadingOverlay {
            display: flex; /* Initially visible */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8); /* Semi-transparent white */
            z-index: 10000; /* Higher than other modals to ensure it's on top */
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Center content vertically */
            font-size: 2em; /* Larger text for visibility */
            color: #4e54c8; /* Match accent color */
        }
    </style>
    <script>
        function openEditModal(id, plate, containsnumber, containscar, containsvisitor) {
            // Fill the form fields with the row data
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-plate').value = plate;
            
            // Assign "Yes"/"No" to the selects
            document.getElementById('edit-containsnumber').value = containsnumber;
            document.getElementById('edit-containscar').value = containscar;
            document.getElementById('edit-containsvisitor').value = containsvisitor;

            // Show the overlay and the modal
            document.getElementById('modalOverlay').style.display = 'block';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            // Hide the overlay and the modal
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('editModal').style.display = 'none';
        }

        function openEditNoticeModal(id, noticepinned) {
            // Fill the form fields with the notice data
            document.getElementById('edit-notice-id').value = id;
            document.getElementById('edit-noticepinned').value = noticepinned;

            // Show the overlay and the modal
            document.getElementById('modalOverlay').style.display = 'block';
            document.getElementById('editNoticeModal').style.display = 'block';
        }

        function closeEditNoticeModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('editNoticeModal').style.display = 'none';
        }

        // Hide the loading overlay when the DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('loadingOverlay').style.display = 'none';
        });
    </script>
</head>
<body>
<?php

/**
 * Renders pagination links.
 *
 * @param int $current_page The current page number.
 * @param int $total_pages  The total number of pages.
 * @param array $params     An array of GET parameters to preserve in the links.
 */
function render_pagination($current_page, $total_pages, $params = []) {
    if ($total_pages > 1) {
        echo '<div class="pagination">';

        // Previous button
        if ($current_page > 1) {
            $params['page'] = $current_page - 1;
            echo '<a href="?' . http_build_query($params) . '">&laquo; Previous</a>';
        }

        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            $params['page'] = $i;
            if ($i == $current_page) {
                echo "<span class=\"current\">{$i}</span>";
            } else {
                echo '<a href="?' . http_build_query($params) . '">' . $i . '</a>';
            }
        }

        // Next button
        if ($current_page < $total_pages) {
            $params['page'] = $current_page + 1;
            echo '<a href="?' . http_build_query($params) . '">Next &raquo;</a>';
        }
        echo '</div>';
    }
}
?>

<!-- Loading Overlay - Visible until DOM is loaded -->
<div id="loadingOverlay">Loading...</div>

<?php
include 'nav.php';

// Add to database 
// Create a connection to MySQL
$host = "localhost";
$user = "root";
$password = "";
$dbname = "parking";

$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Only show the search form if we are not viewing a specific record by ID
if (!isset($_GET['id'])) {
    // Get current filter values to pre-fill the form
    $search_plate = isset($_GET['plate']) ? htmlspecialchars($_GET['plate']) : '';
    $start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '';
    $notice_only_checked = isset($_GET['notice_only']) && $_GET['notice_only'] == '1' ? 'checked' : '';
?>
    <form method="GET" action="all.php" style="padding: 10px; background: white; border-radius: 5px; margin-bottom: 20px;">
        <label for="search_plate">Plate:</label>
        <input type="text" name="plate" id="search_plate" value="<?php echo $search_plate; ?>" style="width: 150px; padding: 5px;">
        <label for="start_date" style="margin-left: 10px;">From:</label>
        <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" style="padding: 5px;">
        <label for="end_date" style="margin-left: 10px;">To:</label>
        <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" style="padding: 5px;">
        <input type="checkbox" name="notice_only" id="notice_only" value="1" <?php echo $notice_only_checked; ?> style="margin-left: 10px; vertical-align: middle;"> <label for="notice_only" style="vertical-align: middle;">Notices Only</label>
        <button type="submit" style="width: auto; height: auto; padding: 6px 12px; margin-left: 10px;">Search</button>
        <a href="all.php" style="display: inline-block; text-decoration: none; background-color: #6c757d; color: white; padding: 7px 12px; border-radius: 3px; font-size: 13.333px; margin-left: 5px; vertical-align: middle; border: 1px solid #6c757d;">Reset</a>
    </form>
<?php
}



/****************************************************
 * 2) UPDATE RECORD IF FORM WAS SUBMITTED
 ****************************************************/
if (isset($_POST['update'])) {
    // Retrieve posted form data
    $id             = (int) $_POST['id'];  // Cast to int for safety
    $plate          = $_POST['plate'];
    $containsnumber = $_POST['containsnumber'];
    $containscar    = $_POST['containscar'];
    $containsvisitor= $_POST['containsvisitor'];

    // Build a prepared UPDATE query
    $stmt = $conn->prepare("
        UPDATE parking_records
           SET plate           = ?,
               containsnumber  = ?,
               containscar     = ?,
               containsvisitor = ?
         WHERE id = ?
    ");
    if (!$stmt) {
        die("Prepare failed: " . $connection->error);
    }

    // Bind parameters: s=string, i=integer. Our fields: plate(str), containsnum(str), containscar(str), containsvisitor(str), id(int)
    $stmt->bind_param("ssssi", $plate, $containsnumber, $containscar, $containsvisitor, $id);

    // Execute statement
    if (!$stmt->execute()) {
        die("Update failed: " . $stmt->error);
    }
    $stmt->close();
}

/****************************************************
 * 2b) UPDATE NOTICE RECORD IF FORM WAS SUBMITTED
 ****************************************************/
if (isset($_POST['update_notice'])) {
    // Retrieve posted form data
    $id           = (int) $_POST['notice_id'];
    $noticepinned = $_POST['noticepinned'];

    // Build a prepared UPDATE query for the notice table
    $stmt = $conn->prepare("
        UPDATE notice
           SET noticepinned = ?
         WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("si", $noticepinned, $id);
        $stmt->execute();
        $stmt->close();
    }
}

/****************************************************
 * 3) PREPARE THE SQL QUERY
 ****************************************************/
$parking_sql = "SELECT 
                    'Visitor' as record_type,
                    id, 
                    plate, 
                    uploadFile, 
                    containsvisitor, 
                    containsnumber, 
                    containscar, 
                    NULL as noticepinned,
                    phototime,
                    CONVERT_TZ(phototime, '+00:00', 'Australia/Sydney') as local_phototime 
                FROM parking_records";

$notice_sql = "SELECT 
                    'Notice' as record_type,
                    id, 
                    plate, 
                    uploadFile, 
                    NULL as containsvisitor, 
                    NULL as containsnumber, 
                    NULL as containscar, 
                    noticepinned,
                    phototime,
                    CONVERT_TZ(phototime, '+00:00', 'Australia/Sydney') as local_phototime 
                FROM notice";

$params = [];
$types = '';
$where_clause = '';
$where_conditions = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // This ID could be from either table, so we need to check both.
    // If an ID is provided, find the plate for that ID first.
    $id = (int)$_GET['id'];
    $stmt_plate = $conn->prepare("SELECT plate FROM (SELECT id, plate FROM parking_records UNION ALL SELECT id, plate FROM notice) AS combined WHERE id = ?");
    $stmt_plate->bind_param("i", $id);
    $stmt_plate->execute();
    $result_plate = $stmt_plate->get_result();
    if ($row_plate = $result_plate->fetch_assoc()) {
        // Now use the found plate to get all records for that vehicle.
        $where_conditions[] = "plate = ?";
        $params[] = $row_plate['plate'];
        $types .= 's';
    }
    $stmt_plate->close();
} else {
    // Handle search form filters if no ID is provided
    if (!empty($_GET['plate'])) {
        $where_conditions[] = "plate LIKE ?";
        $params[] = '%' . $_GET['plate'] . '%';
        $types .= 's';
    }
    if (!empty($_GET['start_date'])) {
        $where_conditions[] = "phototime >= ?";
        $params[] = $_GET['start_date'] . ' 00:00:00';
        $types .= 's';
    }
    if (!empty($_GET['end_date'])) {
        $where_conditions[] = "phototime <= ?";
        $params[] = $_GET['end_date'] . ' 23:59:59';
        $types .= 's';
    }
}

if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(' AND ', $where_conditions);
}

// --- BASE QUERY LOGIC ---
if (isset($_GET['notice_only']) && $_GET['notice_only'] == '1') {
    $base_sql = "($notice_sql)";
} else {
    $base_sql = "(($parking_sql) UNION ALL ($notice_sql))";
}


// --- PAGINATION LOGIC ---
$records_per_page = 99;

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM {$base_sql} as combined_records {$where_clause}";
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_records / $records_per_page);

$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $records_per_page;

$sql = "SELECT * FROM {$base_sql} as combined_records {$where_clause} ORDER BY phototime DESC";

// Apply pagination limit
$sql .= " LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii'; // Add two integers for LIMIT and OFFSET

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// --- RENDER PAGINATION LINKS (TOP) ---
$query_params = [];
if (isset($_GET['id'])) $query_params['id'] = $_GET['id'];
if (isset($_GET['plate'])) $query_params['plate'] = $_GET['plate'];
if (isset($_GET['start_date'])) $query_params['start_date'] = $_GET['start_date'];
if (isset($_GET['end_date'])) $query_params['end_date'] = $_GET['end_date'];
if (isset($_GET['notice_only'])) $query_params['notice_only'] = $_GET['notice_only'];

render_pagination($current_page, $total_pages, $query_params);

// Generate HTML table
if ($result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {
        $filePath = $row['uploadFile'];
        $link_start = '';
        $link_end = '';

        if (!empty($filePath) && file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            $base64Image = base64_encode($fileData);
            $imageSrc = "data:image/jpeg;base64," . htmlspecialchars($base64Image);
            $link_start = "<a class=\"vehicle\" target=\"_blank\" href=\"download.php?file=$filePath\">";
            $link_end = "</a>";
        } else {
        }

        $formattedPhototime = $row['local_phototime'];

        echo '<div class="offence">';
        echo $link_start . "<img src='" . $imageSrc . "' alt='Photo'>" . $link_end;
        echo "<div class=\"timestamp\">". htmlspecialchars($formattedPhototime) . "</div>";
        
        if ($row['record_type'] === 'Visitor') {
            echo '<div style="height:3.2em; font-size: 0.9em; text-align:center;">' . '<b>' . $row['record_type'] . '</b><br>Vistor: ' . $row['containsvisitor'] . '&nbsp;|&nbsp;Number: ' . $row['containsnumber'] . '<br>Shared: ' . $row['containscar'] .   '</div>';
        } else {
            echo '<div style="height:3.2em; font-size: 0.9em; text-align:center;"><b>' . $row['record_type'] . '</b><br>Notice Pinned: ' . htmlspecialchars($row['noticepinned']) . '<br>&nbsp;</div>';
        }

        // Only show the edit button for 'Visitor' type records
        if ($row['record_type'] === 'Visitor') {

        ?><button
            class="edit-btn"
            onclick="openEditModal(
                <?php echo (int)$row['id']; ?>,
                '<?php echo addslashes($row['plate']); ?>',
                '<?php echo addslashes($row['containsnumber']); ?>',
                '<?php echo addslashes($row['containscar']); ?>',
                '<?php echo addslashes($row['containsvisitor']); ?>'
            )"
        >
            Edit
        </button><?
        } else {
            // For 'Notice' type, show its own edit button
            ?><button
                class="edit-btn"
                onclick="openEditNoticeModal(
                    <?php echo (int)$row['id']; ?>,
                    '<?php echo addslashes($row['noticepinned']); ?>'
                )"
            >
                Edit
            </button><?
        }
        echo '</div>';
    }

    echo "</table>";
} else {
    echo "No records found for the specified criteria.";
}

// --- RENDER PAGINATION LINKS ---
render_pagination($current_page, $total_pages, $query_params);
?>
<!-- Hidden modal overlay -->
<div id="modalOverlay"></div>

<!-- Hidden edit modal -->
<div id="editModal">
    <h2>Edit Car</h2>
    <form method="post" action="">
        <input type="hidden" name="id" id="edit-id">

        <p>
            <label>Plate:</label><br>
            <input type="text" name="plate" id="edit-plate">
        </p>

        <p>
            <label>Contains Number:</label><br>
            <select name="containsnumber" id="edit-containsnumber">
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </p>

        <p>
            <label>Contains Car:</label><br>
            <select name="containscar" id="edit-containscar">
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </p>

        <p>
            <label>Contains Visitor:</label><br>
            <select name="containsvisitor" id="edit-containsvisitor">
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </p>

        <button type="submit" name="update">Save</button>
        <button type="button" onclick="closeEditModal()">Cancel</button>
    </form>
</div>

<!-- Hidden notice edit modal -->
<div id="editNoticeModal">
    <h2>Edit Notice</h2>
    <form method="post" action="">
        <input type="hidden" name="notice_id" id="edit-notice-id">

        <p>
            <label>Notice Pinned:</label><br>
            <select name="noticepinned" id="edit-noticepinned">
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </p>

        <button type="submit" name="update_notice">Save</button>
        <button type="button" onclick="closeEditNoticeModal()">Cancel</button>
    </form>
</div>




<?

$conn->close();

?>
</body>
</html>
