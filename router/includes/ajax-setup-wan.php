<?php
if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

#################################################################################################
# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
#################################################################################################
$_POST['static'] = isset($_POST['static']) ? $_POST['static'] : '';
if (!is_numeric($_POST['static']) || $_POST['static'] < 0 || $_POST['static'] > 1)
	die('[STATIC] ERROR: "' . $_POST['static'] . '" is an invalid value!');
else if ($_POST['static'] == 1)
{
	$_POST['ip_addr'] = isset($_POST['ip_addr']) ? $_POST['ip_addr'] : '';
	if (!filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[IP_ADDR] ERROR: "' . $_POST['ip_addr'] . '" is an invalid IPv4 address!');

	$_POST['ip_mask'] = isset($_POST['ip_mask']) ? $_POST['ip_mask'] : '';
	if (!filter_var($_POST['ip_mask'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[IP_MASK] ERROR: "' . $_POST['ip_mask'] . '" is an invalid IPv4 address!');

	$_POST['ip_gate'] = isset($_POST['ip_gate']) ? $_POST['ip_gate'] : '';
	if (!filter_var($_POST['ip_gate'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[IP_GATE] ERROR: "' . $_POST['ip_gate'] . '" is an invalid IPv4 address!');
}

$_POST['use_isp'] = isset($_POST['use_isp']) ? $_POST['use_isp'] : '';
if (!is_numeric($_POST['use_isp']) || $_POST['use_isp'] < 0 || $_POST['use_isp'] > 1)
	die('[USE_ISP] ERROR: "' . $_POST['use_isp'] . '" is an invalid value!');
else if ($_POST['use_isp'] == 1)
{
	$_POST['dns1'] = isset($_POST['dns1']) ? $_POST['dns1'] : '';
	if (!empty($_POST['dns1']) && !filter_var($_POST['dns1'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[DNS2] ERROR: "' . $_POST['dns2'] . '" is an invalid IPv4 address!');

	$_POST['dns2'] = isset($_POST['dns2']) ? $_POST['dns2'] : '';
	if (!empty($_POST['dns2']) && !filter_var($_POST['dns2'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[DNS2] ERROR: "' . $_POST['dns2'] . '" is an invalid IPv4 address!');
}

$_POST['mac'] = isset($_POST['mac']) ? $_POST['mac'] : '';
if (!filter_var($_POST['mac'], FILTER_VALIDATE_MAC))
	die('[MAC] ERROR: "' . $_POST['mac'] . '" is an invalid MAC address!');

#echo '<pre>'; print_r($_POST); echo '</pre>'; exit;

#################################################################################################
# Output the network adapter configuration to the "/tmp" directory:
#################################################################################################
$text = 
'auto wan
iface wan inet ' . (empty($_POST['static']) ? 'dhcp' : 'static
    address ' . $_POST['ip_addr'] . '
    netmask ' . $_POST['ip_mask'] . '
    gateway ' . $_POST['ip_gate']) . '
    post-up echo 6 > /sys/class/net/wan/queues/rx-0/rps_cpus' . (!empty($_POST['use_isp']) ? '
    post-up echo nameserver ' . $_POST['dns1'] . ' > /etc/resolv.conf
    post-up echo nameserver ' . $_POST['dns2'] . ' >> /etc/resolv.conf' : '') . '
';
$handle = fopen("/tmp/wan", "w");
fwrite($handle, $text);
fclose($handle);

#################################################################################################
# Change the DNS servers by calling the router-helper script:
#################################################################################################
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh move_config wan");
echo (@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh mac " . $_POST['mac']) == "REBOOT" ? "REBOOT" : "OK");

