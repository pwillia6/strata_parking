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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SP98937 Parking Application - Login</title>
        <link rel="stylesheet" href="tailwind.css">
    </head>
    <body class="font-sans bg-gradient-to-br from-[#667eea] to-[#764ba2] flex items-center justify-center min-h-screen m-0">
        <div class="bg-white p-10 rounded-xl shadow-2xl text-center w-full max-w-sm">
            <h1 class="text-2xl font-semibold text-slate-800 mb-2">SP98937 Parking Application</h1>
            <h2 class="text-slate-500 font-normal mb-8">Please sign in</h2>
            <a href="?provider=google" class="flex items-center justify-center px-5 py-3 my-2.5 text-white no-underline rounded-md font-medium transition-colors bg-[#DB4437] hover:bg-[#C33D2E]">Sign in with Google</a>
            <a href="?provider=microsoft" class="flex items-center justify-center px-5 py-3 my-2.5 text-white no-underline rounded-md font-medium transition-colors bg-[#0078D4] hover:bg-[#005A9E]">Sign in with Microsoft</a>
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
'curtis@sydneybmp.com.au'];


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
