<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Parking Survey - All photos</title>
    <link rel="stylesheet" href="styles.css">
    <style>.offence { height:425px} </style>
</head>
<body>
<?php

include 'nav.php';

/**
 * Generates a base64-encoded SVG placeholder image.
 *
 * @param string $text The text to display on the placeholder.
 * @param string $color The background color of the placeholder.
 * @return string The data URI for the SVG image.
 */
function generate_placeholder_svg($text, $color) {
    $width = 200;
    $height = 356;
    $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\"><rect fill=\"{$color}\" width=\"{$width}\" height=\"{$height}\"/><text fill=\"rgba(255,255,255,0.7)\" font-family=\"sans-serif\" font-size=\"20\" dy=\"8\" font-weight=\"bold\" x=\"50%\" y=\"50%\" text-anchor=\"middle\">{$text}</text></svg>";
    return "data:image/svg+xml;base64," . base64_encode($svg);
}

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

// SQL to create table if it does not exist


// Prepare the SQL query
$sql = "SELECT id, plate, unitnumber, uploadFile, phototime, result, CONVERT_TZ(phototime, '+00:00', 'Australia/Sydney') as local_timezone FROM survey GROUP by unitnumber,plate ORDER BY unitnumber";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML table
if ($result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {
        $filePath = $row['uploadFile'];
        $imageSrc = '';
        $link_start = '';
        $link_end = '';

        if (!empty($filePath) && file_exists($filePath)) {
            $fileData = file_get_contents($filePath);
            $base64Image = base64_encode($fileData);
            $imageSrc = "data:image/jpeg;base64," . htmlspecialchars($base64Image);
            $link_start = "<a class=\"vehicle\" target=\"_blank\" href=\"download.php?file=$filePath\">";
            $link_end = "</a>";
        } else {
            $result_text = isset($row['result']) ? $row['result'] : '';
            if ($result_text === 'Manual') {
                $imageSrc = generate_placeholder_svg('Manual', '#4CAF50'); // Green
            } elseif ($result_text === 'Imported') {
                $imageSrc = generate_placeholder_svg('Imported', '#2196F3'); // Blue
            } else {
                // Fallback for other/empty result values or if result is not set
                $imageSrc = generate_placeholder_svg('No Photo', '#9E9E9E'); // Grey
            }

            $link_start = "<span class=\"vehicle\">";
            $link_end = "</span>";
        }

        $formattedPhototime = (new DateTime($row['local_timezone']))->format('Y-m-d H:i:s');
        $row['result'] = preg_replace('/The registration plate number is */','',$row['result']);


        echo '<div class="offence">';
        echo $link_start . "<img src='" . $imageSrc . "' alt='Photo'>" . $link_end;
        echo "<div class=\"timestamp\">". htmlspecialchars($formattedPhototime)  . 
             //"<br><a href=\"api3.php?id="  . htmlspecialchars($row['id']) . "\">Reprocess " . $row['id'] . "</a>" .
             '<div style="height:2em">' . $row['id'] . '-' . htmlspecialchars($row['unitnumber']) . '/'  . htmlspecialchars($row['plate'])  .'</div>' . 

            "</div>";
        echo "</div>";
    }

    echo "</table>";
} else {
    echo "No records found for plate: " . htmlspecialchars($plate);
}


$conn->close();

?>
</body>
</html>