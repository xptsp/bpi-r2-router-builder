<?php
if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
$results = explode(",", trim(@shell_exec('/usr/local/bin/router-helper webui check')));
echo  json_encode(array(
	'remote_ver' => date('Y.md.Hi', $results[0]),
));
