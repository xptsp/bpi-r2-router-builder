<?php
if (!isset($_POST['sid']) || $_POST['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}

if (!isset($_POST['username']))
	$username = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login webui'));
else
	$username = $_POST['username'];
$result = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login check ' . $username . ' ' . $_POST['oldPass']));
if ($result == "Match")
{
	if (!isset($_POST['newPass']))
		$_SESSION['login_valid_until'] = time() + 10*60;
	else
	{
		$result = @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login passwd ' . $_POST['newPass'] . ' 2>&1');
		$result = strpos($result, "password updated successfully") > 0 ? 'Successful' : 'Failed';
	}
}
echo $result;
