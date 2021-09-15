<?php
if (!isset($_GET['sid']) || $_GET['sid'] != $_SESSION['sid'])
	require_once("404.php");
else
{
	echo trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git update wireless-regdb'));
	unset($_SESSION['regdb_version']);
	unset($_SESSION['regdb_version_last']);
}
