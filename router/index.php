<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

# Change this variable to disable login prompt if not already set for session:
if (!isset($_SESSION['suppress_login']))
	$_SESSION['suppress_login'] = false;

# If no action has been passed, assume we want the basic router status:
$_GET['action'] = (isset($_GET['action']) and $_GET['action'] != '/') ? $_GET['action'] : '/basic';
$_GET['action'] = preg_replace('/^subs-/', '', ltrim(preg_replace('/[\s\W]+/', '-', $_GET['action']), '-'));
#echo $_GET['action']; exit();

# Include the PHP site framework functions from the "includes" directory:
require_once('includes/subs-site.php');
if (substr($_GET['action'], 0, 4) != 'api-' or !isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
	require_once('includes/subs-login.php');

# Call any needed functions for the specified action:
$include_file = (file_exists('includes/' . $_GET['action'] . '.php') ? $_GET['action'] : '404');
require_once('includes/' . $include_file . '.php');
