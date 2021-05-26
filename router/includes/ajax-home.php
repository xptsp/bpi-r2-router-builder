<?php
if (!isset($_GET['sid']) || $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
header('Content-type: application/json');
require_once('subs/admin.php');

##########################################################################################
# Get information for the AJAX request:
##########################################################################################
$load = sys_getloadavg();
$temp = number_format((float) @file_get_contents('/sys/devices/virtual/thermal/thermal_zone0/temp') / 1000, 1);
$pihole = @json_decode( @file_get_contents( "http://pi.hole/admin/api.php?summary" ) );

##########################################################################################
# Insert hardware statistics information into array:
##########################################################################################
$arr = array(
	'load0' => number_format((float)$load[0], 2),
	'load1' => number_format((float)$load[1], 2),
	'load2' => number_format((float)$load[2], 2),
	'temp' => $temp,
	'temp_icon' => 'fa-thermometer-' . ($temp > 70 ? 'full' : ($temp > 60 ? 'three-quarters' : ($temp > 50 ? 'half' : ($temp > 40 ? 'quarter' : 'empty')))),
	'system_uptime' => system_uptime(),
	'server_time' => date('Y-m-d H:i:s'),
	'lan_devices' => array(),
	'lan_count' => 0,
	'usb_devices' => array(),
	'usb_count' => 0,
);

##########################################################################################
# Insert Pi-Hole statistics information into array:
##########################################################################################
if (isset($pihole->unique_clients))
	$arr['unique_clients'] = $pihole->unique_clients;
if (isset($pihole->dns_queries_today))
	$arr['dns_queries_today'] = $pihole->dns_queries_today;
if (isset($pihole->ads_blocked_today))
	$arr['ads_blocked_today'] = $pihole->ads_blocked_today;
if (isset($pihole->ads_percentage_today))
	$arr['ads_percentage_today'] = $pihole->ads_percentage_today;
if (isset($pihole->domains_being_blocked))
	$arr['domains_being_blocked'] = $pihole->domains_being_blocked;

##########################################################################################
# Return WAN status:
##########################################################################################
$wan_if = parse_ifconfig('wan');
if (strpos($wan_if['brackets'], 'RUNNING') === false)
	$arr['wan_status'] = 'Disconnected';
else
	$arr['wan_status'] = strpos(@shell_exec('ping -c 1 -W 1 8.8.8.8'), '1 received') > 0 ? 'Online' : 'Offline';

##########################################################################################
# Parse the dnsmasq.leases file into the "devices" element of the array:
##########################################################################################
foreach (explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases"))) as $num => $line)
{
	$temp = explode(" ", preg_replace("/\s+/", " ", $line));
	$arr['lan_devices'][] = array(
		'lease_expires' => $temp[0],
		'mac_address' => $temp[1],
		'ip_address' => $temp[2],
		'machine_name' => $temp[3],
	);
}
$arr['lan_count'] = count($arr['lan_devices']);

##########################################################################################
# Get the number of mounted USB devices:
##########################################################################################
foreach (glob('/etc/samba/smb.d/*.conf') as $file)
{
	foreach (explode("\n", trim(@file_get_contents($file))) as $line)
	{
		if (preg_match("/path=(\/media\/.*)/", $line, $regex))
			$arr['usb_devices'][basename($file)]['path'] = $regex[1];
		if (preg_match("/\#mount_dev=(.*)/", $line, $regex))
			$arr['usb_devices'][basename($file)]['mount_dev'] = $regex[1];
	}
}
$arr['usb_count'] = count($arr['usb_devices']);

##########################################################################################
# Output the resulting array:
##########################################################################################
echo json_encode($arr);
die();
