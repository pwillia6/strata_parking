<?php

ob_start();

if (!preg_match('/^api\d+\.php$/', basename($_SERVER['SCRIPT_NAME']))) {
    include '/home/www/parking.cweb.com.au/lib/login.php';
}

ob_end_flush();
