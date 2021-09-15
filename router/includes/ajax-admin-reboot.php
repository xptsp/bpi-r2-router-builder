<?php
if (!isset($_GET['sid']) || $_GET['sid'] != $_SESSION['sid'])
	require_once("404.php");
else
	@exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh reboot');
