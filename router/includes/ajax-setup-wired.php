<?php
if (!isset($_POST['sid']))
	require_once("404.php");
if ($_POST['sid'] != $_SESSION['sid'])
	die('RELOAD');
require_once("subs/admin.php");

#echo '<pre>'; print_r($_POST); exit();

#################################################################################################
# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
#################################################################################################
$op_mode = option('op_mode', '/^(dhcp|static|bridged)$/');
$iface   = option('iface', '/^(' . implode("|", array_keys(get_network_adapters())) . ')$/');
$ip_addr = option_ip('ip_addr');
$ip_mask = option_ip('ip_mask');
$reboot  = option('reboot', "/^(true|false)$/");

#################################################################################################
# If using DHCP on this interface, make sure addresses are valid:
#################################################################################################
if (!empty($_POST['use_dhcp']))
{
	#################################################################################################
	# Make sure the IP address is held within the DHCP address range:
	#################################################################################################
	$mask    = '/^(' . implode('\.', explode(".", substr($ip_addr, 0, strrpos($ip_addr, '.')))) . '\.\d+)$/';
	$dhcp_start = option('dhcp_start', $mask);
	$dhcp_end   = option('dhcp_end', $mask);

	#################################################################################################
	# Make sure the client lease time is valid:
	#################################################################################################
	$dhcp_lease = option('dhcp_lease', '/^(infinite|(\d+)(m|h|d|w|))$/');
	if ($dhcp_lease != "infinite")
	{
		preg_match("/(\d+)(m|h|d|w|)/", $dhcp_lease, $parts);
		#echo '<pre>'; print_r($parts); exit;
		if (($parts[2] == '' && (int) $parts[1] < 120) || ($parts[2] == 'm' && (int) $parts[1] < 2) || ((int) $parts[1] < 1))
			die('ERROR: Invalid DHCP lease time!');
	}
}

#################################################################################################
# Create the network configuration for each of the bound network adapters:
#################################################################################################
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface delete " . $iface);
$bridged = array_diff( explode(" ", trim($_POST['bridge'])), array("undefined") );
if (empty($bridged))
	die("[BRIDGE] ERROR: No interfaces specified in bridge configuration!");
$text =
'allow-hotplug {iface}
auto {iface}
iface {iface} inet manual';
if (count($bridged) > 1)
{
	foreach ($bridged as $adapter)
	{
		$handle = fopen("/tmp/" . $adapter, "w");
		fwrite($handle, str_replace('{iface}', $adapter, $text));
		fclose($handle);
		$tmp = trim(@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $adapter));
		if ($tmp != "")
			die($tmp);
	}
	if (substr($iface, 0, 2) != "br")
		$iface = 'br' . strval( intval(str_replace("/etc/network/interfaces.d/br", "", trim(@shell_exec("ls /etc/network/interfaces.d/br* | sort | tail -1")))) + 1 );
}
else if (substr($iface, 0, 2) == "br")
	$iface = $bridged[0];

#################################################################################################
# Output the network adapter configuration to the "/tmp" directory:
#################################################################################################
$text =
'auto ' . $iface . '
iface ' . $iface . ' inet ' . ($_POST['op_mode'] == 'dhcp' ? 'dhcp' : 'static') . ($_POST['op_mode'] != 'dhcp' ? '
    address ' . $ip_addr . '
    netmask ' . $ip_mask . (!empty($_POST['gateway']) && $_POST['gateway'] != "0.0.0.0" ? '
	gateway ' . $_POST['ip_gate'] : '') : '') . ($_POST['op_mode'] == 'bridged' && count($bridged) > 1 ? '
    bridge_ports ' . implode(" ", $bridged) . '
    bridge_fd 5
    bridge_stp no' : '') . (in_array($iface, array('wan', 'br0')) ? '
    post-up echo ' . ($iface == 'wan' ? '6' : '8') . ' > /sys/class/net/wan/queues/rx-0/rps_cpus' : '');
#echo '<pre>'; echo $text; exit;
$handle = fopen("/tmp/" . $iface, "w");
fwrite($handle, $text);
fclose($handle);
$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $iface);
if ($tmp != "")
	die($tmp);

#################################################################################################
# Output the DNSMASQ configuration file related to the network adapter:
#################################################################################################
if (!empty($_POST['use_dhcp']))
	$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp del " . $adapter);
else
	$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp set " . $adapter . " " . $ip_addr . " " . $dhcp_start . " " . $dhcp_end . (!empty($dhcp_lease) ? " " . $dhcp_lease : ''));
if ($tmp != "")
	die($tmp);

#################################################################################################
# Restarting networking service:
#################################################################################################
if ($reboot == "false")
{
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl restart networking");
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh systemctl restart dnsmasq");
	echo "OK";
}
else
	echo "REBOOT";
