<?php
if (!isset($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}
#echo '<pre>'; print_r($_POST); exit;
require_once("subs/security.php");
$options = parse_file();

#################################################################################################
# ACTION: FIREWALL ==> Update the configuration file using the parameters specified:
#################################################################################################
if ($_POST['action'] == 'firewall')
{
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
