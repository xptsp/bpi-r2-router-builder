<?php
if (!isset($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

#################################################################################################
# Supporting function(s)
#################################################################################################
function parse_file()
{
	$file = '/etc/default/firewall';
	$options = array();
	foreach (explode("\n", trim(@file_get_contents($file))) as $line)
	{
		$parts = explode("=", $line . '=');
		if (!empty($parts[0]))
			$options[$parts[0]] = $parts[1];
	}
	return $options;
}

function option($name, $allowed = "/^[Y|N]$/")
{
	global $options;
	if (!isset($_POST[$name]) || (!empty($allowed) && !preg_match($allowed, $_POST[$name])))
		die('ERROR: Missing or invalid value for option "' . $name . '"!');
	return $_POST[$name];
}

function apply_file()
{
	global $options;
	$text = '';
	foreach ($options as $name => $setting)
		$text .= (!empty($setting) ? $name . '=' . $setting . "\n" : '');
	#echo '<pre>'; echo $text; exit;
	$handle = fopen("/tmp/firewall", "w");
	fwrite($handle, $text);
	fclose($handle);
	echo @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh firewall reload");
}

#################################################################################################
# ACTION: FIREWALL ==> Update the configuration file using the parameters specified:
#################################################################################################
if ($_POST['action'] == 'firewall')
{
	#echo '<pre>'; print_r($_POST); exit;
	$options = parse_file();
	$options['drop_port_scan'] = option('drop_port_scan');
	$options['log_port_scan']  = option('log_port_scan');
	$options['log_udp_flood']  = option('log_udp_flood');
	$options['drop_ping']      = option('drop_ping');
	$options['drop_ident']     = option('drop_ident');
	$options['drop_multicast'] = option('drop_multicast');
	#echo '<pre>'; print_r($options); exit;
	apply_file();
}
else
	die("ERROR: Unknown action!");
