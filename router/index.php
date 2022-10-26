<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();
setcookie(session_name(), session_id(), time() + (isset($_SESSION['session_length']) ? $_SESSION['session_length'] : 600));

# If SID is passed and not equal to session variable SID, return "RELOAD" to caller:
if (isset($_POST['sid']) && (empty($_SESSION['sid']) || $_POST['sid'] != $_SESSION['sid']))
	die("RELOAD");
if (isset($_GET['sid']) && (empty($_SESSION['sid']) || $_GET['sid'] != $_SESSION['sid']))
	die("RELOAD");

# If no action has been passed, assume we want the basic router status:
$_GET['action'] = ltrim(preg_replace('/[\s\W]+/', '-', empty($_GET['action']) ? 'home' : ($_GET['action'] != '/' ? $_GET['action'] : 'home')), '-');
$include_js = $_GET['action']  == 'home' ? '' : 'site-' . explode('-', $_GET['action'])[0];

# If the session variable "sid" doesn't exit, redirect to the login page:
$logged_in = isset($_SESSION['sid']);
if ((!$logged_in && $_GET['action'] != 'login') || $_GET['action'] == 'logout')
{
	session_unset();
	session_destroy();
	setcookie(session_name(), false, time() - 3600);
	header('Location: http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/login');
	die();
}

# Set dark mode if not already set:
require_once('includes/subs/site.php');
if (!isset($_SESSION['dark_mode']))
{
	$local = parse_options();
	$_SESSION['dark_mode'] = isset($local['dark_mode']) && $local['dark_mode'] == "Y";
}

# If we are logged in but going to the login page, redirect the page request to the "Home" page:
if ($logged_in && ($_GET['action'] == 'login' || $_GET['action'] == 'logout'))
{
	header('Location: http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/');
	die();
}

# Include the PHP site framework functions from the "includes" directory:
foreach (glob('includes/plugins/*', GLOB_ONLYDIR) as $dir)
{
	foreach (glob($dir . '/hook-*.php') as $file)
		require_once($file);
}

# Call any needed functions for the specified action:
$include_file = (file_exists('includes/' . $_GET['action'] . '.php') ? ($_GET['action'] != 'template' ? $_GET['action'] : '404') : '404');;
require_once('includes/' . $include_file . '.php');
