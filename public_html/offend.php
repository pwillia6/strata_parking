<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Parking - Weekly Offenders</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Make the table header more compact */
        #offenders th {
            font-size: 0.8em; /* Reduce font size */
            padding: 2px 4px;   /* Reduce vertical and horizontal padding */
            text-align: center; /* Center-align header text */
        }
    </style>
</head>
<body>

<div class="container" style="text-align:left">

<?php
// PHP Version: 5.6
// MySQL Version: 5


include 'nav.php';

// Start a session to remember user preferences.
if (session_id() == '') {
    session_start();
}

// --- Determine 'days' and 'offset' parameters, using session for persistence ---

// Handle 'days' parameter
if (isset($_GET['days']) && is_numeric($_GET['days'])) {
    $days = (int)$_GET['days'];
    $_SESSION['offend_days'] = $days;
} elseif (isset($_SESSION['offend_days'])) {
    $days = $_SESSION['offend_days'];
} else {
    $days = 3; // Default value
}

$conn = Database::getConnection();

/**
 * Returns an array with local (Australia/Sydney) and UTC timestamps.
 */
function getLastFullWeekRange($offset = 0)
{
    $backDays = -$offset * 7 + 1;
    date_default_timezone_set('UTC');
    $dt = new DateTime('now', new DateTimeZone('Australia/Sydney'));
    $dow = (int) $dt->format('w');
    $dt->modify('-' . $dow . ' days');
    $dt->modify("$backDays days");
    $dt->setTime(0, 0, 0);
    $localStart = $dt->format('Y-m-d H:i:s');
    $dtGmt = clone $dt;
    $dtGmt->setTimezone(new DateTimeZone('UTC'));
    $utcStart = $dtGmt->format('Y-m-d H:i:s');
    $dt->modify('+7 days');
    $dt->modify('-1 seconds');
    $localEnd = $dt->format('Y-m-d H:i:s');
    $dtGmt = clone $dt;
    $dtGmt->setTimezone(new DateTimeZone('UTC'));
    $utcEnd = $dtGmt->format('Y-m-d H:i:s');
    $localRange = substr($localStart,0,10) . ' to ' .substr($localEnd,0,10);
    return compact('localStart', 'localEnd', 'utcStart', 'utcEnd', 'localRange');
}

$weeks = array();
for ($i=0; $i<20; $i++) {
    $weeks[$i] = getLastFullWeekRange($i);
}

// Handle 'offset' parameter
if (isset($_GET['offset']) && is_numeric($_GET['offset'])) {
    $offset = (int)$_GET['offset'];
    $_SESSION['offend_offset'] = $offset;
} elseif (isset($_SESSION['offend_offset'])) {
    $offset = $_SESSION['offend_offset'];
} else {
    $offset = 1; // Default value
}
extract(getLastFullWeekRange($offset));

if (!isset($_GET['id'])) {
$sql = "SELECT distinct * FROM (
    SELECT id, uploadFile, group_concat(ids) as ids, plate, count(1) as days, date('$localStart') as datefrom, date('$localEnd') as dateto, count(1) -  sum(containsnumber)  as `M284`, 
                      count(1)>2 as NoLongerVisitor, 
                      sum(nophotos) -  sum(containsnumber) as `M281`, sum(containscar) as `M283`, sum(containsnumber) as `M287`, sum(allphotos) as allphotos, max(lastphoto) as lastphoto,
                      group_concat(firstphoto order by firstphoto) as firstphotos from (
        SELECT max(id) as id, uploadFile, group_concat(id) as ids, plate, count(1) as nophotos,
               min(convert_tz(phototime,'+00:00', 'Australia/Sydney')) as firstphoto, 
               max(convert_tz(phototime,'+00:00', 'Australia/Sydney')) as lastphoto, 
               date(convert_tz(phototime,'+00:00', 'Australia/Sydney')) as photodate, 
               count(1) as allphotos, 
               count(if (containscar = 'Yes', 'YES', null)) as containscar, 
               count(if (containsnumber = 'Yes', 'YES', null)) as containsnumber 
          FROM parking_records 
         WHERE phototime BETWEEN  '$utcStart' and '$utcEnd'  
         GROUP BY plate, photodate
) al GROUP BY plate
) al2";

$sql = "
        SELECT s.plate as SurveyPlate, s.uploadFile as surveyUploadFile, s.unitnumber as SurveyUnitNumber, pr.* FROM (
        $sql 
        ) pr LEFT JOIN survey s on pr.plate=s.plate group by pr.plate";

$sql = "
        SELECT if (v.unitnumber is not null, v.unitnumber, al.SurveyUnitNumber) as unitnumber, al.* from (
            $sql
        ) al LEFT JOIN vehicles v on al.plate=v.plate";

$sql = "SELECT IF(NoLongerVisitor OR (al2.unitnumber IS NOT NULL AND al2.unitnumber REGEXP '^[0-9]+$'), 'Violated', '') as `M286`, al2.* from (
            $sql
        ) al2";

$sql = "SELECT * from ($sql) al3 where days >= $days or `M286` = 'Violated'  order by lastphoto desc;";


    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    // --- NEW CODE BLOCK: Store results and get all-time visitor counts ---
    $offender_rows = array();
    $plates = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $offender_rows[] = $row;
            // Collect plates to query their total counts
            if (!in_array($row['plate'], $plates)) {
                $plates[] = $row['plate'];
            }
        }
    }

    $visitor_counts = array(); // Counts from parking_records
    $notice_issue_counts = array(); // Counts from notice table
    if (!empty($plates)) {
        // Create a string of placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($plates), '?'));
        $count_sql = "SELECT plate, COUNT(1) as count FROM parking_records WHERE plate IN ($placeholders) GROUP BY plate";
        
        $stmt_count = $conn->prepare($count_sql);
        
        // Dynamically bind parameters
        $types = str_repeat('s', count($plates));
        $stmt_count->bind_param($types, ...$plates);
        
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        
        while ($count_row = $count_result->fetch_assoc()) {
            $visitor_counts[$count_row['plate']] = $count_row['count'];
        }
        $stmt_count->close();

        // --- NEW: Fetch notice issue counts ---
        $notice_count_sql = "SELECT plate, COUNT(1) as count, SUM(phototime BETWEEN '$utcStart' AND '$utcEnd') as week_count  
                               FROM notice 
                              WHERE plate IN ($placeholders) AND noticepinned = 'Yes' 
                              GROUP BY plate";
        
        $stmt_count = $conn->prepare($notice_count_sql);

        // Bind parameters (same plates, same types)
        $stmt_count->bind_param($types, ...$plates);
        $stmt_count->execute();
        $notice_count_result = $stmt_count->get_result();

        while ($notice_row = $notice_count_result->fetch_assoc()) {
            $notice_issue_counts[$notice_row['plate']] = ($notice_row['week_count'] > 0 ? '<span style="color:#c00; font-weight:bold">' . $notice_row['week_count'] . '</span>' : 0) . '/' . $notice_row['count'];
        }
        $stmt_count->close();
    }
    
    // --- NEW: Query for photos in the last hour ---
    $recent_photos_last_hour = array();
    if (!empty($plates)) {
        $placeholders_recent = implode(',', array_fill(0, count($plates), '?'));
        $recent_sql = "SELECT plate, MAX(phototime) as recent_phototime 
                       FROM parking_records 
                       WHERE plate IN ($placeholders_recent) 
                         AND phototime >= NOW() - INTERVAL 1 HOUR 
                       GROUP BY plate";
        $stmt_recent = $conn->prepare($recent_sql);
        if ($stmt_recent) {
            $types_recent = str_repeat('s', count($plates));
            $stmt_recent->bind_param($types_recent, ...$plates);
            $stmt_recent->execute();
            $recent_result = $stmt_recent->get_result();
            while ($recent_row = $recent_result->fetch_assoc()) {
                $recent_photos_last_hour[$recent_row['plate']] = $recent_row['recent_phototime'];
            }
            $stmt_recent->close();
        }
    }
    // --- Fetch visitor permission status ---
    $permissions = array();
    if (!empty($plates)) {
        $placeholders_perm = implode(',', array_fill(0, count($plates), '?'));
        $perm_sql = "SELECT plate, visitor_permitted, expiry_date FROM permission WHERE plate IN ($placeholders_perm)";
        $stmt_perm = $conn->prepare($perm_sql);
        if ($stmt_perm) {
            $types_perm = str_repeat('s', count($plates));
            $stmt_perm->bind_param($types_perm, ...$plates);
            $stmt_perm->execute();
            $perm_result = $stmt_perm->get_result();
            while ($perm_row = $perm_result->fetch_assoc()) {
                 $permissions[$perm_row['plate']] = $perm_row;
            }
            $stmt_perm->close();
        } else {
            // Handle error in preparing statement if necessary
        }
    }

    // --- Fetch weekly notice issued status ---
    $weekly_notices_issued = array();
    if (!empty($plates)) {
        $week_start_date_sql = date('Y-m-d', strtotime($localStart));
        $wni_sql = "SELECT plate, issued, issued_at FROM weekly_notices_issued WHERE plate IN ($placeholders) AND week_start_date = ?";
        $stmt_wni = $conn->prepare($wni_sql);
        if ($stmt_wni) {
            $types_wni = str_repeat('s', count($plates)) . 's';
            $params_wni = array_merge($plates, [$week_start_date_sql]);
            $stmt_wni->bind_param($types_wni, ...$params_wni);
            $stmt_wni->execute();
            $wni_result = $stmt_wni->get_result();
            while ($wni_row = $wni_result->fetch_assoc()) {
                $weekly_notices_issued[$wni_row['plate']] = $wni_row;
            }
            $stmt_wni->close();
        }
    }
    // --- END NEW CODE BLOCK ---
    ?>
    <button style="float:right; font-size:2em" type="button" id="printNoticesButton">Print Notices</button>
    <h1>Weekly offenders: <form style="display:inline" method="GET">
    <select  style="display:inline; font-size:1em" name="offset" onchange="this.form.submit()">
        <? foreach ($weeks as $i => $week): ?>
            <option <?=$i==$offset ? 'selected' : ''?> value="<?=$i?>"><?=$week['localRange']?></option>
        <? endforeach ?>
    </select>
    <select  style="display:inline; font-size:1em" name="days" onchange="this.form.submit()">
        <? for($i=1; $i<=7; $i++): ?>
            <option <?=$i==$days ? 'selected' : ''?> value="<?=$i?>"><?=$i?> days</option>
        <? endfor ?>
    </select>
</form>
<span style="margin-left: 20px; font-size: 0.8em; font-weight: normal;">
    <label>
        <input type="checkbox" id="showViolationsOnlyCheckbox" style="width: auto; height: auto; vertical-align: middle;"> Show violations only
    </label>
</span>
</h1>
    <div class="row">
    <?
    if (count($offender_rows) > 0) {
        echo "<div class=\"table-container\"><table class=\"table-container\" id=\"offenders\" style=\"text-align:center\" border='1'>
        <tr>
        <th class=\"noexport\"><input type=\"checkbox\" id=\"selectAllNotices\" title=\"Select All\"></th>
        <th>date</th>
        <th>plate</th>
        <th>unitno</th>
        <th>Visitor<br>Parking<br>Count</th>
        <th>Notices<br>Issued (week/past)</th>
        <th>Obser<br>vations</th>
        <th>Shared<br>Car</th>
        <th>Days</th>
        <th>Visitor<br>Permitted</th>
        <th>Visitor<br>Violated</th>
        <th>Resident<br>Spot</th>
        <th class=\"noexport\" colspan=\"4\">Action</a><br>Tickbox for notice issued</th>
        </tr>";
        $images = array();
        // --- MODIFIED LOOP ---
        foreach ($offender_rows as $row) {

            if (is_null($row['unitnumber'])) {
                $row['unitnumber'] = '';
            }
            
            $filePath = $row['uploadFile'];
            $base64Image = "";

            if (file_exists($filePath)) {
                $fileData = file_get_contents($filePath);
                $base64Image = base64_encode($fileData);
            }
            $images[$row['plate']] = $base64Image;

            if ($row['unitnumber']) {
                $filePath = $row['surveyUploadFile'];
                $base64Image = "";
    
                if (file_exists($filePath)) {
                    $fileData = file_get_contents($filePath);
                    $base64Image = base64_encode($fileData);
                    $images['unit-' . $row['plate'] . $row['unitnumber']] = $base64Image;
                }
            }

            // --- GET THE VISITOR COUNT FOR THE CURRENT PLATE ---
            $visitor_count = isset($visitor_counts[$row['plate']]) ? $visitor_counts[$row['plate']] : 0;
            $notice_count = isset($notice_issue_counts[$row['plate']]) ? $notice_issue_counts[$row['plate']] : '0/0';

            $is_permitted = false;
             if (isset($permissions[$row['plate']])) {
                 $perm = $permissions[$row['plate']];
                 $is_permitted = ($perm['visitor_permitted'] === 'YES' && 
                                  (is_null($perm['expiry_date']) || $perm['expiry_date'] >= date('Y-m-d')));
             }
             $expiry_date_value = isset($permissions[$row['plate']]['expiry_date']) ? $permissions[$row['plate']]['expiry_date'] : '';

            // Determine the display value for the 'Visitor Violated' (M286) column
            $m286_display_value = $row['M286']; // Default to the value from SQL
            if ($is_permitted) {
                $m286_display_value = 'Permitted';
                if (empty($row['unitnumber'])) {
                    $m286_display_value .= '<br><b>Unit?</b>';
                }
            }

            // --- GET THE WEEKLY NOTICE ISSUED STATUS ---
            $is_notice_issued = isset($weekly_notices_issued[$row['plate']]) && $weekly_notices_issued[$row['plate']]['issued'] == 1;
            $issued_at_timestamp = '';
            if ($is_notice_issued && !empty($weekly_notices_issued[$row['plate']]['issued_at'])) {
                $issued_at_timestamp = date('d/m/y H:i', strtotime($weekly_notices_issued[$row['plate']]['issued_at']));
            }

            $disable_notice_checkbox = $is_permitted ? 'disabled' : '';

            echo "<tr class=\"plate\" data-plate=\"{$row['plate']}\" >
            <td class=\"noexport\"><input type=\"checkbox\" class=\"notice-checkbox\" name=\"notices[]\" value=\"" . htmlspecialchars(http_build_query($row)) . "\" {$disable_notice_checkbox}></td>";

            // Display the last photo time for the week
            $display_phototime = $row['lastphoto'];
            $recent_phototime_html = '';

            // Check if there's a more recent photo in the last hour and if it's different from the weekly last photo.
            if (isset($recent_photos_last_hour[$row['plate']])) {
                $dt_recent = new DateTime($recent_photos_last_hour[$row['plate']], new DateTimeZone('UTC'));
                $dt_recent->setTimezone(new DateTimeZone('Australia/Sydney'));
                $recent_phototime_str = $dt_recent->format('Y-m-d H:i:s');
                if ($recent_phototime_str !== $display_phototime) {
                    $recent_phototime_html = '<br><b style="color: red;">' . $recent_phototime_str . '</b>';
                } else {
                    $display_phototime = '<br><b style="color: red;">' . $recent_phototime_str . '</b>';
                }
            }
            echo "<td>{$display_phototime}{$recent_phototime_html}</td>
            <td>{$row['plate']}</td>
            <td class=\"unitnumber\" style=\"text-wrap:none\" data-unitnumber=\"{$row['plate']}{$row['unitnumber']}\">"; ?>
                    <input 
                        type="hidden" 
                        id="old_unitnumber_<?= $row['plate']; ?>" 
                        value="<?= htmlspecialchars($row['unitnumber']); ?>"
                    />

                    <input style="width:50px"
                        type="text"
                        id="new_unitnumber_<?= $row['plate']; ?>" 
                        value="<?= htmlspecialchars($row['unitnumber']); ?>"
                        placeholder="Enter new unit number" 
                    />
                    <button type="button"  style="width:50px"
                            onclick="updateUnitNumber('<?= $row['plate']; ?>')">
                        Save
                    </button>
                </td>
            <?
            echo "
            <td>{$visitor_count}</td>
            <td>{$notice_count}</td>
            <td>{$row['M281']}</td>
            <td>{$row['M283']}</td>
            <td>{$row['M284']}</td>
            <td>
                <input type=\"checkbox\" class=\"permission-checkbox\" data-plate=\"{$row['plate']}\" " . ($is_permitted ? 'checked' : '') . " onchange=\"updateVisitorPermission(this)\">
                <br>
                <input type=\"date\" class=\"expiry-date-input\" data-plate=\"{$row['plate']}\" value=\"{$expiry_date_value}\" onchange=\"updateVisitorPermission(this)\" style=\"margin-top: 4px;\">
            </td>
            <td class=\"visitor-violated-cell\" data-original-m286=\"" . htmlspecialchars($row['M286']) . "\">{$m286_display_value}</td>
            <td>{$row['M287']}</td>
            <td class=\"noexport\" style=\"text-align: center;\">
                <a target=\"blank\"  href=\"download_notices.php?" . http_build_query($row) .  "\">Notice</a>
                <br>
                <input type=\"checkbox\" 
                       class=\"weekly-notice-checkbox\" 
                       data-plate=\"{$row['plate']}\" 
                       data-week-start=\"" . date('Y-m-d', strtotime($localStart)) . "\"
                       " . ($is_notice_issued ? 'checked' : '') . ">
                <div class=\"issued-timestamp\" id=\"timestamp-{$row['plate']}\" style=\"font-size: 0.7em; color: #666;\">
                    " . ($is_notice_issued ? $issued_at_timestamp : '') . "
                </div>
            </td>
            <td class=\"noexport\"><a href=\"download_pdf.php?ids={$row['ids']}\">PDF</a></td>
            <td class=\"noexport\"><a target=\"blank\" href=\"all.php?id={$row['id']}\">Photos</a></td>
            <td class=\"noexport\"><a target=\"_blank\" href=\"all.php?id={$row['id']}\">Photos</a></td>
            </tr>";
        }
        echo "</table></div>";

        ?><div id="image-preview" style="position:sticky; top:0;"><?
        foreach ($images as $plate => $base64Image) {
            /* Display the image when the user hovers over the number plate cell */
            ?><img id="image-<?=$plate?>" style="display:none" src="data:image/jpeg;base64,<?=htmlspecialchars($base64Image)?>" alt="Photo"><?
        }
        ?></div></div><?
    }
}
?>
</div>
<script>
    // XHR Update Function
    function updateUnitNumber(plate) {
        var oldUnitNumber = document.getElementById('old_unitnumber_' + plate).value;
        var newUnitNumber = document.getElementById('new_unitnumber_' + plate).value;

        if (oldUnitNumber==newUnitNumber) {
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "update_unitnumber.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === "success") {
                        alert("Unit number updated successfully!");
                    } else {
                        alert("Error: " + (response.message || "Unknown error"));
                    }
                } catch (e) {
                    alert("Error parsing server response.");
                }
            }
        };
        
        var params = 
            "plate=" + encodeURIComponent(plate) +
            "&old_unitnumber=" + encodeURIComponent(oldUnitNumber) +
            "&new_unitnumber=" + encodeURIComponent(newUnitNumber);
            
        xhr.send(params);
    }
    
    // --- SCRIPT FOR PRINTING NOTICES ---
    
    // Logic for the "Select All" checkbox
    document.getElementById('selectAllNotices').addEventListener('change', function(e) {
        var isChecked = e.target.checked;
        var checkboxes = document.querySelectorAll('.notice-checkbox');
        for (var i = 0; i < checkboxes.length; i++) {
            var row = checkboxes[i].closest('tr');
            if (row && row.style.display !== 'none' && !checkboxes[i].disabled) {
                checkboxes[i].checked = isChecked; // Only check if not disabled
            }
        }
    });

    // Handle the "Print Notices" button click
    document.getElementById('printNoticesButton').addEventListener('click', function() {
        var selectedNotices = [];
        var checkboxes = document.querySelectorAll('.notice-checkbox:checked');
        
        if (checkboxes.length === 0) {
            alert('Please select at least one notice to print.');
            return;
        }

        for (var i = 0; i < checkboxes.length; i++) {
            selectedNotices.push(checkboxes[i].value);
        }

        var printButton = document.getElementById('printNoticesButton');
        var originalButtonText = printButton.textContent;
        printButton.textContent = 'Processing...';
        printButton.disabled = true;

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "send_notices.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                // Restore button state
                printButton.textContent = originalButtonText;
                printButton.disabled = false;
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        alert(response.message); // Display success or error message from server
                    } catch (e) {
                        alert("An error occurred. The server's response could not be understood.");
                        console.error("Server Response: ", xhr.responseText);
                    }
                } else {
                    alert("Failed to send notices. The server responded with status: " + xhr.status);
                }
            }
        };

        // Send the selected notices as a JSON string
        var params = "notices=" + encodeURIComponent(JSON.stringify(selectedNotices));
        xhr.send(params);
    });

    // --- SCRIPT FOR UPDATING VISITOR PERMISSION ---
    function updateVisitorPermission(checkbox) {
        const plate = checkbox.getAttribute('data-plate');
        const row = checkbox.closest('tr');
        const permissionCheckbox = row.querySelector('.permission-checkbox');
        const expiryDateInput = row.querySelector('.expiry-date-input');
        const permitted = permissionCheckbox.checked;
        const expiryDate = expiryDateInput.value;

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "update_permission.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === "success") {
                            // Optional: Show a small success message or log to console
                            console.log("Permission updated for " + plate + ": " + response.message);

                            // Dynamically update the 'Visitor Violated' cell
                            const rowElement = checkbox.closest('tr');
                            if (rowElement) {
                                const violatedCell = rowElement.querySelector('.visitor-violated-cell');
                                const noticeCheckbox = rowElement.querySelector('.notice-checkbox');
                                const unitNumberInput = rowElement.querySelector('#new_unitnumber_' + plate);
                                const unitNumber = unitNumberInput ? unitNumberInput.value.trim() : '';
                                const originalM286 = violatedCell ? violatedCell.getAttribute('data-original-m286') : '';

                                if (violatedCell) {
                                    if (permitted && unitNumber !== '') {
                                        violatedCell.textContent = 'Permitted';
                                        if (noticeCheckbox) {
                                            noticeCheckbox.disabled = true;
                                            noticeCheckbox.checked = false;
                                        }
                                    } else {
                                        if (permitted && unitNumber == '') {
                                            violatedCell.innerHTML = 'Permitted<br><b>Unit?</b>';  
                                            if (noticeCheckbox) {
                                                noticeCheckbox.disabled = true;
                                                noticeCheckbox.checked = false;
                                            }
                                        } else {
                                            violatedCell.innerHTML = 'Violated';
                                            if (noticeCheckbox) noticeCheckbox.disabled = false;
                                        }
                                    }
                                }
                            }

                        } else {
                            alert("Error: " + (response.message || "Unknown error updating permission.")); // Revert checkbox on error
                            permissionCheckbox.checked = !permitted;
                        }
                    } catch (e) {
                        alert("Error parsing server response for permission update.");
                        permissionCheckbox.checked = !permitted; // Revert checkbox on error
                    }
                } else {
                    alert("Failed to update permission. Server responded with status: " + xhr.status);
                    permissionCheckbox.checked = !permitted; // Revert checkbox on error
                }
            }
        };

        var params = "plate=" + encodeURIComponent(plate) + 
                     "&permitted=" + permitted +
                     "&expiry_date=" + encodeURIComponent(expiryDate);
        xhr.send(params);
    }

    // --- SCRIPT FOR HIDING NON-VIOLATIONS ---
    document.getElementById('showViolationsOnlyCheckbox').addEventListener('change', function() {
        var showOnlyViolations = this.checked;
        var tableRows = document.querySelectorAll('#offenders tr.plate');

        for (var i = 0; i < tableRows.length; i++) {
            var row = tableRows[i];
            var violatedCell = row.querySelector('.visitor-violated-cell');
            var isViolation = false;

            if (violatedCell && violatedCell.textContent.trim() === 'Violated') {
                isViolation = true;
            }

            if (showOnlyViolations) {
                if (isViolation) {
                    row.style.display = ''; // Show the row
                } else {
                    row.style.display = 'none'; // Hide the row
                }
            } else {
                row.style.display = ''; // Show all rows
            }
        }
    });

    // --- SCRIPT FOR WEEKLY NOTICE CHECKBOX ---
    document.addEventListener('DOMContentLoaded', function() {
        var noticeCheckboxes = document.querySelectorAll('.weekly-notice-checkbox');
        for (var i = 0; i < noticeCheckboxes.length; i++) {
            noticeCheckboxes[i].addEventListener('change', function(e) {
                updateWeeklyNoticeStatus(e.target);
            });
        }
    });

    function updateWeeklyNoticeStatus(checkbox) {
        var plate = checkbox.getAttribute('data-plate');
        var weekStartDate = checkbox.getAttribute('data-week-start');
        var isIssued = checkbox.checked;

        var timestampDiv = document.getElementById('timestamp-' + plate);

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "update_weekly_notice.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        xhr.onload = function() {
            if (xhr.status === 200) {
                if (isIssued) {
                    timestampDiv.textContent = new Date().toLocaleString('en-AU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).replace(',', '');
                } else {
                    timestampDiv.textContent = '';
                }
            }
        };
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status !== 200) {
                    alert('Error updating notice status. Server responded with: ' + xhr.status);
                    checkbox.checked = !isIssued; // Revert on failure
                }
                // Success is silent unless you want a message
                if (isIssued) {
                    timestampDiv.textContent = new Date().toLocaleString('en-AU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).replace(',', '');
                } else {
                    timestampDiv.textContent = '';
                }
            }
        };

        var params = "plate=" + encodeURIComponent(plate) +
                     "&week_start_date=" + encodeURIComponent(weekStartDate) +
                     "&issued=" + isIssued;
        xhr.send(params);
    }

    <?php Database::close(); ?>
</script>
</body>
</html>