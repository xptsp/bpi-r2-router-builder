<?php
#if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
#{
#	require_once("404.php");
#	exit();
#}
$results = explode(",", trim(@shell_exec('/usr/local/bin/router-helper webui check')));
echo  json_encode(array(
	'local_ver' => date('Y.md.Hi', $results[0]),
	'remote_ver' => date('Y.md.Hi', $results[1]),
	'status' => $results[2],
), true);
