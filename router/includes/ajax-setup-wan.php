<?php
if (!isset($_POST['sid']) || $_POST['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}

#################################################################################################
# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
#################################################################################################
$err = '';
if (!isset($_POST['ip_addr']) || !filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	$err = '[IP_ADDR] ERROR: "' . $_POST['ip_addr'] . '" is an invalid IPv4 address!';
if (!isset($_POST['ip_mask']) || !filter_var($_POST['ip_mask'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	$err = '[IP_MASK] ERROR: "' . $_POST['ip_mask'] . '" is an invalid IPv4 address!';
if (!isset($_POST['ip_gate']) || !filter_var($_POST['ip_gate'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	$err = '[IP_GATE] ERROR: "' . $_POST['ip_gate'] . '" is an invalid IPv4 address!';
if (!isset($_POST['dns2']) || (!empty($_POST['dns2']) && !filter_var($_POST['dns2'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)))
	$err = '[DNS2] ERROR: "' . $_POST['dns2'] . '" is an invalid IPv4 address!';
if (!isset($_POST['mac']) || !filter_var($_POST['mac'], FILTER_VALIDATE_MAC))
	$err = '[MAC] ERROR: "' . $_POST['mac'] . '" is an invalid MAC address!';
if (!isset($_POST['static']) || !is_numeric($_POST['static']) || $_POST['static'] < 0 || $_POST['static'] > 1)
	$err = '[STATIC] ERROR: "' . $_POST['static'] . '" is an invalid value!';

# Our local CloudFlare DoH "servers" use "127.0.0.1#" and a port number.  Validate each part seperately:
$parts = explode("#", isset($_POST['ip_addr']) ? $_POST['dns1'] : '');
if (!filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	$err = '[DNS1] ERROR: "' . $parts[0] . '" is an invalid IPv4 address!';
if (isset($parts[1]) && (!is_numeric($parts[1]) || $parts[1] < 5051 || $parts[1] > 5053))
	$err = '[DNS1] ERROR: "' . $parts[1] . '" is an invalid port number!';

# If we have an error message, return it to the user and abort:
if (!empty($err))
	die($err);
#echo '<pre>'; print_r($_POST); echo '</pre>';

#################################################################################################
# Output the network adapter configuration to the "/tmp" directory:
#################################################################################################
$text = 
'auto wan
iface wan inet ' . (!empty($_POST['static']) ? 'static' : 'dhcp') . '
    hwaddress ether ' . strtolower($_POST['mac']) . (!empty($_POST['static']) ? '
    address ' . $_POST['ip_addr'] . '
    netmask ' . $_POST['ip_mask'] . '
    post-up route add default gw ' . $_POST['ip_gate'] . '
    pre-down route del default gw ' . $_POST['ip_gate'] : '') . '
    post-up echo 6 > /sys/class/net/wan/queues/rx-0/rps_cpus
';
$handle = fopen("/tmp/wan", "w");
fwrite($handle, $text);
fclose($handle);

#################################################################################################
# Change the DNS servers by calling the router-helper script:
#################################################################################################
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh move_config wan");
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dns " . $_POST['dns1'] . " " . $_POST['dns2']);


