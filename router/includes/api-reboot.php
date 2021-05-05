<?php
if (isset($_GET['sid']) and $_GET['sid'] == strrev(session_id()))
	@exec('/usr/local/bin/router-helper reboot');
else
	require_once("404.php");
