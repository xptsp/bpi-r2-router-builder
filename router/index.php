<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

# If no action has been passed, assume we want the basic router status:
$_GET['action'] = empty($_GET['action']) ? 'home' : ($_GET['action'] != '/' ? $_GET['action'] : 'home');
$_GET['action'] = ltrim(preg_replace('/[\s\W]+/', '-', $_GET['action']), '-');
$include_js = $_GET['action']  == 'home' ? '' : 'site-' . explode('-', $_GET['action'])[0];

# Decide whether the user is logged in or not:
$logged_in = isset($_SESSION['login_valid_until']) and $_SESSION['login_valid_until'] >= time();
if (!$logged_in or $_GET['action'] == 'logout')
	$logged_in = ($_SESSION['login_valid_until'] = 0) != 0;
else
	$_SESSION['login_valid_until'] = time() + 600;

# When not logged in, redirect all page requests to the home page:
if (!$logged_in and !in_array($_GET['action'], array('home', 'ajax-password', 'ajax-basic')))
{
	header('Location: http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/', true, 301);
	die();
}

# Include the PHP site framework functions from the "includes" directory:
require_once('includes/subs/site.php');
foreach (glob('includes/plugins/hook-*.php') as $file)
	require_once($file);

# Call any needed functions for the specified action:
$include_file = (file_exists('includes/' . $_GET['action'] . '.php') ? $_GET['action'] : '404');
require_once('includes/' . $include_file . '.php');
