<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Instantiate TinyButStrong and load the OpenTBS plugin
new clsOpenTBS(); // Load constants
$TBS = new clsTinyButStrong;
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

// Load your DOCX (or ODT) template
$template = 'template.docx';
$TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);


$data = [ 'plate' => 'XXXXXX' ];
foreach ($data as $name => $val) {
   $TBS->VarRef[$name] = $val;
}
print_r($TBS->VarRef);

// Prepare your data (array of arrays)
$images = [
    [
        'img_path' => 'images/photo_1.jpg',
        'caption'  => 'A beautiful sunrise in the mountains',
    ],
    [
        'img_path' => 'images/photo_2.png',
        'caption'  => 'Lake view with a reflection of the surrounding forest',
    ],
    [
        'img_path' => 'images/photo_3.jpg',
        'caption'  => 'City skyline at night with bright lights',
    ],
];

// Merge the repeating block
//$TBS->MergeBlock('blk', $images);

// Output the merged document to the browser
$TBS->Show(OPENTBS_FILE, 'merged_with_images.docx');
//$TBS->Show(OPENTBS_DOWNLOAD, 'merged_with_images.docx');
exit;
?>

