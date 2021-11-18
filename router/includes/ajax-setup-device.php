<?php
$_POST['action'] = 'detect';
$_POST['sid'] = $_SESSION['sid'];
if (!isset($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}
#echo '<pre>'; print_r($_POST); exit;

#################################################################################################
# ACTION: DETECT ==> Detect where the machine is, according to "http://ipinfo.io":
#################################################################################################
if ($_POST['action'] == 'detect')
{
	$arr = array();
	foreach (explode("\n", trim(@shell_exec("curl ipinfo.io"))) as $line)
	{
		if (preg_match("/\"(.*)\"\:\s\"(.*)\"/", $line, $matches))
			$arr[$matches[1]] = $matches[2];
	}
	echo json_encode($arr);
}
#################################################################################################
# ACTION: SET ==> Set the timezone and hostname of the system:
#################################################################################################
else if ($_POST['action'] == 'set')
{
	$_POST['hostname'] = isset($_POST['hostname']) ? $_POST['hostname'] : '';
	if (!preg_match("/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/", $_POST['hostname']))
		die("[HOSTNAME] ERROR: " . $_POST['hostname'] . " is not a valid hostname");
}
#################################################################################################
# ACTION: Anything Else ==> Report unknown action to caller:
#################################################################################################
else
	die("ERROR: Unknown action!");
