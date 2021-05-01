<?php
if (isset($_GET['sid']) and $_GET['sid'] == session_id())
{
	@shell_exec('/usr/local/bin/router-helper reboot');
	exit();
}
require_once('404.php');