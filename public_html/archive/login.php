<?php

require __DIR__ . '/OAuth.php';

session_start();

$oauth = OAuth::initialize($_SERVER['REQUEST_URI']);

$oauth->login();

$user = $oauth->user();
if ("$user->email" == "") {
    unset($_SESSION["OAuth"]);
    $oauth = OAuth::initialize($_SERVER['REQUEST_URI']);
    $oauth->login();
}
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
'Management@kingstonquarter.com.au',
'management@kingstonquarter.com.au'];


if (in_array($email, $members)) {
    ;
} else {
    echo "Email $email does not have access\n";
    error_log("OAUTH: Email $email does not have access");
    exit;
}

// After successful login, redirect to the original page stored in the session,
// or to a default page if it's not set.
$redirect_url = isset($_SESSION['login_redirect_url']) ? $_SESSION['login_redirect_url'] : 'offend.php';

// Prevent redirecting back to login.php in a loop
if (basename($redirect_url) === 'login.php') {
    header("Location: offend.php");
} else {
    header("Location: " . $redirect_url);
}
