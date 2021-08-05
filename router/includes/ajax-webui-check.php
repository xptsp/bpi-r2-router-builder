<?php
if (!isset($_GET['sid']) || $_GET['sid'] != $_SESSION['sid'])
	require_once("404.php");
else
	echo  json_encode(array(
		'webui_remote' => date('Y.md.Hi', (int) trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git remote'))),
		'regdb_current' => date('Y.md.Hi', (int) trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git current wireless-regdb'))),
		'regdb_remote' => date('Y.md.Hi', (int) trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh git remote wireless-regdb'))),
	));
