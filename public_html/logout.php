<?php

require_once __DIR__ . '/../lib/OAuth.php';

session_start();

session_destroy();

header('Location: login.php');
exit;