<?php

require_once __DIR__ . '/config.php';
require __DIR__ . '/OAuth.php';

session_start();

// Check if a provider is selected (e.g., from a login button click)
$provider = isset($_GET['provider']) ? $_GET['provider'] : null;

if ($provider) {
    // A provider was chosen, set it in the session and redirect to start the login flow.
    OAuth::setProvider($provider);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Initialize OAuth object. It will be false if no provider has been set.
$oauth = OAuth::initialize($_SERVER['PHP_SELF']);

if ($oauth === false) {
    // User is not logged in and has not chosen a provider.
    // Display the login choice page.
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>SP98937 Parking Application - Login</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .login-container {
                background-color: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                text-align: center;
                width: 100%;
                max-width: 400px;
            }
            .app-title {
                font-size: 24px;
                font-weight: 600;
                color: #333;
                margin-bottom: 10px;
            }
            .login-container h2 {
                color: #555;
                margin-bottom: 30px;
                font-weight: 400;
            }
            .login-button {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 12px 20px;
                margin: 10px 0;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: 500;
                transition: background-color 0.3s ease;
                border: none;
                cursor: pointer;
            }
            .google { background-color: #DB4437; }
            .google:hover { background-color: #C33D2E; }
            .microsoft { background-color: #0078D4; }
            .microsoft:hover { background-color: #005A9E; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1 class="app-title">SP98937 Parking Application</h1>
            <h2>Please sign in</h2>
            <a href="?provider=google" class="login-button google">Sign in with Google</a> 
            <a href="?provider=microsoft" class="login-button microsoft">Sign in with Microsoft</a>
        </div>
    </body>
    </html>
    <?php
    exit; // Stop the script until a provider is chosen
}

// If we are here, a provider has been chosen.
// Check if we are in the middle of an OAuth callback or if the user is already logged in.
if (!isset($_GET['code']) && !$oauth->loggedIn()) {
    $oauth->login(); // This will redirect the user to the provider's login page
    exit;
}

$oauth->login();


$user = $oauth->user();
error_log("OAUTH User Details (base64): " . base64_encode(print_r($user, true)));
//print_r($user); exit;

$email = strtolower($user->email);


$members = ['admin@sc.kingstonquarter.com.au',
'edwinmaurice1@gmail.com',
'kingstonquarterau@gmail.com',
'paul.strata@williamson.bike',
'paul@completewebservices.com.au',
'pwillia6@gmail.com',
'sleemeehan@gmail.com',
'tesaro@sandu.com.au',
'tesaro.sandu@gmail.com',
'wirzie@gmail.com',
'markandrobert2130@gmail.com',
'michaelinelee@gmail.com',
'lipakshidas@gmail.com',
'Management@kingstonquarter.com.au',
'management@kingstonquarter.com.au',
'ricky@sydneybmp.com.au'];


if (in_array($email, $members)) {
    ;
} else {
    echo "Email $email does not have access\n";
    error_log("OAUTH: Email $email does not have access");
    exit;
}

if ($_SERVER['PHP_SELF']=='/login.php') {
    header("Location: offend.php");
}
