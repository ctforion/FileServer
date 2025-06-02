<?php
include_once 'includes/config.php';
include_once 'includes/functions.php';
include_once 'includes/json-functions.php';
include_once 'includes/auth-functions.php';
include_once 'includes/log-functions.php';

// Require login
require_login();

// Logout user
logout_user();

// Redirect to home page
redirect_to('index.php');
?>
