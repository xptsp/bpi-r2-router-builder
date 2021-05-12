<?php
if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
$result = trim(@shell_exec('/usr/local/bin/router-helper apt update | grep "packages"'));
$updates = 0;
if (preg_match("/(\d+) packages/", $result, $regex))
	$updates = $regex[1];
echo json_encode(array('updates' => $updates));