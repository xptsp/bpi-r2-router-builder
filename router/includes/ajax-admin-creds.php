<?php
####################################################################################
# Supporting function:
####################################################################################
function word($file)
{
	global $num;
	$lines = file('/usr/share/dict/' . $file . '.list');
	$max = count($lines);
	$word = explode("'", trim(ucfirst( $lines[ rand(0, $max) ] )))[0];
	return $word;
}

####################################################################################
# If "SID" variable not specified, generate a new password:
####################################################################################
if (!isset($_GET['sid']) || $_GET['sid'] != $_SESSION['sid'])
	die(echo word('adjectives') . word('animals') . strval(rand(0,100)));

####################################################################################
# Start validating the password parameters sent:
####################################################################################
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
		$username = isset($_POST['newPass']) ? '' : trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh login webui'));
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
