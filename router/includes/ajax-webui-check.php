<?php
if (!isset($_GET['sid']) || $_GET['sid'] != strrev(session_id()))
	require_once("404.php");
else
	echo  json_encode(array(
		'remote_ver' => date('Y.md.Hi', (int) trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh webui remote'))),
	));
