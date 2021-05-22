<?php

################################################################################################
# Function that returns the system uptime as a string:
################################################################################################
function system_uptime($sep = ', ')
{
	$str	 = @file_get_contents('/proc/uptime');
	$num	 = floatval($str);
	$secs	= $num % 60;
	$num	 = (int)($num / 60);
	$mins	= $num % 60;
	$num	 = (int)($num / 60);
	$hours = $num % 24;
	$num	 = (int)($num / 24);
	$days	= $num;
	$num	 = (int)($num / 365.24);
	$years = $num;
	if ($years > 0)
		return sprintf('%d year' . ($years > 1 ? 's' : '') . $sep . '%d day' . ($days > 1 ? 's' : ''), $years, $days);
	else if ($days > 0)
		return sprintf('%d day' . ($days > 1 ? 's' : '') . $sep . '%d hour' . ($hours > 1 ? 's' : ''), $days, $hours);
	else if ($hours > 0)
		return sprintf('%d hour' . ($hours > 1 ? 's' : '') . $sep . '%d minute' . ($mins > 1 ? 's' : ''), $hours, $mins);
	else
		return sprintf('%d minute' . ($mins > 1 ? 's' : ''), $mins);
}

################################################################################################
# Function that returns information from the specified /etc/network/interfaces.d file:
################################################################################################
function get_mac_info($interface)
{
	$file = @file_get_contents('/etc/network/interfaces.d/' . $interface);
	$arr = array();
	foreach (explode("\n", $file) as $line)
	{
		$parts = explode(' ', trim($line));
		$arr[$parts[0]] = str_replace($parts[0], '', trim($line));
	}
	#echo '<pre>'; print_r($arr); exit();
	return $arr;
}

################################################################################################
# Function that returns the output of ifconfig for a particular interface:
################################################################################################
function parse_ifconfig($interface)
{
	$ret = array();
	if (!empty($interface))
	{
		foreach (explode("\n", trim(shell_exec('/sbin/ifconfig ' . $interface))) as $line)
		{
			if (preg_match('/inet (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $line, $regex))
				$ret['inet'] = $regex[1];
			if (preg_match('/netmask (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $line, $regex))
				$ret['netmask'] = $regex[1];
			if (preg_match('/broadcast (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $line, $regex))
				$ret['broadcast'] = $regex[1];
			if (preg_match("/ether ([0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f])/", $line, $regex))
				$ret['ether'] = $regex[1];
			if (preg_match("/inet6 ([0-9a-f][0-9a-f][0-9a-f][0-9a-f]::[0-9a-f][0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f][0-9a-f][0-9a-f]:[0-9a-f])/", $line, $regex))
				$ret['inet6'] = $regex[1];
			if (preg_match("/mtu\s+(\d+)/", $line, $regex))
				$ret['mtu'] = $regex[1];
			if (preg_match("/flags\=(\d+)\<([^\>]*)\>/", $line, $regex))
			{
				$ret['flags'] = $regex[1];
				$ret['brackets'] = $regex[2];
			}
			if (preg_match("/RX packets\s+(\d+)\s+bytes\s+(\d+)/", $line, $regex))
			{
				$ret['rx_packets'] = $regex[1];
				$ret['rx_bytes'] = $regex[2];
			}
			if (preg_match("/RX errors (\d+)\s+dropped\s+(\d+)\s+overruns\s+(\d+)/", $line, $regex))
			{
				$ret['tx_errors'] = $regex[1];
				$ret['tx_dropped'] = $regex[2];
				$ret['tx_overruns'] = $regex[3];
			}
			if (preg_match("/TX packets\s+(\d+)\s+bytes\s+(\d+)/", $line, $regex))
			{
				$ret['tx_packets'] = $regex[1];
				$ret['tx_bytes'] = $regex[2];
			}
			if (preg_match("/TX errors (\d+)\s+dropped\s+(\d+)\s+overruns\s+(\d+)\s+carrier\s+(\d+)\s+collisions\s+(\d+)/", $line, $regex))
			{
				$ret['tx_errors'] = $regex[1];
				$ret['tx_dropped'] = $regex[2];
				$ret['tx_overruns'] = $regex[3];
				$ret['carrier'] = $regex[4];
				$ret['collisions'] = $regex[5];
			}
		}
	}
	#echo '<pre>'; print_r($ret); exit();
	return $ret;
}

################################################################################################
# Function that returns PiHole DNS servers:
################################################################################################
function get_dns_servers()
{
	$ip = array();
	foreach (explode("\n", @file_get_contents('/etc/pihole/setupVars.conf')) as $line)
	{
		if (preg_match('/PIHOLE_DNS_\d+\=(.*)/', $line, $regex))
			$ip[] = $regex[1];
	}
	#echo '<pre>'; print_r($ip); exit();
	return $ip;
}

################################################################################################
# Function that returns names of all network adapters on the system:
################################################################################################
function get_network_adapters()
{
	$arr = array();
	$bridged = array();
	foreach (glob("/sys/class/net/*") as $iface)
	{
		$name = basename($iface);
		if (!in_array($name, $bridged))
		{
			$arr[$name] = array();
			foreach (explode("\n", @file_get_contents("/etc/network/interfaces.d/" . $name)) as $line)
			{
				if (preg_match('/bridge_ports\s+(.*)/', $line, $regex))
				{
					$arr[$name] += $ifaces = explode(" ", $regex[1]);
					$bridged += $ifaces;
				}
				if (preg_match('/Nickname\s+(.*)/', $line, $regex))
					$arr[$name]['nickname'] = $regex[1];
			}
		}
	}
	return $arr;
}
