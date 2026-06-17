<?php
/**
 * generate_pdf.php
 *
 * Example script that:
 * 1. Reads comma-separated IDs from `?ids=1,2,3`.
 * 2. Queries `parking_records` for those IDs.
 * 3. Converts `phototime` from UTC to Australia/Sydney.
 * 4. Generates a PDF (4 images per page) using FPDF.
 */

// --------------------------------------------------
// 1. Include FPDF library
//    Make sure fpdf.php is in the same directory or
//    adjust the path accordingly.
// --------------------------------------------------
require_once('lib/fpdf.php');

// --------------------------------------------------
// 2. OPTIONAL: Adjust error reporting for PHP 5
// --------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --------------------------------------------------
// 3. Database Connection
// --------------------------------------------------
$host     = "localhost";
$db       = "parking";
$user     = "root";
$password = "";

// Create a PDO connection (you could use mysqli as well).
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --------------------------------------------------
// 4. Detect Query Parameters
//    - Check for ?ids=1,2,3
//    - Check for ?plate=ABC123
//    - If both exist, IDs take precedence (change logic if needed).
// --------------------------------------------------
$idsParam   = isset($_GET['ids'])   ? trim($_GET['ids'])   : '';
$plateParam = isset($_GET['plate']) ? trim($_GET['plate']) : '';

if (empty($idsParam) && empty($plateParam)) {
    die("No query parameters provided. Use ?ids=1,2 OR ?plate=ABC123");
}

// --------------------------------------------------
// 5. Build and Execute the Query
//    - If we have `ids`, use them in an IN-clause.
//    - Else if we have `plate`, select by plate.
// --------------------------------------------------
$records = array();

if (!empty($idsParam)) {
    // 5a. Handle IDs
    $idsArray = array_filter(array_map('trim', explode(',', $idsParam)), 'is_numeric');
    if (empty($idsArray)) {
        die("No valid numeric IDs found in ?ids parameter.");
    }
    
    $placeholders = rtrim(str_repeat('?,', count($idsArray)), ',');
    $sql  = "SELECT id, plate, uploadFile, phototime
             FROM parking_records
             WHERE id IN ($placeholders) order by phototime desc";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($idsArray);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif (!empty($plateParam)) {
    // 5b. Handle single plate
    $sql  = "SELECT id, plate, uploadFile, phototime
             FROM parking_records
             WHERE plate = :plate  order by phototime desc";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':plate' => $plateParam));
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If no records found
if (!$records) {
    die("No records found for the given parameter(s).");
}

// --------------------------------------------------
// 6. Prepare Data for the PDF
//    - For each record, build array with 'file' and 'tagline'.
//    - Convert phototime to Australia/Sydney if available.
// --------------------------------------------------
$photos = array();

foreach ($records as $row) {
    $imagePath = $row['uploadFile']; // e.g. stored path to the JPG
    if (!file_exists($imagePath)) {
        // Could skip or handle differently if file doesn't exist
        continue;
    }

    // Convert phototime from UTC to Australia/Sydney
    $convertedTime = 'No time found';
    if (!empty($row['phototime'])) {
        $dateUtc = new DateTime($row['phototime'], new DateTimeZone('UTC'));
        $dateUtc->setTimezone(new DateTimeZone('Australia/Sydney'));
        $convertedTime = $dateUtc->format('Y-m-d H:i:s');
    }

    // Tagline includes plate + phototime
    $tagLine = $row['plate'] . ' - ' . $convertedTime;

    $photos[] = array(
        'file'    => $imagePath,
        'tagline' => $tagLine,
        'plate' => $row['plate']
    );
}

if (empty($photos)) {
    die("No valid images found for the given parameter(s).");
}

// --------------------------------------------------
// 7. Define a PDF Class for 4 Images per Page
// --------------------------------------------------
class FourUpPDF extends FPDF
{
    private $imagesPerPage = 4;  // 2×2
    // Coordinates (x,y) for the 4 image slots on an A4 page (portrait)
    private $slots = array(
        array('x' => 10,  'y' => 10),
        array('x' => 110, 'y' => 10),
        array('x' => 10,  'y' => 150),
        array('x' => 110, 'y' => 150),
    );
    // Max width/height
    private $maxImageWidth  = 90;
    private $maxImageHeight = 90;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetMargins(10, 10, 10);
    }

    public function createPdf(array $photos)
    {
        $slotIndex = 0;

        foreach ($photos as $photo) {
            if ($slotIndex === 0) {
                $this->AddPage();
            }

            $x = $this->slots[$slotIndex]['x'];
            $y = $this->slots[$slotIndex]['y'];

            $imagePath = $photo['file'];
            $tagLine   = $photo['tagline'];

            $imgSize = @getimagesize($imagePath);
            if ($imgSize === false) {
                // skip if invalid image
                continue;
            }
            list($imgWidth, $imgHeight) = $imgSize;

            // Scale to fit
            $widthRatio  = $this->maxImageWidth  / $imgWidth;
            $heightRatio = $this->maxImageHeight / $imgHeight;
            $scale       = min($widthRatio, $heightRatio);

            $finalWidth  = $imgWidth  * $scale;
            $finalHeight = $imgHeight * $scale;

            // Center image within the slot's max width
            $xImage = $x + (($this->maxImageWidth - $finalWidth) / 2);
            $yImage = $y;

            $this->Image($imagePath, $xImage, $yImage, $finalWidth, $finalHeight);

            // Tagline in larger font, below image
            $this->SetFont('Arial', 'B', 16);
            $tagY = $yImage + $finalHeight + 5;
            $this->SetXY($x, $tagY);
            $this->Cell(
                $this->maxImageWidth, 
                8, 
                $tagLine, 
                0, 
                0, 
                'C'
            );

            // Move to next slot
            $slotIndex++;
            if ($slotIndex >= $this->imagesPerPage) {
                $slotIndex = 0;
            }
        }
    }
}

// --------------------------------------------------
// 8. Generate the PDF and force download
// --------------------------------------------------
$pdf = new FourUpPDF();
$pdf->createPdf($photos);


// Get the plate from the *last* photo (as requested)
$lastPhoto = end($photos);         // get the last array element
$lastPlate = $lastPhoto['plate'];  // last record's plate

// Build filename using last plate
$pdfFilename = "Parking Records {$lastPlate}.pdf";

// --------------------------------------------------
// 9. Force download in the browser
// --------------------------------------------------
$pdf->Output('D', $pdfFilename);
exit; // Prevent any additional output