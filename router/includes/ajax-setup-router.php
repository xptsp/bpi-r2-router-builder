<?php
#$_POST['action'] = 'detect';
#$_POST['sid'] = $_SESSION['sid'];
if (!isset($_POST['action']) || !isset($_POST['sid']))
	require_once("404.php");
if ($_POST['sid'] != $_SESSION['sid'])
	die('RELOAD');
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
	$_POST['mac'] = isset($_POST['mac']) ? $_POST['mac'] : '';
	if (!filter_var($_POST['mac'], FILTER_VALIDATE_MAC))
		die('[MAC] ERROR: "' . $_POST['mac'] . '" is an invalid MAC address!');

	$_POST['timezone'] = isset($_POST['timezone']) ? $_POST['timezone'] : '';
	$_POST['locale'] = isset($_POST['locale']) ? $_POST['locale'] : '';
	$_POST['hostname'] = isset($_POST['hostname']) ? $_POST['hostname'] : '';

	if (!preg_match("/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/", $_POST['hostname']))
		die("[HOSTNAME] ERROR: " . $_POST['hostname'] . " is not a valid hostname");
	if (empty($_POST['timezone']))
		die("[TIMEZONE] ERROR: Timezone not specified!");
	if (empty($_POST['locale']))
		die("[LOCALE] ERROR: Locale not specified!");

	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh mac " . $_POST['mac']);
	echo @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh device " . $_POST['hostname'] . ' ' . $_POST['timezone'] . ' ' . $_POST['locale']);
}
#################################################################################################
# ACTION: Anything Else ==> Report unknown action to caller:
#################################################################################################
else
	die("ERROR: Unknown action!");
