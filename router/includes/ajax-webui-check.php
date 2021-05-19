<?php
if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
echo  json_encode(array(
	'remote_ver' => date('Y.md.Hi', (int) trim(@shell_exec('/usr/local/bin/router-helper webui remote'))),
));
