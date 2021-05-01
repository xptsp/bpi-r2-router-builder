<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

# If no action has been passed, assume we want the basic router status:
$_GET['action'] = str_replace('/subs-', '/', (isset($_GET['action']) and $_GET['action'] != "/") ? $_GET['action'] : '/basic');

# If website status is being requested, pass "Up" back to caller:
if ($_GET['action'] == '/api/status')
{
	echo 'Up';
	exit();
}

# Include the PHP site framework functions from the "includes" directory:
require_once('includes/subs-site.php');
require_once('includes/subs-login.php');

# Call any needed functions for the specified action:
$include_file = (file_exists('includes/' . $_GET['action'] . '.php') ? $_GET['action'] : '404');
require_once('includes/' . $include_file . '.php');
