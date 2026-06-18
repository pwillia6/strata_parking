<?php

/**
 * Handles file-related operations, such as saving uploaded photos
 * and extracting request data.
 */
class FileHandler {
    /**
     * Saves an uploaded file to a specified directory.
     *
     * @param string $tmpPath The temporary path of the uploaded file ($_FILES['photo']['tmp_name']).
     * @param string $originalName The original name of the file ($_FILES['photo']['name']).
     * @param string $destinationDir The directory to save the file in.
     * @param string $plate The license plate number.
     * @param string $phototime The timestamp of the photo.
     * @return string The full path to the saved file.
     * @throws Exception If the directory cannot be created or the file cannot be moved.
     */
    public static function saveUploadedFile($tmpPath, $originalName, $destinationDir, $plate, $phototime) {
        // Ensure the upload directory exists
        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0777, true)) {
                throw new Exception("Failed to create directory: {$destinationDir}");
            }
        }

        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Basic validation for file type
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid file type');
        }

        $uniqueFileName = $plate . ' ' . $phototime . '.' . $fileExtension;
        $uploadFile = $destinationDir . $uniqueFileName;

        if (!move_uploaded_file($tmpPath, $uploadFile)) {
            throw new Exception("Failed to move uploaded file to {$uploadFile}.");
        }

        return $uploadFile;
    }

    /**
     * Extracts and validates required data from the POST/FILES superglobals for an upload request.
     *
     * @param string $destinationDir The target directory for the upload.
     * @return object An object with 'imagePath', 'originalName', 'phototime', 'uuid', and 'uploadDir'.
     * @throws Exception If required data is missing.
     */
    public static function getRequestDataForUpload($destinationDir) {
        if (!isset($_FILES['photo']['tmp_name']) || !isset($_POST['phototime'])) {
            throw new Exception("Invalid upload request: Missing photo or phototime.");
        }

    return (object) [
            'imagePath'    => $_FILES['photo']['tmp_name'],
            'originalName' => $_FILES['photo']['name'],
            'phototime'    => $_POST['phototime'],
            'uuid'         => isset($_POST['uuid']) ? $_POST['uuid'] : null,
            'uploadDir'    => $destinationDir
        ];
    }
}