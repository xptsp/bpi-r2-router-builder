<?php
if (!isset($_GET['sid']) || $_GET['sid'] != strrev(session_id()))
	require_once("404.php");
else
{
	echo trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh webui update'));
	unset($_SESSION['webui_version']);
	unset($_SESSION['webui_version_last']);
}
