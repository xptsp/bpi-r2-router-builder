<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);

# If no action has been passed, assume we want the overview:
$_GET['action'] = isset($_GET['action']) ? $_GET['action'] : '/';

# Include the PHP site framework functions from the "includes" directory:
require_once('includes/site.php');

# Call any needed functions for the specified action:
site_header();
switch ($_GET['action'])
{
	case "/detailed":
		site_menu('Detailed Status');
		require_once('includes/overview.php');
		break;

	case "/login":
		site_login();
		break;

	default:
		site_menu('404 - File Not Found');
		site_404();
}
site_footer();
