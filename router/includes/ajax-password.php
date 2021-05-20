<?php
if (!isset($_POST['sid']) or $_POST['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}

$username = trim(@exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login webui'));
$result = trim(@exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login check ' . $username . ' ' . $_POST['oldPass']));
if ($result == "Match")
{
	$result = @exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login passwd ' . $_POST['newPass'] . ' 2>&1');
	$result = strpos($result, "password updated successfully") > 0 ? 'Successful' : 'Failed';
}
echo $result;
