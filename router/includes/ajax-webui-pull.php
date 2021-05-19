<?php
if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
	require_once("404.php");
else
{
	echo trim(@shell_exec('/usr/local/bin/router-helper webui update'));
	unset($_SESSION['webui_version_last']);
}
