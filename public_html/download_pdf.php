<?php
/**
 * download_pdf.php
 *
 * Reads comma-separated IDs or a plate number from the query string,
 * fetches the corresponding parking records, and generates a PDF
 * with the images of the vehicles.
 */

// --------------------------------------------------
// 1. Include FPDF library
// --------------------------------------------------
require_once('lib/fpdf.php');

// --------------------------------------------------
// 2. Error Reporting
// --------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --------------------------------------------------
// 3. Database Connection
// --------------------------------------------------
try {
    $conn = Database::getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --------------------------------------------------
// 4. Detect Query Parameters
//    - Check for ?ids=1,2,3
//    - Check for ?plate=ABC123
// --------------------------------------------------
$idsParam   = isset($_GET['ids'])   ? trim($_GET['ids'])   : '';
$plateParam = isset($_GET['plate']) ? trim($_GET['plate']) : '';

if (empty($idsParam) && empty($plateParam)) {
    die("No query parameters provided. Use ?ids=1,2 OR ?plate=ABC123");
}

// --------------------------------------------------
// 5. Build and Execute the Query
// --------------------------------------------------
$records = array();

if (!empty($idsParam)) {
    // 5a. Handle IDs
    $idsArray = array_filter(array_map('trim', explode(',', $idsParam)), 'is_numeric');
    if (empty($idsArray)) {
        die("No valid numeric IDs found in ?ids parameter.");
    }
    
    $placeholders = rtrim(str_repeat('?,', count($idsArray)), ',');
    $sql = "SELECT id, plate, uploadFile, phototime
            FROM parking_records
            WHERE id IN ($placeholders) ORDER BY phototime DESC";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($idsArray));
    $stmt->bind_param($types, ...$idsArray);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} elseif (!empty($plateParam)) {
    // 5b. Handle single plate
    $sql = "SELECT id, plate, uploadFile, phototime
            FROM parking_records
            WHERE plate = ? ORDER BY phototime DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $plateParam);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// If no records found
if (empty($records)) {
    die("No records found for the given parameter(s).");
}

// --------------------------------------------------
// 6. Prepare Data for the PDF
// --------------------------------------------------
$photos = array();

foreach ($records as $row) {
    $imagePath = $row['uploadFile'];
    if (!file_exists($imagePath)) {
        continue;
    }

    // Convert phototime from UTC to Australia/Sydney
    $convertedTime = 'No time found';
    if (!empty($row['phototime'])) {
        try {
            $dateUtc = new DateTime($row['phototime'], new DateTimeZone('UTC'));
            $dateUtc->setTimezone(new DateTimeZone('Australia/Sydney'));
            $convertedTime = $dateUtc->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Handle potential DateTime errors gracefully
            $convertedTime = 'Invalid time format';
        }
    }

    // Tagline includes plate + phototime
    $tagLine = $row['plate'] . ' - ' . $convertedTime;

    $photos[] = array(
        'file' => $imagePath,
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
class FourUpPDF extends FPDF {
    private $imagesPerPage = 4;  // 2×2
    // Coordinates (x,y) for the 4 image slots on an A4 page (portrait)
    private $slots = array(
        array('x' => 10,  'y' => 10),
        array('x' => 110, 'y' => 10),
        array('x' => 10,  'y' => 150),
        array('x' => 110, 'y' => 150),
    );
    // Max width/height
    private $maxImageWidth = 90;
    private $maxImageHeight = 90;

    public function __construct() {
        parent::__construct('P', 'mm', 'A4');
        $this->SetMargins(10, 10, 10);
    }

    public function createPdf(array $photos) {
        $slotIndex = 0;

        foreach ($photos as $photo) {
            if ($slotIndex === 0) {
                $this->AddPage();
            }

            $x = $this->slots[$slotIndex]['x'];
            $y = $this->slots[$slotIndex]['y'];

            $imagePath = $photo['file'];
            $tagLine = $photo['tagline'];

            $imgSize = @getimagesize($imagePath);
            if ($imgSize === false) {
                continue;
            }
            list($imgWidth, $imgHeight) = $imgSize;

            // Scale to fit
            $widthRatio = $this->maxImageWidth / $imgWidth;
            $heightRatio = $this->maxImageHeight / $imgHeight;
            $scale = min($widthRatio, $heightRatio);

            $finalWidth = $imgWidth * $scale;
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
