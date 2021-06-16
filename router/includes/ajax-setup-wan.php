<?php
if (!isset($_POST['sid']) || $_POST['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
echo "OK";
