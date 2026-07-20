<?php

/**
 * Captures full request diagnostics for an uncaught upload exception and
 * responds with HTTP 200, so the PWA does not treat the failure as a
 * transient error and keep retrying the same upload indefinitely.
 */
class ExceptionLogger {
    public static function handle(Exception $e, $logDir = null) {
        $logDir = $logDir ?: __DIR__ . '/../var/log/';

        $files = array();
        foreach ($_FILES as $field => $file) {
            $content = null;
            if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                $content = base64_encode(file_get_contents($file['tmp_name']));
            }
            $files[$field] = array(
                'name'      => isset($file['name']) ? $file['name'] : null,
                'type'      => isset($file['type']) ? $file['type'] : null,
                'size'      => isset($file['size']) ? $file['size'] : null,
                'error'     => isset($file['error']) ? $file['error'] : null,
                'content_base64' => $content,
            );
        }

        $diagnostics = array(
            'timestamp' => date('c'),
            'exception' => array(
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ),
            'request' => array(
                'method'     => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null,
                'uri'        => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
                'remoteAddr' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                'referer'    => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
                'userAgent'  => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            ),
            'post'  => $_POST,
            'files' => $files,
        );

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $filename = $logDir . 'exceptions_' . date('Y-m-d_His') . '_' . uniqid() . '.json';
        file_put_contents($filename, json_encode($diagnostics, JSON_PRETTY_PRINT));

        error_log($e->getMessage() . ' - diagnostics saved to ' . $filename);

        http_response_code(200);
        echo "Error logged.";
    }
}
