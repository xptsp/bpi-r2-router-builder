<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

# Decide whether the user is logged in or not:
$logged_in = isset($_SESSION['login_valid_until']) and $_SESSION['login_valid_until'] >= time();
if ($logged_in)
	$_SESSION['login_valid_until'] = time() + 600;
else
	unset($_SESSION['login_valid_until']);
#$logged_in = true;

# If no action has been passed, assume we want the basic router status:
$_GET['action'] = (isset($_GET['action']) and $_GET['action'] != '/') ? $_GET['action'] : '/home';
$_GET['action'] = ltrim(preg_replace('/[\s\W]+/', '-', $_GET['action']), '-');

# Include the PHP site framework functions from the "includes" directory:
require_once('includes/subs/site.php');
foreach (glob('includes/plugins/hook-*.php') as $file)
	require_once($file);

# Call any needed functions for the specified action:
$include_file = (file_exists('includes/' . $_GET['action'] . '.php') ? $_GET['action'] : '404');
require_once('includes/' . $include_file . '.php');
