<?php

/**
 * A utility class to interact with the OpenAI Vision API.
 * It handles API calls, response parsing, and semaphore locking.
 */
class OpenAiVisionExtractor {

    /**
     * Removes markdown code block fences from a string.
     * @param string $inputString
     * @return string
     */
    private static function removeLinesStartingWithBackticks($inputString) {
        $lines = explode("\n", $inputString);
        $filteredLines = array_filter($lines, function($line) {
            return strpos(trim($line), '```') !== 0;
        });
        return implode("\n", $filteredLines);
    }

    /**
     * Calculates the MD5 checksum of file content.
     * @param string $fileContent
     * @return string
     */
    private static function fileChecksum($fileContent) {
        return md5($fileContent);
    }

    /**
     * Calls the OpenAI Vision API to extract data from an image.
     *
     * @param string $imagePath The path to the image file.
     * @param string $prompt The specific prompt for data extraction.
     * @return object An object containing the extracted data, plus 'raw_result' and 'checksum'.
     * @throws Exception
     */
    public static function extractDataFromImage($imagePath, $prompt) {
        if (!Semaphore::acquire()) {
            throw new Exception("Could not acquire lock to call API.");
        }

        try {
            if (!file_exists($imagePath)) {
                throw new Exception("File not found at $imagePath");
            }

            $fileContent = file_get_contents($imagePath);
            $imageData = base64_encode($fileContent);

            $apiUrl = "https://api.openai.com/v1/chat/completions";
            $payload = [
                "model" => "gpt-4o",
                "messages" => [
                    [
                        "role" => "user",
                        "content" => [
                            ["type" => "text", "text" => $prompt],
                            ["type" => "image_url", "image_url" => (object) ['url' => "data:image/jpeg;base64,$imageData"]]
                        ]
                    ]
                ],
                "max_tokens" => 300
            ];

            $headers = [
                "Content-Type: application/json",
                "Authorization: Bearer " . OPENAI_API_KEY
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            error_log('Response:' . $response);

            if (curl_errno($ch)) {
                throw new Exception("cURL Error: " . curl_error($ch));
            }
            curl_close($ch);

            $result = json_decode($response, true);

            $info = null;
            $jsonContent = null;
            $cleanedJson = null;

            // Check if content is present and attempt to decode it
            if (isset($result['choices'][0]['message']['content']) && !empty(trim($result['choices'][0]['message']['content']))) {
                $jsonContent = $result['choices'][0]['message']['content'];
                $cleanedJson = self::removeLinesStartingWithBackticks($jsonContent);
                $info = json_decode($cleanedJson);
            }

            // If we reach here, $info is a valid object from the decoded JSON.
            // Attach additional useful data
            $info->raw_result = $cleanedJson;
            $info->checksum = self::fileChecksum($fileContent);

            return $info;

        } finally {
            Semaphore::release();
        }
    }
}