<?php
if (!isset($_POST['iface']) || !isset($_POST['action']) || !isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
{
	require_once("404.php");
	exit();
}
require_once("subs/setup.php");

###################################################################################################
# Assemble necessary information for this script to function:
###################################################################################################
$iface = $_POST['iface'];
$reserve = $hostname = $leases = array();

foreach (explode("\n", @file_get_contents("/etc/dnsmasq.d/" . $iface . ".conf")) as $line)
{
	$parts = explode("=", trim($line));
	$sub_parts = explode(",", !empty($parts[1]) ? $parts[1] : '');
	if ($parts[0] == 'dhcp-host')
		$reserve[$sub_parts[2]] = explode(',', $parts[1]);
	else if ($parts[0] == 'host-record')
		$hostname[!empty($sub_parts[1]) ? strtoupper($sub_parts[1]) : ''] = $sub_parts[0];
	else if ($parts[0] == 'dhcp-range')
		$subnet = substr($sub_parts[1], 0, strrpos($sub_parts[1], ".") + 1);
}
#echo '<pre>$reserve >> '; print_r($reserve); exit();
#echo '<pre>hostname >> '; print_r($hostname); exit();

foreach (explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases"))) as $lease)
{
	$sub_parts = explode(' ', $lease);
	if (strpos($sub_parts[2], $subnet) != -1)
		$leases[] = $sub_parts;
}
#echo '<pre>$leases >> '; print_r($leases); exit();

###################################################################################################
# ACTION: RESERVATIONS ==> Output list of DHCP leases for the specified adapter:
###################################################################################################
if ($_POST['action'] == 'reservations')
{
	foreach ($reserve as $parts)
	{
		if (isset($parts[2]))
		{
			$parts[1] = strtoupper($parts[1]);
			echo
				'<tr>' .
					'<td class="dhcp_host">' . (isset($parts[3]) ? $parts[3] : (isset($hostname[$parts[1]]) ? $hostname[$parts[1]] : 'Unknown')) . '</td>' .
					'<td class="dhcp_ip_addr">' . $parts[2] . '</td>' .
					'<td class="dhcp_mac_addr">' . $parts[1] . '</td>' .
					'<td class="dhcp_edit"><i class="fas fa-pen"></i></td>' .
					'<td class="dhcp_delete"><i class="fas fa-trash-alt"></i></td>' .
				'</tr>';
		}
	}
	if (empty($reserve))
		echo '<tr><td colspan="5"><center>No IP Address Reservations</center></td></tr>';
}
###################################################################################################
# ACTION: CLIENTS ==> Output list of DHCP clients for the specified adapter:
###################################################################################################
else if ($_POST['action'] == 'clients')
{
	foreach ($leases as $parts)
	{
		$parts[1] = strtoupper($parts[1]);
		echo
			'<tr class="reservation-option">' .
				'<td class="dhcp_host">' . (!empty($parts[3]) ? $parts[3] : (isset($hostname[$parts[1]]) ? $hostname[$parts[1]] : 'Unknown')) . '</td>' .
				'<td class="dhcp_ip_addr">' . $parts[2] . '</td>' .
				'<td class="dhcp_mac_addr">' . $parts[1] . '</td>' .
				'<td><center><i class="far fa-plus-square"></i></center></td>' .
			'</tr>';
	}
	if (empty($leases))
		echo '<tr><td colspan="5"><center>ERROR: No Leases Found</center></td></tr>';
}
###################################################################################################
# ACTION: REMOVE ==> Remove the specific DHCP reservation from the specified adapter:
# ACTION: ADD ==> Add the specified DHCP reservation to the specified adapter:
###################################################################################################
else if ($_POST['action'] == 'remove' || $_POST['action'] == 'add')
{
	$_POST['ip_addr'] = isset($_POST['ip_addr']) ? $_POST['ip_addr'] : '';
	if (!filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[IP_ADDR] ERROR: "' . $_POST['ip_addr'] . '" is an invalid IPv4 address!');

	$_POST['mac_addr'] = isset($_POST['mac_addr']) ? $_POST['mac_addr'] : '';
	if (!filter_var($_POST['mac_addr'], FILTER_VALIDATE_MAC))
		die('[MAC_ADDR] ERROR: "' . $_POST['mac_addr'] . '" is an invalid MAC address!');

	$action = $_POST['action'] == 'remove' ? 'dhcp_del' : 'dhcp_add';
	$action .=  ' ' . $_POST['iface'] . ' ' . $_POST['mac_addr'] . ' ' . $_POST['ip_addr'] . ' ' . $_POST['hostname'];
	echo @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh ' . $action);
}
###################################################################################################
# ACTION: CLIENTS ==> Output list of DHCP clients for the specified adapter:
###################################################################################################
else if ($_POST['action'] == 'check')
{
	$_POST['ip_addr'] = isset($_POST['ip_addr']) ? $_POST['ip_addr'] : '';
	if (!filter_var($_POST['ip_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		die('[IP_ADDR] ERROR: "' . $_POST['ip_addr'] . '" is an invalid IPv4 address!');

	$_POST['mac_addr'] = isset($_POST['mac_addr']) ? $_POST['mac_addr'] : '';
	if (!filter_var($_POST['mac_addr'], FILTER_VALIDATE_MAC))
		die('[MAC_ADDR] ERROR: "' . $_POST['mac_addr'] . '" is an invalid MAC address!');

	echo empty($reserve[$_POST['ip_addr']]) || $reserve[$_POST['ip_addr']][1] == $_POST['mac_addr'] ? 'OK' : 'Taken';
}
###################################################################################################
# ACTION: Everything else ==> Let's just tell the user this page doesn't exist....
###################################################################################################
else
	echo "INFO: Unrecognized Action";
