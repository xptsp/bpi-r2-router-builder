<?php
if (!isset($_POST['action']) || !isset($_POST['sid']))
	require_once("404.php");
if ($_POST['sid'] != $_SESSION['sid'])
	die('RELOAD');
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
#################################################################################################
# ACTION: DMZ ==> Update the configuration file using the parameters specified:
#################################################################################################
if ($_POST['action'] == 'dmz')
{
	$options['enable_dmz'] = option('enable_dmz');
	$options['dmz_src_type'] = option_allowed('src_type', array('any', 'range', 'mask'));
	unset($options['dmz_range_from'], $options['dmz_range_to'], $options['dmz_mask_ip'], $options['dmz_mask_bits']);
	if ($options['dmz_src_type'] == 'range')
	{
		$options['dmz_range_from'] = option_ip('range_from');
		$options['dmz_range_to'] = option_range('range_to', 0, 255);
	}
	else if ($options['dmz_src_type'] == 'mask')
	{
		$options['dmz_mask_ip'] = option_ip('mask_ip');
		$options['dmz_mask_bits'] = option_range('mask_bits', 0, 32);
	}
	$option['dmz_dest_type'] = option_allowed('dest_type', array('addr', 'mac'));
	unset($options['dmz_mac_addr'], $options['dmz_ip_addr']);
	if ($option['dmz_dest_type'] == 'addr')
		$options['dmz_ip_addr'] = option_ip('dest_ip');
	else
		$options['dmz_mac_addr'] = option_mac('dest_mac');
	#echo '<pre>'; print_r($options); exit;
	apply_file();
}
#################################################################################################
# ACTION: Anything Else ==> Report unknown action to caller:
#################################################################################################
else
	die("ERROR: Unknown action!");
