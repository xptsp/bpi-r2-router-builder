<?php
if (!isset($_POST['sid']) or $_POST['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}

$username = trim(@exec('/usr/local/bin/router-helper login webui'));
$result = trim(@exec('/usr/local/bin/router-helper login check ' . $username . ' ' . $_POST['oldPass']));
if ($result == "Match")
{
	$result = @exec('/usr/local/bin/router-helper login passwd ' . $_POST['newPass'] . ' 2>&1');
	$result = strpos($result, "password updated successfully") > 0 ? 'Successful' : 'Failed';
}
echo $result;
