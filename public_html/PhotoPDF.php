<?php
require('lib/fpdf.php');

/**
 * PhotoPDF
 * Generates a PDF with 4 images per page, each with its own tagline,
 * using a larger font for the tagline. 
 */
class PhotoPDF
{
    /**
     * @var FPDF
     */
    private $pdf;

    /**
     * Number of images per page (4 in this example: 2×2 grid).
     * Adjust if you need more/less per page.
     */
    private $imagesPerPage = 4;

    /**
     * Coordinates for each of the 4 "slots" on a page (x,y in mm).
     * - These are based on A4 size (210×297 mm) with 10 mm margins,
     *   leaving ~190 mm width and ~277 mm height.
     * - The example splits the page into 2 columns × 2 rows.
     */
    private $slots = array(
        // Slot 1 (top-left)
        array('x' => 10,  'y' => 10),
        // Slot 2 (top-right)
        array('x' => 110, 'y' => 10),
        // Slot 3 (bottom-left)
        array('x' => 10,  'y' => 150),
        // Slot 4 (bottom-right)
        array('x' => 110, 'y' => 150),
    );

    /**
     * Maximum width/height allowed for an image in each slot (in mm).
     * The tagline will go underneath the image.
     */
    private $maxImageWidth  = 90;
    private $maxImageHeight = 90;

    /**
     * Constructor - create an FPDF instance (portrait, mm units, A4).
     */
    public function __construct()
    {
        $this->pdf = new FPDF('P', 'mm', 'A4');
        $this->pdf->SetMargins(10, 10, 10);
    }

    /**
     * Creates a PDF file from an array of photos, 
     * with 4 images per page in a 2×2 grid.
     *
     * @param array  $photos     Each element is an associative array like:
     *                            [
     *                              'file'    => '/path/to/image.jpg',
     *                              'tagline' => 'Tagline for this image'
     *                            ]
     * @param string $outputFile Name of the PDF file to be generated.
     */
    public function createPdf(array $photos, $outputFile = 'output.pdf')
    {
        // Track which slot (0–3) we are on a given page
        $slotIndex = 0;

        // Iterate over each photo
        foreach ($photos as $index => $photo) {
            $imagePath = isset($photo['file']) ? $photo['file'] : '';
            $tagLine   = isset($photo['tagline']) ? $photo['tagline'] : '';

            // Validate image existence/readability
            if (!file_exists($imagePath) || !is_readable($imagePath)) {
                // Skip or handle error if needed
                continue;
            }

            // If we're starting a fresh page (slotIndex = 0) or 0 mod imagesPerPage
            if ($slotIndex === 0) {
                $this->pdf->AddPage();
            }

            // Calculate the slot's position on the page
            $x = $this->slots[$slotIndex]['x'];
            $y = $this->slots[$slotIndex]['y'];

            // Read image dimensions
            $imgSize = getimagesize($imagePath);
            if ($imgSize === false) {
                // Skip or handle error if needed
                continue;
            }

            $imgWidth  = $imgSize[0];
            $imgHeight = $imgSize[1];

            // Calculate scaling to fit inside the maxImageWidth/Height
            $widthRatio  = $this->maxImageWidth / $imgWidth;
            $heightRatio = $this->maxImageHeight / $imgHeight;
            $scale       = min($widthRatio, $heightRatio);

            // Final scaled dimensions (in mm)
            $finalWidth  = $imgWidth * $scale;
            $finalHeight = $imgHeight * $scale;

            // Center the image horizontally in the slot if there's leftover space
            $xImage = $x + (($this->maxImageWidth - $finalWidth) / 2);
            // Place the image at the top of the slot
            $yImage = $y;

            // Place the image
            $this->pdf->Image($imagePath, $xImage, $yImage, $finalWidth, $finalHeight);

            // Place the tagline below the image, with a small gap
            $this->pdf->SetFont('Arial', 'B', 16);  // Double-size font
            $tagY = $yImage + $finalHeight + 5;     // 5 mm below image

            // Make sure the tagline text starts at the correct position
            $this->pdf->SetXY($x, $tagY);
            // Create a cell the same width as the slot (90 mm), so tagline is centered
            $this->pdf->Cell(
                $this->maxImageWidth,   // cell width
                8,                      // cell height
                $tagLine,               // text
                0,                      // border (0 = no border)
                0,                      // line break
                'C'                     // text alignment (center)
            );

            // Move to the next slot
            $slotIndex++;

            // If we've filled all 4 slots on this page, reset slot index for next page
            if ($slotIndex >= $this->imagesPerPage) {
                $slotIndex = 0;
            }
        }

        // Output the PDF
        $this->pdf->Output('F', $outputFile);
    }
}
