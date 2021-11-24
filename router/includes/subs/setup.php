<?php

###################################################################################################
# Function displaying settings for the specified network interface:
###################################################################################################
function get_network_adapters_list($get_bridged = true)
{
	$out = array('wan' => 'wan');
	foreach (glob("/sys/class/net/*") as $iface)
	{
		$base = basename($iface);
		$found = false;
		foreach (explode("\n", @file_get_contents("/etc/network/interfaces.d/" . $base)) as $line)
		{
			if ($found = preg_match('/bridge_ports\s+(.*)/', $line, $regex))
				break;
		}
		if (!$found)
			$out[$base] = $base;
	}
	return $out;
}

function device_name($mac)
{
	global $leases;
	foreach ($leases as $id => $lease)
	{
		if (isset($lease[3]) && $mac == strtoupper($lease[1]))
			return $lease[3];
	}
	return "Unknown";
}

function get_invalid_adapters($iface)
{
	global $ifaces;
	$arr = array();
	foreach ($ifaces as $tface => $bound)
	{
		if ($tface != $iface)
			$arr = array_merge($bound, $arr);
	}
	return $arr;
}
