<?php
if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
@exec('/usr/local/bin/router-helper reboot');
