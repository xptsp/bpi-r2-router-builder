<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

# If SID is passed and not equal to session variable SID, return "RELOAD" to caller:
if (isset($_POST['sid']) && (empty($_SESSION['sid']) || $_POST['sid'] != $_SESSION['sid']))
	die("RELOAD");
if (isset($_GET['sid']) && (empty($_SESSION['sid']) || $_GET['sid'] != $_SESSION['sid']))
	die("RELOAD");

# If no action has been passed, assume we want the basic router status:
$_GET['action'] = empty($_GET['action']) ? 'home' : ($_GET['action'] != '/' ? $_GET['action'] : 'home');
$_GET['action'] = ltrim(preg_replace('/[\s\W]+/', '-', $_GET['action']), '-');
$include_js = $_GET['action']  == 'home' ? '' : 'site-' . explode('-', $_GET['action'])[0];
$include_js = $include_js == 'site-ajax' ? '' : $include_js;

# Decide whether the user is logged in or not:
$logged_in = isset($_SESSION['login_valid_until']) && $_SESSION['login_valid_until'] >= time();

# If we are not logged and not using the login page, check to see if the "remember_me" cookie is valid:
if (!$logged_in && isset($_COOKIE["remember_me"]) && isset($_SESSION['sid']))
{
	if ($logged_in = ($_COOKIE["remember_me"] == $_SESSION['sid']))
		setcookie("remember_me", $_COOKIE["remember_me"] = $_SESSION['sid'], time() + $_SESSION['session_length']);
}

# If user is logging out OR if the "sid" session variable isn't set, do the logout routine:
if (!$logged_in || $_GET['action'] == 'logout')
{
	$logged_in = ($_SESSION['login_valid_until'] = 0) != 0;
	setcookie("remember_me", false, time() - 3600);
	unset($_COOKIE["remember_me"]);
}

# Set dark mode if not already set:
require_once('includes/subs/site.php');
if (!isset($_SESSION['dark_mode']))
	$_SESSION['dark_mode'] = parse_options()['dark_mode'] == "Y";

# Remove the session ID if not logged in.  Otherwise, extend the session time for another 10 minutes:
if (!$logged_in)
	unset($_SESSION['sid']);
else
	$_SESSION['login_valid_until'] = time() + $_SESSION['session_length'];

# If we are not logged it, redirect all page requests to the "Login" page:
if (!$logged_in && $_GET['action'] != 'login')
{
	header('Location: http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/login', true, 301);
	die();
}
# If we are logged in but going to the login page, redirect the page request to the "Home" page:
else if ($logged_in && $_GET['action'] == 'login')
{
	header('Location: http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/', true, 301);
	die();
}

# Generate a random SID for use in the session:
if (!isset($_SESSION['sid']))
	$_SESSION['sid'] = substr(bin2hex(random_bytes(32)), 0, 32);
#echo $_SESSION['sid']; exit;

# Include the PHP site framework functions from the "includes" directory:
foreach (glob('includes/plugins/*', GLOB_ONLYDIR) as $dir)
{
	foreach (glob($dir . '/hook-*.php') as $file)
		require_once($file);
}

# Call any needed functions for the specified action:
$include_file = (file_exists('includes/' . $_GET['action'] . '.php') ? ($_GET['action'] != 'template' ? $_GET['action'] : '404') : '404');;
require_once('includes/' . $include_file . '.php');
