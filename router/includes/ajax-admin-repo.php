<?php
if (!isset($_POST['action']) || !isset($_POST['misc']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

#################################################################################################
# Make sure we know what repo is expected to be actioned upon:
#################################################################################################
$match = array(
	'webui' => '',
	'regdb' => 'wireless-regdb',
);
$misc = isset($match[$_POST['misc']]) ? $match[$_POST['misc']] : $_POST['misc'];

#################################################################################################
# ACTION: CHECK => Returns the current version of the specified repo:
#################################################################################################
if ($_POST['action'] == 'check')
{
	$time = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git remote ' . $misc));
	echo  json_encode(array(
		'elem' => $_POST['misc'],
		'time' => $time ? date('Y.md.Hi', $time) : 'Invalid Data',
	));
}
#################################################################################################
# ACTION: PULL => Updates to the current version of the specified repo:
#################################################################################################
else if ($_POST['action'] == 'pull')
{
	echo trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git update ' . $misc));
	unset($_SESSION[$_POST['misc'] . '_version']);
	unset($_SESSION[$_POST['misc'] . '_version_last']);
}
#################################################################################################
# ACTION: Anything else ==> Return "Unknown action" to user:
#################################################################################################
else
	die("ERROR: Unknown action!");
