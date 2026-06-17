<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Parking - Offender Photos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container">

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

$sql = "SELECT p1.id, p1.plate, p1.uploadFile, p1.phototime, CONVERT_TZ(p1.phototime, '+00:00', 'Australia/Sydney') as local_timezone
FROM parking_records p1
WHERE p1.plate IN (
    SELECT p2.plate
    FROM (
        SELECT plate, COUNT(DISTINCT checksum) AS count
        FROM parking_records
        GROUP BY plate
    ) p2
    WHERE p2.count > 4
)
AND p1.plate IN (
    SELECT plate
    FROM parking_records
    WHERE phototime >= NOW() - INTERVAL 14 DAY
)
GROUP BY p1.checksum
ORDER BY p1.plate, p1.phototime desc;";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML table
if ($result->num_rows > 0) {

    $lastPlate = '~';

    while ($row = $result->fetch_assoc()) {
        $filePath = $row['uploadFile'];
        $base64Image = "";

        if (file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            $base64Image = base64_encode($fileData);
        }

        $formattedPhototime = (new DateTime($row['local_timezone']))->format('d/m/Y H:i:s');

        $firstPlate = $lastPlate == '~';
        $newPlate = $lastPlate != $row['plate'];
        $lastPlate = $row['plate'];

        if ($newPlate) {
            if (!$firstPlate) {
                echo '<div style="clear:both"></div></div>';
            }
            echo '<div class="offenceenv">';
            echo "<h3 class=\"offenceheading\">Vehicle " . htmlspecialchars($lastPlate) . "</h3>";
        }

        echo '<div class="offence">';
        echo "<a class=\"vehicle\" href=\"download.php?file=$filePath\"><img src='data:image/jpeg;base64," . htmlspecialchars($base64Image) . "' alt='Photo'></a>";
        echo "<div class=\"timestamp\">". htmlspecialchars($formattedPhototime)  . "</div>";
        echo "<div class=\"timestamp\"><a href=\"api2.php?id="  . htmlspecialchars($row['id']) . "\">Reprocess " . $row['id'] . "</a></div>";

        echo "</div>";
    }
    if (!$firstPlate) {
        echo  '<div style="clear:both"></div></div>';
    }


} else {
    echo "No records found for plate: " . htmlspecialchars($plate);
}



$conn->close();

?>
</div>
</body>
</html>
