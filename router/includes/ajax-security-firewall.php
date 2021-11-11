<?php
if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

#################################################################################################
# Returns "Y" if option is "true"
#################################################################################################
function option($name, $default = '')
{
	global $options;
	return !empty($_POST[$name]) ? $_POST[$name] : (isset($options[$name]) ? $options[$name] : $default);
}

#################################################################################################
# Update the configuration file using the parameters specified:
#################################################################################################
#echo '<pre>'; print_r($_POST); exit;
$file = '/etc/default/firewall';
$options = array();
foreach (explode("\n", trim(@file_get_contents($file))) as $line)
{
	$parts = explode("=", $line . '=');
	if (!empty($parts[0]))
		$options[$parts[0]] = $parts[1];
}
$options['drop_port_scan'] = option('drop_port_scan');
$options['log_port_scan']  = option('log_port_scan');
$options['log_udp_flood']  = option('log_udp_flood');
$options['drop_ping']      = option('drop_ping');
$options['drop_ident']     = option('drop_ident');
$options['drop_multicast'] = option('drop_multicast');
#echo '<pre>'; print_r($options); exit;

#################################################################################################
# Assemble the new configuration file:
#################################################################################################
$text = '';
foreach ($options as $name => $setting)
	$text .= (!empty($setting) ? $name . '=' . $setting . "\n" : '');
#echo '<pre>'; echo $text; exit;
$handle = fopen("/tmp/firewall", "w");
fwrite($handle, $text);
fclose($handle);

#################################################################################################
# Reload the iptable rules as specified by the new WebUI configuration file:
#################################################################################################
echo @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh firewall reload");
