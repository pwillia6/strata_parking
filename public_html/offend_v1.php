<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Photo Capture Web App</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container" style="text-align:left">

<?php


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



$id = intval($_GET['id']);


$sql = "SELECT 'Visitor' as title, p1.plate, p1.uploadFile, p1.phototime, CONVERT_TZ(p1.phototime, '+00:00', 'Australia/Sydney') as local_timezone, p1.checksum
FROM parking_records p1
WHERE p1.plate IN (
    SELECT plate
    FROM parking_records
    WHERE id=?
)
UNION
        SELECT 'Notice' as title,  s.plate, s.uploadFile, s.phototime, CONVERT_TZ(s.phototime, '+00:00', 'Australia/Sydney') as local_timezone, s.checksum 
        FROM notice s WHERE plate=(select plate from parking_records where id=?)

GROUP BY checksum
ORDER BY plate, phototime desc
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $id, $id);
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

        // Use the pre-converted timezone from MySQL
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
        echo "<div style=\"text-align:center\">". htmlspecialchars($row['title'])  . "</div>";
        echo "<div  style=\"text-align:center\" class=\"timestamp\">". htmlspecialchars($formattedPhototime)  . "</div>";
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
