<?php
if (!isset($_POST['sid']))
	require_once("404.php");
if ($_POST['sid'] != $_SESSION['sid'])
	die('RELOAD');

#echo '<pre>'; print_r($_POST); exit();

#################################################################################################
# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
#################################################################################################
$_POST['iface'] = isset($_POST['iface']) ? $_POST['iface'] : '';
if (empty($_POST['iface']) || !file_exists("/sys/class/net/" . $_POST['iface']))
	die('[IFACE] ERROR: "' . $_POST['iface'] . '" is not a valid network interface!');

$_POST['ip_addr'] = isset($_POST['ip_addr']) ? $_POST['ip_addr'] : '';
if (!filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	die('[IP_ADDR] ERROR: "' . $_POST['ip_addr'] . '" is an invalid IPv4 address!');

$_POST['ip_mask'] = isset($_POST['ip_mask']) ? $_POST['ip_mask'] : '';
if (!filter_var($_POST['ip_mask'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	die('[IP_MASK] ERROR: "' . $_POST['ip_mask'] . '" is an invalid IPv4 address!');

$_POST['reboot'] = isset($_POST['reboot']) ? $_POST['reboot'] : '';
if (!preg_match("/^(true|false)$/", $_POST['reboot']))
	die("[REBOOT] ERROR: " . $_POST['reboot'] . " is not a valid boolean");

#################################################################################################
# If using DHCP on this interface, make sure addresses are valid:
#################################################################################################
if (!empty($_POST['use_dhcp']))
{
	$_POST['dhcp_start'] = isset($_POST['dhcp_start']) ? $_POST['dhcp_start'] : '';
	if (!filter_var($_POST['dhcp_start'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[DHCP_START] ERROR: "' . $_POST['dhcp_start'] . '" is an invalid IPv4 address!');

	$_POST['dhcp_end'] = isset($_POST['dhcp_end']) ? $_POST['dhcp_end'] : '';
	if (!filter_var($_POST['dhcp_end'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[DHCP_END] ERROR: "' . $_POST['dhcp_end'] . '" is an invalid IPv4 address!');

	#################################################################################################
	# Make sure the IP address is held within the DHCP address range:
	#################################################################################################
	$ip_addr    = implode('.', explode(".", substr($_POST['ip_addr'], 0, strrpos($_POST['ip_addr'], '.'))));
	if ($ip_addr != implode('.', explode(".", substr($_POST['dhcp_start'], 0, strrpos($_POST['dhcp_start'], '.')))))
		die('[DHCP_START] ERROR: Starting IP Address needs to start with "' . $ip_addr . '"!');

	if ($ip_addr != implode('.', explode(".", substr($_POST['dhcp_end'], 0, strrpos($_POST['dhcp_end'], '.')))))
		die('[DHCP_END] ERROR: Starting IP Address needs to start with "' . $ip_addr . '"!');

	#################################################################################################
	# Make sure the client lease time is valid:
	#################################################################################################
	$_POST['dhcp_lease'] = isset($_POST['dhcp_lease']) ? $_POST['dhcp_lease'] : '';
	if ($_POST['dhcp_lease'] != "infinite")
	{
		if (!preg_match("/(\d+)(m|h|d|w|)/", $_POST['dhcp_lease'], $parts))
			die('[DHCP_LEASE] ERROR: Invalid client lease time "' . $_POST['dhcp_lease'] . '"!');
		if ($parts[2] == '' && (int) $parts[1] < 120)
			die('[DHCP_LEASE] ERROR: Minimum client lease time is 120 seconds!!');
		else if ($parts[2] == 'm' && (int) $parts[1] < 2)
			die('[DHCP_LEASE] ERROR: Minimum client lease time is 2 minutes!!');
		else if ((int) $parts[1] < 1)
			die('[DHCP_LEASE] ERROR: Minimum client lease time is 1 ' . ($parts[1] == 'h' ? 'hour' : $parts[1] == 'd' ? 'day' : 'week') . '!!');
		#echo '<pre>'; print_r($parts); exit;
	}
}

#################################################################################################
# Create the network configuration for each of the bound network adapters:
#################################################################################################
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface delete " . $_POST['iface']);
$bridged = explode(" ", trim($_POST['bridge']));
if (empty($bridged))
	die("[BRIDGE] ERROR: No interfaces specified in bridge configuration!");
$text =
'allow-hotplug {iface}
auto {iface}
iface {iface} inet manual';
if (count($bridged) > 1)
{
	foreach ($bridged as $IFACE)
	{
		$handle = fopen("/tmp/" . $IFACE, "w");
		fwrite($handle, str_replace('{iface}', $IFACE, $text));
		fclose($handle);
		@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $IFACE);
	}
	if (substr($_POST['iface'], 0, 2) != "br")
		$_POST['iface'] = 'br' . strval( intval(str_replace("/etc/network/interfaces.d/br", "", trim(@shell_exec("ls /etc/network/interfaces.d/br* | sort | tail -1")))) + 1 );
}
else if (substr($_POST['iface'], 0, 2) == "br")
	$_POST['iface'] = $bridged[0];

#################################################################################################
# Output the network adapter configuration to the "/tmp" directory:
#################################################################################################
$text =
'auto ' . $_POST['iface'] . '
iface ' . $_POST['iface'] . ' inet static
    address ' . $_POST['ip_addr'] . '
    netmask ' . $_POST['ip_mask'] . (!empty($_POST['bridge']) ? '
    bridge_ports ' . trim($_POST['bridge']) . '
    bridge_fd 5
    bridge_stp no
    post-up echo 8 > /sys/class/net/' . $_POST['iface'] . '/queues/rx-0/rps_cpus' : '') . '
';
#echo '<pre>'; echo $text; exit;
$handle = fopen("/tmp/" . $_POST['iface'], "w");
fwrite($handle, $text);
fclose($handle);
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $_POST['iface']);

#################################################################################################
# Output the DNSMASQ configuration file related to the network adapter:
#################################################################################################
if (!empty($_POST['use_dhcp']))
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp del " . $IFACE);
else
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp set " . $IFACE . " " . $_POST['ip_addr'] . " " . $_POST['dhcp_start'] . " " . $_POST['dhcp_end'] . (!empty($_POST['dhcp_lease']) ? " " . $_POST['dhcp_lease'] : ''));

#################################################################################################
# Restarting networking service:
#################################################################################################
if ($_POST['reboot'] == "false")
{
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl restart networking");
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl restart dnsmasq");
	echo "OK";
}
else
	echo "REBOOT";
