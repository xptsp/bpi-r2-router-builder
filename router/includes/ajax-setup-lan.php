<?php
if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}

#################################################################################################
# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
#################################################################################################
$hostname = isset($_POST['hostname']) ? $_POST['hostname'] : '';
if (!preg_match("/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/", $hostname))
	die("[HOSTNAME] ERROR: " . $_POST['hostname'] . " is not a valid hostname");

$iface = $_POST['iface'] = isset($_POST['iface']) ? $_POST['iface'] : '';
if (empty($_POST['iface']) || !file_exists("/sys/class/net/" . $_POST['iface']))
	die('[IFACE] ERROR: "' . $_POST['iface'] . '" is not a valid network interface!');

$_POST['ip_addr'] = isset($_POST['ip_addr']) ? $_POST['ip_addr'] : '';
if (!filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	die('[IP_ADDR] ERROR: "' . $_POST['ip_addr'] . '" is an invalid IPv4 address!');

$_POST['ip_mask'] = isset($_POST['ip_mask']) ? $_POST['ip_mask'] : '';
if (!filter_var($_POST['ip_mask'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	die('[IP_MASK] ERROR: "' . $_POST['ip_mask'] . '" is an invalid IPv4 address!');

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

//echo '<pre>'; print_r($_POST); exit();

#################################################################################################
# Get old IP address for the adapter in question:
#################################################################################################
$old_addr = trim(@shell_exec('cat /etc/network/interfaces.d/' . $iface . ' | grep " address" | awk \'{print $2}\''));
//echo $old_addr; exit;

#################################################################################################
# Output the network adapter configuration to the "/tmp" directory:
#################################################################################################
$text = 
'auto ' . $iface . '
iface ' . $iface . ' inet static
    hwaddress ether ' . explode(" ", trim(@shell_exec('grep "hwaddress" /etc/network/interfaces.d/' . $iface)))[2] . '
    address ' . $_POST['ip_addr'] . '
    netmask ' . $_POST['ip_mask'] . (!empty($_POST['bridge']) ? '
    bridge_ports ' . trim($_POST['bridge']) . '
    bridge_fd 5
    bridge_stp no' : '') . ($iface == "br0" ? '
    post-up echo 6 > /sys/class/net/wan/queues/rx-0/rps_cpus' : '') . '
';
#echo echo '<pre>'; echo $text; exit;
$handle = fopen("/tmp/" . $iface, "w");
fwrite($handle, $text);
fclose($handle);

#################################################################################################
# Change the DNS servers by calling the router-helper script:
#################################################################################################
@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh move_config " . $iface);
echo "OK";
