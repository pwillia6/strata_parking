<?php

require_once __DIR__ . '/../vendor/autoload.php';

$plate = $_GET['plate'];

/* Get photos */
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


 /* Convert a dd-mm-yy date in Sydney time to UTC, returning the first or last second of that day.
 *
 * @param string $dateStringDDMMYY A string in dd-mm-yy format (e.g., '02-03-25').
 * @param string $dayBoundary      'start' or 'end' to specify first or last second of the day.
 * @param string $format           (Optional) The desired output format. Default is 'Y-m-d H:i:s'.
 *
 * @return string                  The UTC date/time string in the specified format.
 */
function convertSydneyToUTC($date, $dayBoundary = 'start', $format = 'Y-m-d H:i:s')
{
    // Create a DateTime object from the dd-mm-yy string in Australia/Sydney timezone
    $dateTime = DateTime::createFromFormat(
        'Y-m-d',
        $date,
        new DateTimeZone('Australia/Sydney')
    );

    // Handle errors in case createFromFormat fails
    if (!$dateTime) {
        // You may throw an exception or return an empty string. For simplicity, return empty.
        return 'ERROR';
    }

    // Set time to either start of day (00:00:00) or end of day (23:59:59)
    if ($dayBoundary === 'end') {
        $dateTime->setTime(23, 59, 59);
    } else {
        // Default to 'start'
        $dateTime->setTime(0, 0, 0);
    }

    // Convert to UTC
    $dateTime->setTimezone(new DateTimeZone('UTC'));

    // Return the date/time in UTC, formatted as requested
    return $dateTime->format($format);
}

$timeFrom = convertSydneyToUTC($_GET['datefrom'], $dayBoundary = 'start');
$timeTo = convertSydneyToUTC($_GET['dateto'], $dayBoundary = 'end');

if ($_GET['unitnumber']=='') {
    $sql = "SELECT unitnumber FROM vehicles where plate=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $plate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if (isset($row['unitnumber']) ) {
        $_GET['unitnumber'] = $row['unitnumber'];
    }
}

function addImages($table, $images = []) {
    global $conn, $plate, $timeFrom, $timeTo;

    switch ($table) {
        case 'parking_records': 
            $sql = "SELECT * FROM parking_records WHERE plate = ? and phototime between ? and ? GROUP BY checksum ORDER BY phototime"; 
            $caption = 'Visitor Parking';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $plate, $timeFrom, $timeTo);
            break;
        case 'notice': 
            $sql = "SELECT * FROM notice WHERE plate = ? GROUP BY checksum ORDER BY phototime desc"; 
            $caption = 'Notice';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $plate);
            break;
    
        case 'survey':
            $sql = "SELECT * FROM survey WHERE plate = ? order by phototime desc limit 1"; 
            $caption = 'Survey';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $plate);
            break;
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $lastPlate = '~';

        while ($row = $result->fetch_assoc()) {
            $filePath = $row['uploadFile'];

            if (trim($filePath)=='') continue;

            $filePath = realpath($filePath); 
           

            // Convert UTC timestamp to Australia/Sydney time zone
            $utcPhototime = new DateTime($row['phototime'], new DateTimeZone('UTC'));
            $utcPhototime->setTimezone(new DateTimeZone('Australia/Sydney'));
            $formattedPhototime = $utcPhototime->format('d/m/Y H:i:s');
            $xcaption = $caption;
            if (isset($row['containsnumber']) &&   $row['containsnumber']=='Yes') {
                $xcaption = 'Resident Park';
            }

            $images[] = [ 'file' =>$filePath, 'caption' => $xcaption, 'phototime' => $formattedPhototime];

        }
    }
    return $images;
}

//print_r($_GET); exit;
$images = addImages('notice');
$images = addImages('survey', $images);
$images = addImages('parking_records', $images);
//print_r($images); exit;

// Instantiate TinyButStrong and load the OpenTBS plugin
new clsOpenTBS(); // Load constants
$TBS = new clsTinyButStrong;
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

// Load your DOCX (or ODT) template
$template = $_GET['unitnumber']=='' ? 'template.docx' : 'template_unitnumber.docx';
$TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);

foreach ($_GET as $name => $val) {
   $TBS->VarRef[$name] = $val;
}


$TBS->VarRef['platex'] = $plate;

// Merge the images
$TBS->MergeBlock('blkImg', $images);

$TBS->Show(OPENTBS_DOWNLOAD, 'Notice ' . $plate . '-' . $_GET['dateto'] . '.docx' );
exit;
?>
