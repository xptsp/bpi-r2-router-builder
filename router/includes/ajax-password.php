<?php
if (!isset($_POST['sid']) || $_POST['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}

$oldPass = isset($_POST['oldPass']) ? $_POST['oldPass'] : '';
$newPass = isset($_POST['newPass']) ? $_POST['newPass'] : '';
$conPass = isset($_POST['conPass']) ? $_POST['conPass'] : '';
$tmp1 = preg_replace("/[^A-Za-z0-9 ]/", '-', $oldPass);
$tmp2 = preg_replace("/[^A-Za-z0-9 ]/", '-', $newPass);
$tmp3 = preg_replace("/[^A-Za-z0-9 ]/", '-', $conPass);
if ($oldPass != $tmp1)
	$results = "oldPass";
else if ($newPass != $tmp2)
	$results = "newPass";
else if ($newPass != $conPass || $conPass != $tmp3)
	$results = "conPass";
else
{
	if (!isset($_POST['username']))
		$username = !empty($_POST['newPass']) ? trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login webui')) : '';
	else
		$username = isset($_POST['username']) ? $_POST['username'] : '';

	$result = trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login check ' . $username . ' ' . $oldPass));
	if ($result == "Match")
	{
		if (empty($newPass))
			$_SESSION['login_valid_until'] = time() + 10*60;
		else
		{
			$result = @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login passwd ' . $newPass . ' 2>&1');
			$result = strpos($result, "password updated successfully") > 0 ? 'Successful' : 'Failed';
		}
	}
}
echo $result;
