<?php
# Enable error reporting and displaying of errors:
ini_set('display_errors',1);
error_reporting(E_ALL);

# If no action has been passed, assume we want the basic router status:
$_GET['action'] = isset($_GET['action']) ? $_GET['action'] : '/';

# Include the PHP site framework functions from the "includes" directory:
require_once('includes/site.php');

# Call any needed functions for the specified action:
switch ($_GET['action'])
{
	case "/":
		$site_title = 'Basic Status';
		site_header();
		site_menu();
		require_once('includes/basic.php');
		break;

	case "/detailed":
		$site_title = 'Detailed Status';
		site_header();
		site_menu();
		require_once('includes/detailed.php');
		break;

	default:
		$site_title = '404 Error Page';
		site_header();
		site_menu();
		site_404();
}
site_footer();
