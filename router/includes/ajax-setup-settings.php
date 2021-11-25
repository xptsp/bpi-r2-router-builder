<?php
if (!isset($_POST['action']) || !isset($_POST['sid']))
	require_once("404.php");
if ($_POST['sid'] != $_SESSION['sid'])
	die('RELOAD');
require_once("subs/setup.php");
#echo '<pre>'; print_r($_POST); exit;

#################################################################################################
# ACTION: DETECT ==> Detect where the machine is, according to "http://ipinfo.io":
#################################################################################################
if ($_POST['action'] == 'detect')
{
	if (!isset($_SESSION['ipinfo']['time']) || $_SESSION['ipinfo']['time'] > time())
	{
		$_SESSION['ipinfo']['arr'] = array();
		foreach (explode("\n", trim(@shell_exec("curl ipinfo.io"))) as $line)
		{
			if (preg_match("/\"(.*)\"\:\s\"(.*)\"/", $line, $matches))
				$_SESSION['ipinfo']['arr'][$matches[1]] = $matches[2];
		}
		$_SESSION['ipinfo']['time'] = time() + 600;
	}
	echo json_encode($_SESSION['ipinfo']['arr']);
}
#################################################################################################
# ACTION: SET ==> Set the timezone and hostname of the system:
#################################################################################################
else if ($_POST['action'] == 'set')
{
	$mac = option_mac('mac');
	$timezone = option_allowed('timezone', array_keys(timezone_list()) );
	$locale = option_allowed('locale', array_keys(get_os_locales()) );
	$hostname = option('hostname', "/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/");
	die("GOT HERE");

	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh mac " . $mac);
	echo @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh device ' . $hostname . ' ' . $timezone . ' ' . $locale);
}
#################################################################################################
# ACTION: SET ==> Set the timezone and hostname of the system:
#################################################################################################
else if ($_POST['action'] == 'dns')
{
	$use_isp = option('use_isp');
	$dns1 = option_ip('dns1');
	$dns2 = option_ip('dns2', true);
	die("GOT HERE");
}
#################################################################################################
# ACTION: Anything Else ==> Report unknown action to caller:
#################################################################################################
else
	die("ERROR: Unknown action!");
