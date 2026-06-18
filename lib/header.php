<?php

ob_start();

require_once __DIR__ . '/config.php';

/**
 * Simple autoloader for classes in the /lib directory.
 * Class names must match their file names (e.g., class Database -> Database.php).
 */
spl_autoload_register(function ($className) {
    $file = __DIR__ . '/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});


    // API endpoints are exempt from login checks only for POST requests.
    // This allows unauthenticated photo uploads from the PWA.
    // GET requests to the same endpoints (for reprocessing) will require a login.
    $scriptName = basename($_SERVER['SCRIPT_NAME']);
    $isApiPostRequest = preg_match('/^api_.*\.php$/', $scriptName) && $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$isApiPostRequest) {
    include __DIR__ . '/login.php';
}

ob_end_flush();