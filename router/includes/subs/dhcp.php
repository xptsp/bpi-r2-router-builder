<?php
require_once("admin.php");

function dns_actions()
{
	$reserve = $hostname = $leases = array();

	###################################################################################################
	# If the "misc" parameter was passed, it has something to do with the DHCP code in this file:
	###################################################################################################
	if (!isset($_POST['misc']))
		return;
	$iface   = option('misc', '/^(' . implode("|", array_keys(get_network_adapters())) . ')$/');

	###################################################################################################
	# Parse the DNSMASQ configuration file for the specified interface:
	###################################################################################################
	foreach (explode("\n", @file_get_contents("/etc/dnsmasq.d/" . $iface . ".conf")) as $line)
	{
		$parts = explode("=", trim($line));
		$sub_parts = explode(",", !empty($parts[1]) ? $parts[1] : '');
		$sub_parts[1] = strtoupper(!empty($sub_parts[1]) ? $sub_parts[1] : '');
		if ($parts[0] == 'dhcp-host')
		{
			$arr = array();
			foreach ($sub_parts as $part)
			{
				if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
					$arr['ip'] = $part;
				else if (filter_var($part, FILTER_VALIDATE_MAC))
					$arr['mac'] = strtoupper($part);
				else if ($part != $iface)
					$arr['host'] = $part;
			}
			$reserve[$arr['ip']] = $reserve[$arr['mac']] = $arr;
			$reserve[$arr['mac']]['hide'] = true;
		}
		else if ($parts[0] == 'host-record' && !empty($sub_parts[1]))
			$hostname[$sub_parts[1]] = $sub_parts[0];
		else if ($parts[0] == 'dhcp-range')
			$subnet = substr($sub_parts[1], 0, strrpos($sub_parts[1], ".") + 1);
	}
	if (empty($subnet))
	{
		$parts = parse_ifconfig($iface);
		$subnet = substr($parts['inet'], 0, strrpos($parts['inet'], ".") + 1);
	}
	#echo '<pre>$reserve >> '; print_r($reserve); exit();
	#echo '<pre>$hostname >> '; print_r($hostname); exit();

	###################################################################################################
	# Parse the DNSMASQ leases files:
	###################################################################################################
	foreach (file("/var/lib/misc/dnsmasq.leases") as $lease)
	{
		$sub_parts = explode(' ', trim($lease));
		if (strpos($sub_parts[2], $subnet) != -1)
		{
			$leases[$sub_parts[1]] = $leases[strtoupper($sub_parts[2])] = $sub_parts;
			$leases[$sub_parts[1]]['hide'] = true;
		}
	}
	#echo '<pre>$leases >> '; print_r($leases); exit();

	###################################################################################################
	# ACTION: RESERVATIONS ==> Output list of DHCP leases for the specified adapter:
	###################################################################################################
	if ($_POST['action'] == 'reservations')
	{
		foreach ($reserve as $parts)
		{
			if (isset($parts['ip']) && empty($parts['hide']))
			{
				echo
					'<tr>' .
						'<td class="dhcp_host">' . (isset($parts['host']) ? $parts['host'] : (isset($hostname[$parts['mac']]) ? $hostname[$parts['mac']] : 'Unknown')) . '</td>' .
						'<td class="dhcp_ip_addr">' . $parts['ip'] . '</td>' .
						'<td class="dhcp_mac_addr">' . $parts['mac'] . '</td>' .
						'<td class="dhcp_edit"><a href="javascript:void(0);"><i class="fas fa-pen"></i></a></td>' .
						'<td class="dhcp_delete"><a href="javascript:void(0);"><i class="fas fa-trash-alt"></i></a></td>' .
					'</tr>';
			}
		}
		die( empty($reserve) ? '<tr><td colspan="5"><center>No IP Address Reservations</center></td></tr>' : '' );
	}
	###################################################################################################
	# ACTION: CLIENTS ==> Output list of DHCP clients for the specified adapter:
	###################################################################################################
	else if ($_POST['action'] == 'clients')
	{
		foreach ($leases as $id => $parts)
		{
			$parts[1] = strtoupper($parts[1]);
			if (empty($parts['hide']) && strpos($parts[2], $subnet) !== false)
				echo
				'<tr class="reservation-option">' .
					'<td class="dhcp_host">' . (!empty($parts[3]) ? $parts[3] : (isset($hostname[$parts[1]]) ? $hostname[$parts[1]] : 'Unknown')) . '</td>' .
					'<td class="dhcp_ip_addr">' . $parts[2] . '</td>' .
					'<td class="dhcp_mac_addr">' . $parts[1] . '</td>' .
					'<td><center><a href="javascript:void(0);"><i class="far fa-plus-square"></i></a></center></td>' .
				'</tr>';
			else
				unset($leases[$id]);
		}
		die( empty($leases) ? '<tr><td colspan="5"><center>ERROR: No Leases Found</center></td></tr>' : '' );
	}
	###################################################################################################
	# ACTION: REMOVE ==> Remove the specific DHCP reservation from the specified adapter:
	# ACTION: ADD ==> Add the specified DHCP reservation to the specified adapter:
	###################################################################################################
	else if ($_POST['action'] == 'remove' || $_POST['action'] == 'add')
	{
		$ip_addr = option_ip('ip_addr');
		$mac_addr = option_mac('mac_addr');
		$hostname = option('hostname', "/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/");
		die( @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp ' . $_POST['action'] . ' ' . $iface . ' ' . $mac_addr . ' ' . $ip_addr . ' ' . $hostname) );
	}
	###################################################################################################
	# ACTION: CHECK ==> Check to see if the IP and/or MAC have already been assigned.
	###################################################################################################
	else if ($_POST['action'] == 'check')
	{
		$ip_addr = option_ip('ip_addr');
		$mac_addr = option_mac('mac_addr');

		if (isset($reserve[$_POST['mac_addr']]))
			die( $reserve[$_POST['mac_addr']]['ip'] == $_POST['ip_addr'] ? 'SAME' : 'This MAC address has already been assigned to ' . $reserve[$_POST['mac_addr']]['ip'] . '.' );
		else if (isset($reserve[$_POST['ip_addr']]) && $reserve[$_POST['ip_addr']]['mac'] != $_POST['mac_addr'])
		{
			$res = &$reserve[$_POST['ip_addr']];
			$s = (!empty($res['host']) ? '&quot;' . $res['host'] . '&quot; with ' : '') . ' MAC Address ' . $res['mac'];
			die( 'This IP address has already been assigned to ' . $s . '.' );
		}
		else
			die( $_POST['ip_addr'] == trim(@shell_exec("arp | grep " . $iface . " | grep -i " . $_POST['mac_addr'] . " | awk '{print $1}'")) ? 'ADD' : 'OK' );
	}
	###################################################################################################
	# ACTION: Everything else ==> Let's just tell the user this page doesn't exist....
	###################################################################################################
	return;
}
